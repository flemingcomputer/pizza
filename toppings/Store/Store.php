<?php
// Copyright 2022 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Store
{
    // Element attributes.
    public $disableCart;

    // Form inputs for when editing a 'store-item' page.
    private $attachedFiles;
    private $body;
    private $files;
    private $prices;
    private $quantities;
    private $options;
    private $title;

    private $cm;
    private $editing;
    private $id;
    private $numPrices;
    private $s;
    private $tempPagePath;
    private $tp;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->s = $GLOBALS['pizza']['settings'];
        $this->tp = $GLOBALS['pizza']['toppings'];
        $this->numPrices = 8;
    }

    function applyTopping($html)
    {
        // If this page is not a 'store-item', then only apply any
        // '[store]' element and return.
        if ($GLOBALS['pizza']['page']['kind'] != 'store-item')
        {
            return preg_replace_callback(
                '/\[(store)(]|\s+.*]|,.*])/sU',
                array($this, '_applyTopping'),
                $html
            );
        }

        // This is a 'store-item' page.
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        // Get parent's [store] options.
        $parentPagePath = $this->cm->parentPath($pagePath);
        $pp = $this->cm->getPage($parentPagePath);
        preg_match('/\[(store)(]|\s+.*]|,.*])/sU', $pp['body'], $matches);
        $e = substr($matches[0], 1, -1);
        $this->disableCart = $this->tp->getYesNo($e, 'disable-cart', 'no') == 'yes';

        if (hg('addToCart') && !$this->disableCart)
        {
            $qtyToAdd = substr(gge('quantity', '1'), 0, 5);
            if (!is_numeric($qtyToAdd) || (intval($qtyToAdd) != $qtyToAdd))
                relocateNow($urlRoot . $pagePath);
            $option = substr(gge('option', ''), 0, 15);
            $result = $GLOBALS['pizza']['cart']->updateCart($pagePath, $option, $qtyToAdd);
            if ($result) relocateNow($urlRoot . '/cart/');
        }

        $this->id = "Store_$pagePath";
        $this->initThis();

        if (($_SERVER['REQUEST_METHOD'] ?? '') == 'POST')
        {
            // A form was submitted.
            if (empty($_POST))
            {
                // post_max_size was likely exceeded.
                $maxPostSize = ini_get('post_max_size');
                $u = strtoupper(substr($maxPostSize, -1));
                if ($u == 'K') $maxPostSize = substr($maxPostSize, 0, -1) * 1024;
                else if ($u == 'M') $maxPostSize = substr($maxPostSize, 0, -1) * 1048576;
                else if ($u == 'G') $maxPostSize = substr($maxPostSize, 0, -1) * 1073741824;
                if ($_SERVER['CONTENT_LENGTH'] > $maxPostSize)
                {
                    $this->files['value'] = '';
                    $this->files['errors'] = array();
                    $this->files['errors'][0] = 'total upload is too large ('
                        . ini_get('post_max_size') . ' max)';
                    ss($this->id, $this);
                }
            }
            else if (isset($_POST['formId']) && ($_POST['formId'] == $this->id))
                $this->validateInput($_POST);
            return $this->tp->protect($this->showForm());
        }

        if (hg('editPage') || $this->editing)
        {
            // If attempting to edit the page without write permission, do unauthorized.
            $userId = $GLOBALS['pizza']['user']['id'];
            if (!isWritable() || ($userId == 0)) errorUnauthorized();
            if ((lockedBy() == 0) && $this->editing)
            {
                // This user had the lock but lost it to another user who changed the
                // page.  Clear this user's stale state and show page-changed error.
                $this->editing = false;
                ss($this->id, $this);
                errorPageChanged();
            }
            if ((lockedBy() > 0) && (lockedBy() != $userId))
            {
                // Another user has the lock.  Clear state and show page-locked error.
                $this->editing = false;
                ss($this->id, $this);
                errorPageLocked();
            }
            // Grant/renew the lock.
            lockPage();

            $this->editing = true;
            ss($this->id, $this);
            return $this->tp->protect($this->showForm());
        }

        return $this->showItem($html);
    }

    function getFiles($pagePath)
    {
        $files = $this->cm->getFileInfo($pagePath);
        if ($files === false) $files = array();
        // Sort them in currently specified order.
        $sortIds = $this->s->get($pagePath, 'Store_sortOrder');
        if ($sortIds === false) $sortIds = array();
        // Reconstruct a new sorted files array.
        // Rekey files based on file ids.
        $temp = array();
        foreach ($files as $v) $temp[$v['id']] = $v;
        $files = $temp; unset($temp);
        // Remove nonexistent ids from sortIds.
        $temp = array();
        foreach ($sortIds as $k)
            if (isset($files[$k])) $temp[] = $k;
        $sortIds = $temp; unset($temp);
        // Add remaining ids to sortIds.
        foreach ($files as $v)
            if (!in_array($v['id'], $sortIds)) $sortIds[] = $v['id'];
        // All ids are now in sortIds.
        // Finally, reconstruct the sorted files array.
        $temp = array();
        foreach ($sortIds as $k)
        {
            $temp[] = $files[$k];
            unset($files[$k]);
        }
        foreach ($files as $k => $i) $temp[] = $i;
        $files = $temp; unset($temp);
        return $files;
    }

    function getItems($pagePath)
    {
        $items = $this->cm->getChildren($pagePath, 'store-item');
        // Include any subpages that have a '[store]' element for displaying
        // like an item; i.e. so the user can go deeper into the store.
        $substores = $this->cm->getChildren($pagePath, 'page');
        foreach ($substores as $key => $sp)
            if (!preg_match('/\[(store)(]|\s+.*]|,.*])/sU', $sp['body']))
                unset($substores[$key]);
        // Merge the substores and items arrays.
        $items = $substores + $items;
        // Sort them in currently specified order.
        $sortIds = $this->s->get($pagePath, 'Store_sortOrder');
        if ($sortIds === false) $sortIds = array();
        // Reconstruct a new sorted items array.
        // Remove nonexistent ids from sortIds.
        $temp = array();
        foreach ($sortIds as $k)
            if (isset($items[$k])) $temp[] = $k;
        $sortIds = $temp; unset($temp);
        // Add remaining ids to sortIds.
        foreach ($items as $v)
            if (!in_array($v['id'], $sortIds)) $sortIds[] = $v['id'];
        // All ids are now in sortIds.
        // Finally, reconstruct the sorted items array.
        $temp = array();
        foreach ($sortIds as $k)
        {
            $temp[$k] = $items[$k];
            unset($items[$k]);
        }
        foreach ($items as $k => $i) $temp[$k] = $i;
        $items = $temp; unset($temp);
        // Items are now sorted.
        foreach ($items as $k => $item)
        {
            // Get the item's files.
            $items[$k]['files'] = $this->getFiles($this->cm->pageIdToPagePath($item['id']));
            // Get the item's prices.
            $settings = unserialize($item['settings']);
            if (!isset($settings['Store_prices'])) continue;
            $prices = array();
            for ($i = 0; $i < count($settings['Store_prices']); $i++)
            {
                $p = $settings['Store_prices'][$i];
                if ($p == '') break;
                $s = $settings['Store_options'][$i];
                if ($s == '')
                {
                    // There is only one price and no option.
                    $prices[] = $p;
                    break;
                }
                $prices[$s] = $p;
            }
            $items[$k]['prices'] = $prices;
        }
        return $items;
    }

    private function _applyTopping($matches)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $e = substr($matches[0], 1, -1);
        $this->disableCart = $this->tp->getYesNo($e, 'disable-cart', 'no') == 'yes';
        $this->id = "Store_$pagePath";
        $o = sge($this->id, false);
        $this->tempPagePath = $o ? $o->tempPagePath : '';

        if (isWritable() &&
            (hg('addItem') || $this->cm->hasPage($this->tempPagePath)))
        {
            // Either adding just now or started earlier and need to finish.
            if (!$this->cm->hasPage($this->tempPagePath))
            {
                $this->tempPagePath = $this->cm->addTempPage($pagePath, 'store-item', 'y');
                ss($this->id, $this);
            }
            relocateNow($urlRoot . $this->tempPagePath . '?editPage');
        }

        return $this->tp->protect($this->showItems());
    }

    private function formatMoney($v)
    {
        if (!is_numeric($v)) return $v;
        if (intval($v) == $v) return number_format($v, 0);
        return number_format($v, 2);
    }

    private function initThis()
    {
        $settings = $GLOBALS['pizza']['page']['settings'];
        $o = sge($this->id, false);
        $this->attachedFiles = $o ? $o->attachedFiles : array('value' => '', 'error' => '');
        $this->body = $o ? $o->body : array(
            'value' => $GLOBALS['pizza']['page']['body'],
            'error' => ''
        );
        $this->files = $o ? $o->files : array('value' => '', 'errors' => array());
        $this->prices = array();
        $this->quantities = array();
        $this->options = array();
        if ($o)
        {
            $this->prices = $o->prices;
            $this->quantities = $o->quantities;
            $this->options = $o->options;
        }
        else if (isset($settings['Store_prices']) && is_array($settings['Store_prices'])
            && isset($settings['Store_quantities']) && is_array($settings['Store_quantities'])
            && isset($settings['Store_options']) && is_array($settings['Store_options']))
        {
            foreach ($settings['Store_prices'] as $p)
                $this->prices[] = array('value' => $p, 'error' => '');
            foreach ($settings['Store_quantities'] as $q)
                $this->quantities[] = array('value' => $q, 'error' => '');
            foreach ($settings['Store_options'] as $s)
                $this->options[] = array('value' => $s, 'error' => '');
            // If necessary, expand to meet numPrices; e.g. numPrices increased recently.
            for ($i = count($this->prices); $i < $this->numPrices; $i++)
            {
                $this->prices[] = array('value' => '', 'error' => '');
                $this->quantities[] = array('value' => '', 'error' => '');
                $this->options[] = array('value' => '', 'error' => '');
            }
        }
        else
        {
            for ($i = 0; $i < $this->numPrices; $i++)
            {
                $this->prices[] = array('value' => '', 'error' => '');
                $this->quantities[] = array('value' => '', 'error' => '');
                $this->options[] = array('value' => '', 'error' => '');
            }
        }
        $this->title = $o ? $o->title : array(
            'value' => !$this->cm->isTempPage($GLOBALS['pizza']['pagePath'])
                ? $GLOBALS['pizza']['page']['pageName'] : '',
            'error' => ''
        );
        $this->editing = $o ? $o->editing : false;
    }

    private function showForm()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        addStyle("$urlRoot/toppings/Store/default.css");
        ob_start();
        ?>
        <div class="page-editor" id="page-editor">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <div>
                    <?php
                    $class = $this->title['error'] != '' ? 'class="title error-placeholder"' : 'class="title"';
                    ?>
                    <input <?=$class?> name="title" placeholder="Item Name" type="text" value="<?=myHtmlEntities($this->title['value'])?>">
                    <?php
                    if ($this->title['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->title['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div style="margin-top: 20px;">
                    Prices, Quantities, and Options:<br>
                    (Leave 'qty' blank for unlimited, 'opt' can be S, M, Blue, ...)<br>
                    <?php
                    for ($i = 0; $i < $this->numPrices; $i++)
                    {
                        $class = $this->prices[$i]['error'] != '' ? 'class="options error-placeholder"' : 'class="options"';
                        $placeHolder = $this->prices[$i]['error'] != '' ? 'price' : '';
                        $p = $this->formatMoney($this->prices[$i]['value']);
                        ?>
                        <div style="display: inline-block; text-align: right; width: 1em;">$</div><input <?=$class?> name="prices[]" placeholder="<?=$placeHolder?>" type="text" value="<?=myHtmlEntities($p)?>">
                        <?php
                    }
                    echo '<br>';
                    for ($i = 0; $i < $this->numPrices; $i++)
                    {
                        $class = $this->quantities[$i]['error'] != '' ? 'class="options error-placeholder"' : 'class="options"';
                        ?>
                        <div style="display: inline-block; text-align: right; width: 1em;">&nbsp;</div><input <?=$class?> name="quantities[]" placeholder="qty<?=$i+1?>" type="text" value="<?=myHtmlEntities($this->quantities[$i]['value'])?>">
                        <?php
                    }
                    echo '<br>';
                    for ($i = 0; $i < $this->numPrices; $i++)
                    {
                        $class = $this->options[$i]['error'] != '' ? 'class="options error-placeholder"' : 'class="options"';
                        ?>
                        <div style="display: inline-block; text-align: right; width: 1em;">&nbsp;</div><input <?=$class?> name="options[]" placeholder="opt<?=$i+1?>" type="text" value="<?=myHtmlEntities($this->options[$i]['value'])?>">
                        <?php
                    }
                    ?>
                </div>

                <div data-filer="hide" style="margin-top: 20px;">
                    <?php
                    $fileInfo = $this->cm->getFileInfo($pagePath);
                    if ($fileInfo !== false)
                    {
                        ?>
                        <img src="<?=$urlRoot?>/images/paperclip.gif" border="0" width="15" height="15" alt="paperclip"> Attached Photos (to delete, check desired boxes and click Save):
                        <div class="attached-files">
                        <?php
                        foreach ($fileInfo as $f)
                        {
                            $fileName = $f['fileName'];
                            $fileSize = $f['fileSize'];
                            $mFileName = myHtmlEntities($fileName);
                            if ($fileSize < 1000) $fileSize .= 'B';
                            else if ($fileSize < 1000000) $fileSize = number_format($fileSize / 1000) . 'K';
                            else $fileSize = number_format($fileSize / 1000000, 1) . 'M';
                            $imgUrl = "$urlRoot/images/mime/generic.gif";
                            $fileDetails = "$fileSize";
                            if (
                                ($f['mimeType'] == 'image/jpeg')
                                || ($f['mimeType'] == 'image/pjpeg')
                                || ($f['mimeType'] == 'image/gif')
                                || ($f['mimeType'] == 'image/png')
                                || ($f['mimeType'] == 'image/svg+xml')
                                || ($f['mimeType'] == 'image/webp'))
                            {
                                if ($f['dimensions'] != '')
                                {
                                    [$x, $y] = explode('x', $f['dimensions']);
                                    $fileDetails = "$fileSize, {$x}x{$y}";
                                }
                                else
                                {
                                    $fileDetails = "$fileSize";
                                }
                                $imgUrl = $mFileName . '?width=48&amp;height=16';
                            }
                            else if (substr($f['mimeType'], -3, 3) == 'pdf')
                                $imgUrl = "$urlRoot/images/mime/pdf.gif";
                            else if (substr($f['mimeType'], 0, 5) == 'audio')
                                $imgUrl = "$urlRoot/images/mime/audio.gif";
                            ?>
                            <input name="attachedFiles[]" type="checkbox" value="<?=$mFileName?>">
                            <img src="<?=$imgUrl?>" border="0" alt="<?=$mFileName?>">
                            <a href="<?=$mFileName?>"><?=$mFileName?></a> (<?=$fileDetails?>)<br>
                            <?php
                        }
                        ?>
                        </div>
                        <?php
                    }
                    $maxFileSize = ini_get('upload_max_filesize');
                    $u = strtoupper(substr($maxFileSize, -1));
                    if ($u == 'K') $maxFileSize = substr($maxFileSize, 0, -1) * 1024;
                    else if ($u == 'M') $maxFileSize = substr($maxFileSize, 0, -1) * 1048576;
                    else if ($u == 'G') $maxFileSize = substr($maxFileSize, 0, -1) * 1073741824;
                    ?>
                    <input name="MAX_FILE_SIZE" type="hidden" value="<?=$maxFileSize?>">
                    Upload Photos: <input name="files[]" type="file" multiple="multiple">
                    <!-- <input name="upload" type="submit" value="Upload"> -->
                    <?php
                    if (count($this->files['errors']) > 0)
                    {
                        $errors = $this->files['errors'];
                        foreach ($errors as $i => $e)
                        {
                            $fileName = '';
                            if (isset($this->files['value']['name'][$i]))
                                $fileName = $this->files['value']['name'][$i] . ': ';
                            ?>
                            <div class="error">↑<?=myHtmlEntities($fileName)?><?=myHtmlEntities($e)?>↑</div>
                            <?php
                            unset($this->files['errors'][$i]);
                        }
                    }
                    ?>
                </div>

                <?php
                addJavaScript("$urlRoot/js/filer.js");
                ?>
                <script type="text/javascript">
                    $(document).ready(function() {
                        $("#filer").pizzaFiler({
                            handler: '<?="$urlRoot/ajax/filer-handler"?>',
                            maxFileSize: <?=$maxFileSize?>
                        });
                    });
                </script>
                <input id="filer" name="filer[]" type="file" multiple="multiple">

                <textarea name="body" placeholder="Description" style="margin-top: 20px;"><?=myHtmlEntities($this->body['value'])?></textarea>
                <?php
                if ($this->body['error'] != '')
                {
                    ?>
                    <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->body['error'])?>↑</div>
                    <?php
                }
                ?>

                <div>
                    <input class="submit" name="save" type="submit" value="Save">
                    <input class="submit" name="cancel" type="submit" value="Cancel">
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showItem($html)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        addStyle("$urlRoot/toppings/Store/default.css");
        addJavaScript("$urlRoot/toppings/Store/Store.js");
        ob_start();
        $prices = array(); $options = array();
        $availables = array(); $carteds = array();
        $minPrice = 9999999; $maxPrice = 0;
        [$qtyAvailable, $qtyCarted] = $GLOBALS['pizza']['cart']->quantitiesAvailable($pagePath);
        // If a price is blank but has an option, use the previous non-empty price.
        $prevPrice = $this->prices[0]['value'];
        for ($i = 0; $i < count($this->prices); $i++)
        {
            $p = $this->prices[$i]['value'];
            $o = $this->options[$i]['value'];
            if (($p == '') && ($o == '')) break;
            if ($p == '') $p = $prevPrice;
            $prevPrice = $p;
            $prices[] = $p;
            $availables[] = $qtyAvailable[$i];
            $carteds[] = $qtyCarted[$i];
            $options[] = $o;
            if ($p < $minPrice) $minPrice = $p;
            if ($p > $maxPrice) $maxPrice = $p;
        }
        // Create data-prices and data-options for use by JavaScript.
        $dataPrices = '';
        foreach ($prices as $p) $dataPrices .= $p . ',';
        $dataPrices = myHtmlEntities(substr($dataPrices, 0, -1));
        $dataOptions = '';
        foreach ($options as $s) $dataOptions .= $s . ',';
        $dataOptions = myHtmlEntities(substr($dataOptions, 0, -1));
        $priceRange = '$' . $this->formatMoney($minPrice);
        if ($maxPrice > $minPrice) $priceRange .= ' - $' . $this->formatMoney($maxPrice);
        ?>
        <h1><?=myHtmlEntities($this->title['value'])?></h1>
        <div id="add-to-cart" data-prices="<?=$dataPrices?>" data-options="<?=$dataOptions?>">
            <?php
            if ($options[0] !== '')
            {
                ?>
                <span id="item-price" style="font-size: larger;"><?=$priceRange?></span>
                <?php
                ob_start();
                ?>
                <br>
                <select id="select-option" name="selectOption">
                    <option value="blank">Select</option>
                    <?php
                    $totalCarted = 0;
                    for ($i = 0; $i < count($prices); $i++)
                    {
                        $disabled = '';
                        $option = $options[$i];
                        $available = $availables[$i];
                        $carted = $carteds[$i];
                        $totalCarted += $carted;
                        if ($available > 0)
                        {
                            $mOption = myHtmlEntities($option);
                            if ($available == 1)
                                $mOption .= "&nbsp;&nbsp;&nbsp;&nbsp;LAST ONE!";
                            else if ($available <= 5)
                                $mOption .= "&nbsp;&nbsp;&nbsp;&nbsp;only $available left";
                        }
                        else
                        {
                            $disabled = 'disabled="disabled"';
                            $mOption = myHtmlEntities($option) . "&nbsp;&nbsp;&nbsp;&nbsp;sold out";
                        }
                        $mCarted = isAdministrator() && $carted > 0 ? " ($carted)" : '';
                        ?>
                        <option <?=$disabled?> value="<?=$i?>"><?=$mOption?><?=$mCarted?></option>
                        <?php
                    }
                    // Next use of carted is for total carted on add/sold-out button.
                    $carted = $totalCarted;
                    ?>
                </select>
                <?php
                $select = ob_get_clean();
                if (!$this->disableCart) echo $select;
            }
            else
            {
                // Only one (and blank) option available.
                $available = $availables[0];
                $carted = $carteds[0];
                $message = '';
                if ($available == 1)
                    $message = 'LAST ONE!';
                else if (($available > 1) && ($available <= 5))
                    $message = "only $available left";
                $mMessage = '<span style="font-size: smaller;">' . myHtmlEntities($message) . '</span>';
                if ($this->disableCart) $mMessage = '';
                ?>
                <span style="font-size: larger;"><?=$priceRange?>&nbsp;&nbsp;&nbsp;<?=$mMessage?></span><br>
                <?php
            }
            $buttonStyle = count($prices) == 1 ? 'style="border-radius: 20px;"' : '';
            $mCarted = isAdministrator() && $carted > 0 ? " ($carted)" : '';
            $allSoldOut = array_sum($qtyAvailable) == 0;
            if (!$allSoldOut && !$this->disableCart)
            {
                ?>
                <div class="add-button" <?=$buttonStyle?>>
                    <span>Add To Cart<?=$mCarted?></span>
                    <a href="?addToCart"><span style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;"></span></a>
                </div>
                <?php
            }
            else if (!$this->disableCart)
            {
                ?>
                <div class="add-button" <?=$buttonStyle?>><span>Sold Out<?=$mCarted?></span></div>
                <?php
            }
            ?>
        </div>
        <?php
        $files = $this->getFiles($pagePath);
        if (!empty($files))
        {
            $images = array();
            foreach ($files as $f)
            {
                $x = 300; $y = 300;
                $slide = "$urlRoot/images/rounded_square.svg";
                $thumb = $slide;
                if (($f['mimeType'] == 'image/jpeg')
                    || ($f['mimeType'] == 'image/pjpeg')
                    || ($f['mimeType'] == 'image/gif')
                    || ($f['mimeType'] == 'image/png')
                    || ($f['mimeType'] == 'image/svg+xml')
                    || ($f['mimeType'] == 'image/webp'))
                {
                    $mFileName = myHtmlEntities($f['fileName']);
                    if ($f['dimensions'] != '')
                        [$x, $y] = explode('x', $f['dimensions']);
                    $slide = $urlRoot . $pagePath . $mFileName;
                    $thumb = $slide . '?width=300&amp;height=300';
                }
                $images[] = array(
                    'slide' => $slide,
                    'thumb' => $thumb,
                    'caption' => '',
                    'height' => $y,
                    'width' => $x,
                    'url' => $slide
                );
            }
            if (isWritable())
                echo $this->showItemImages($images);
            else
                echo $this->showUniteGallery($images, 'justified');
        }
        return $this->tp->protect(trim(ob_get_clean())) . "\n" . trim($html);
    }

    private function showItemImages($slides)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $initialImgWidth = 120;
        ob_start();
        ?>
        <div class="wait"></div>
        <div class="store-item-images" data-initial-img-width="<?=$initialImgWidth?>">
            <div>
                <ul>
                <?php
                $sortHandle = count($slides) > 1
                    ? '<div class="sort-handle"><img src="' . $urlRoot . '/images/four-way-arrow.svg"></div>'
                    : '';
                foreach ($slides as $i => $s)
                {
                    $slide = $s['slide'];
                    $thumb = $s['thumb'];
                    $sw = $s['width'];
                    $sh = $s['height'];
                    $url = $s['url'];
                    ?>
                    <li data-index="<?=$i?>">
                        <a href="<?=$slide?>" target="_blank"><img alt="" class="item-image" data-width="<?=$sw?>" data-height="<?=$sh?>" src="<?=$thumb?>" style="width: <?=$initialImgWidth?>px;"></a>
                        <?=$sortHandle?>
                    </li>
                    <?php
                }
                ?>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showItems()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        addStyle("$urlRoot/toppings/Store/default.css");
        addJavaScript("$urlRoot/toppings/Store/Store.js");
        ob_start();
        $items = $this->getItems($pagePath);
        if (empty($items))
        {
            ?>
            <p>There are no items at this time.</p>
            <?php
            if (isWritable())
            {
                ?>
                <p><a href="?addItem">Add an Item</a></p>
                <?php
            }
            return ob_get_clean();
        }
        if (isWritable())
        {
            ?>
            <p><a href="?addItem">Add an Item</a></p>
            <?php
        }
        $images = array();
        foreach ($items as $k => $i)
        {
            $mode = $i['mode'];
            $pageName = $i['pageName'];
            $pageUri = $i['pageUri'];
            $itemUrl = $urlRoot . $pagePath . $pageUri . '/';
            $x = 300; $y = 300;
            $slide = "$urlRoot/images/rounded_square.svg";
            foreach ($i['files'] as $f)
            {
                // Use first image found and break.
                if (($f['mimeType'] == 'image/jpeg')
                    || ($f['mimeType'] == 'image/pjpeg')
                    || ($f['mimeType'] == 'image/gif')
                    || ($f['mimeType'] == 'image/png')
                    || ($f['mimeType'] == 'image/svg+xml')
                    || ($f['mimeType'] == 'image/webp'))
                {
                    $mFileName = myHtmlEntities($f['fileName']);
                    if ($f['dimensions'] != '')
                        [$x, $y] = explode('x', $f['dimensions']);
                    $slide = $pageUri . "/$mFileName?width=300&amp;height=300";
                    break;
                }
            }
            $priceRange = '';
            if (!empty($i['prices']))
            {
                $minPrice = 9999999; $maxPrice = 0;
                [$qtyAvailable, $dummy] = $GLOBALS['pizza']['cart']->quantitiesAvailable(
                    $pagePath . $i['pageUri'] . '/');
                $allSoldOut = array_sum($qtyAvailable) == 0;
                foreach ($i['prices'] as $p)
                {
                    if ($p < $minPrice) $minPrice = $p;
                    if ($p > $maxPrice) $maxPrice = $p;
                }
                $priceRange = '$' . $this->formatMoney($minPrice);
                if ($maxPrice > $minPrice) $priceRange .= ' - $' . $this->formatMoney($maxPrice);
                if ($allSoldOut && !$this->disableCart) $priceRange .= '&nbsp;&nbsp;&nbsp;&nbsp;sold out';
            }
            $description = '<span style="font-size: smaller; font-weight: bold;">' . $pageName . '</span>';
            if ($priceRange != '')
                $description .= '<div style="font-size: smaller;">' . $priceRange . '</div>';
            $images[] = array(
                'slide' => $slide,
                'height' => $y,
                'width' => $x,
                'caption' => $pageName,
                'description' => $description,
                'priceRange' => $priceRange,
                'url' => $itemUrl,
                'mode' => $mode
            );
        }
        echo $this->showItemsGrid($images);
        return trim(ob_get_clean());
    }

    private function showItemsGrid($slides)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="wait"></div>
        <div style="position: relative;">
        <ul class="store-items">
        <?php
        foreach ($slides as $i => $s)
        {
            $slide = $s['slide'];
            $sw = $s['width'];
            $sh = $s['height'];
            $caption = $s['caption'];
            $description = $s['description'];
            $priceRange = $s['priceRange'];
            $url = $s['url'];
            $mode = $s['mode'];
            $altText = myHtmlEntities($caption);
            if ($priceRange != '') $altText .= ", $priceRange";
            ?>
            <li data-index="<?=$i?>">
                <div class="item-content">
                    <a href="<?=$url?>"><span style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></span></a>
                    <img alt="<?=$altText?>" class="item-image" data-width="<?=$sw?>" data-height="<?=$sh?>" src="<?=$slide?>">
                    <div class="item-description">
                        <?=$description?>
                        <?php
                        if (substr($mode, 0, 1) == 'n')
                        {
                            ?>
                            <span style="color: red; font-size: smaller;">turned off</span>
                            <?php
                        }
                        ?>
                    </div>
                </div>
                <?php
                if (isWritable())
                {
                    ?>
                    <div class="sort-handle"><img src="<?=$urlRoot?>/images/four-way-arrow.svg"></div>
                    <?php
                }
                ?>
            </li>
            <?php
        }
        ?>
        </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showUniteGallery($slides, $mode = '')
    {
        $uniteDocRoot = $GLOBALS['pizza']['docRoot'] . '/toppings/Unite';
        $uniteUrlRoot = $GLOBALS['pizza']['urlRoot'] . '/toppings/Unite';
        if (is_file("$uniteDocRoot/css/unite-gallery.css"))
            addStyle("$uniteUrlRoot/css/unite-gallery.css");
        if (is_file("$uniteDocRoot/js/unitegallery.min.js"))
            addJavaScript("$uniteUrlRoot/js/unitegallery.min.js");
        if (is_file("$uniteDocRoot/themes/tiles/ug-theme-tiles.js"))
            addJavaScript("$uniteUrlRoot/themes/tiles/ug-theme-tiles.js");
        ob_start();
        ?>
        <div id="store-gallery" style="display:none;">
        <?php
        foreach ($slides as $s)
        {
            $slide = $s['slide'];
            $thumb = $s['thumb'];
            $caption = $s['caption'];
            $url = $s['url'];
            ?>
            <a href="<?=$url?>"><img alt="<?=$caption?>" src="<?=$thumb?>" data-image="<?=$slide?>" data-description="<?=$caption?>"></a>
            <?php
        }
        ?>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function(){
                jQuery("#store-gallery").unitegallery({
                    gallery_theme: "tiles",
                    tile_as_link: true,
                    tiles_col_width: 300,
                    tile_enable_textpanel: true,
                    tile_link_newpage: false,
                    tile_textpanel_always_on: true,
                    tile_textpanel_title_text_align: "center",
                    <?php
                    if ($mode == 'justified')
                    {
                        ?>
                        tiles_type: "justified",
                        gallery_width: <?=min(1000, count($slides) * 225)?>,
                        tiles_justified_row_height: 100,
                        tile_as_link: false,
                        tile_enable_textpanel: false,
                        <?php
                    }
                    ?>
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();
        $pagePath = currentPath();
        $parentPagePath = $this->cm->parentPath($pagePath);

        if ($v->submitted('cancel'))
        {
            unlockPage();
            // If temp page, delete it.
            if ($this->cm->isTempPage($pagePath))
            {
                $this->cm->deletePage($pagePath);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Store_' . $parentPagePath);
                sc($this->id);
                relocateNow($urlRoot . $parentPagePath);
            }
            sc($this->id);
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('save'))
        {
            if ($this->cm->isReservedPage($pagePath)) relocateNow($urlRoot . currentUri());
            // Delete files whose checkboxes were set.
            $deleteFileNames = isset($input['attachedFiles']) && is_array($input['attachedFiles'])
                ? $input['attachedFiles'] : array();
            foreach ($deleteFileNames as $f) $this->cm->deleteFile($pagePath, $f);

            // See if files are to be uploaded.
            $fileErrorOccurred = false;
            if (isset($_FILES['files']))
            {
                $this->files['value'] = $_FILES['files'];
                $fileCount = count($_FILES['files']['name']);
                for ($i = 0; $i < $fileCount; $i++)
                {
                    if ($_FILES['files']['name'][$i] == '') continue;
                    $fv = $v->checkUploadedFile('files', $i);
                    if ($v->error)
                    {
                        $this->files['errors'][$i] = $fv['error'];
                        $fileErrorOccurred = true;
                        $v->reset();
                        continue;
                    }
                    $f = $this->files['value'];
                    $fileName = $f['name'][$i];
                    // Make sure proposed file name doesn't collide with a page name.
                    if ($this->cm->hasPage($pagePath . $fileName))
                    {
                        $this->files['errors'][$i] = 'already exists';
                        $fileErrorOccurred = true;
                        continue;
                    }
                    $fileSize = $f['size'][$i];
                    $mimeType = $f['type'][$i];
                    $contents = file_get_contents($f['tmp_name'][$i]);
                    $this->cm->addFile($pagePath, $fileName, $fileSize, $mimeType, $contents);
                }
            }

            // Check the text fields.
            $this->title = $v->checkLength('title', 1, 200);
            if (!$v->error)
            {
                // Title's syntax is correct.  If the proposed title is
                // different from the current title, then check to see if it
                // is already used.
                $currentPageName = $GLOBALS['pizza']['page']['pageName'];
                $newPageName = $this->title['value'];
                $newPageUri = $this->cm->pageNameToPageUri($newPageName);
                // If newPageUri starts and ends with '-', e.g. '-hello-', reserved.
                if (preg_match('/^-.*-$/', $newPageUri))
                {
                    $this->title['error'] = 'reserved';
                    $v->error = true;
                }
                $newPagePath = $this->cm->parentPath($pagePath) . $newPageUri . '/';
                if (($currentPageName != $newPageName) && $this->cm->hasPage($newPagePath))
                {
                    $this->title['error'] = 'title already used';
                    $v->error = true;
                }
            }
            [$this->prices, $this->quantities, $this->options, $error] = $this->validatePricesAndOptions();
            if ($error) $v->error = true;
            $this->body = $v->checkLength('body', 2, 30000);

            if ($v->error || $fileErrorOccurred)
            {
                ss($this->id, $this);
                return;
            }

            // Don't do the edit if the body didn't change; otherwise a
            // revision would be recorded for only a name change.
            $oldBody = trim($GLOBALS['pizza']['page']['body']);
            $newBody = trim($this->body['value']);
            if ($oldBody != $newBody)
                $this->cm->editPage($pagePath, $this->body['value']);
            if (!$this->cm->renamePage($pagePath, $this->title['value']))
            {
                ss($this->id, $this);
                return;
            }
            // If moving from a temp page to a real page, change permissions.
            if ($this->cm->isTempPage($pagePath))
                $this->cm->changePermissions($newPagePath, 'yyrwr-r-');
            // Format prices and options for saving.
            $prices = array(); $quantities = array(); $options = array();
            foreach ($this->prices as $p) $prices[] = $p['value'];
            foreach ($this->quantities as $q) $quantities[] = $q['value'];
            foreach ($this->options as $s) $options[] = $s['value'];
            $this->s->set($newPagePath, 'Store_prices', $prices);
            $this->s->set($newPagePath, 'Store_quantities', $quantities);
            $this->s->set($newPagePath, 'Store_options', $options);
            unlockPage();
            sc($this->id);
            // Also clear the parent's state (i.e. tempPagePath)
            sc('Store_' . $parentPagePath);
            relocateNow($urlRoot . $newPagePath);
        }

        sleep(3);
    }

    private function validatePricesAndOptions()
    {
        $postPrices = pg('prices');
        $postQuantities = pg('quantities');
        $postOptions = pg('options');
        $prices = $this->prices;
        $quantities = $this->quantities;
        $options = $this->options;
        $error = false;
        // Sanity checks on prices and options.
        if (!is_array($postPrices) || !is_array($postQuantities) || !is_array($postOptions)
            || (count($postPrices) != count($postQuantities))
            || (count($postPrices) != count($postOptions)))
            return array($prices, $quantities, $options, $error);
        // The only required input is the first price. Quantities are optional.
        // Options are optional unless multiple prices are given.
        for ($i = 0; $i < count($postPrices); $i++)
        {
            $prices[$i] = $this->checkPrice($postPrices[$i])
                ? array('value' => $postPrices[$i], 'error' => '')
                : array('value' => '', 'error' => 'nope');
            $quantities[$i] = $this->checkQuantity($postQuantities[$i])
                ? array('value' => $postQuantities[$i], 'error' => '')
                : array('value' => '', 'error' => 'nope');
            $options[$i] = $this->checkOption($postOptions[$i])
                ? array('value' => $postOptions[$i], 'error' => '')
                : array('value' => '', 'error' => 'nope');
            if (($prices[$i]['error'] != '')
                || ($quantities[$i]['error'] != '')
                || ($options[$i]['error'] != ''))
                $error = true;
        }
        // Must have at least one price.
        if ($prices[0]['value'] == '')
        {
            $prices[0]['error'] = 'nope';
            $error = true;
        }
        if ($error) return array($prices, $quantities, $options, $error);
        // // Clear the arrays past a first blank price.
        // for ($i = 0; $i < count($prices); $i++)
        // {
        //     if ($prices[$i]['value'] == '')
        //     {
        //         for ($j = $i; $j < count($prices); $j++)
        //         {
        //             $prices[$j] = array('value' => '', 'error' => '');
        //             $quantities[$j] = array('value' => '', 'error' => '');
        //             $options[$j] = array('value' => '', 'error' => '');
        //         }
        //         break;
        //     }
        // }
        // If more than one price, options must be given.
        if (count(array_filter($prices, function($p) {return $p['value'] != '';})) > 1)
        {
            for ($i = 0; $i < count($prices); $i++)
            {
                if (($prices[$i]['value'] != '') && ($options[$i]['value'] == ''))
                {
                    $options[$i] = array('value' => '', 'error' => 'nope');
                    $error = true;
                }
            }
        }
        return array($prices, $quantities, $options, $error);
    }

    private function checkPrice($p)
    {
        if ($p == '') return true;
        if ((strlen($p) <= 15) && is_numeric($p) && (round($p, 2) == $p) && ($p > 0))
            return true;
        return false;
    }

    private function checkQuantity($q)
    {
        if ($q == '') return true;
        if ((strlen($q) <= 15) && is_numeric($q) && (intval($q) == $q) && ($q >= 0))
            return true;
        return false;
    }

    private function checkOption($s)
    {
        if (strlen($s) <= 15) return true;
        return false;
    }
}
?>
