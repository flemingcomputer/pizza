<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Album
{
    // Element attributes.

    // Form inputs.
    private $attachedFiles;
    private $files;

    private $cm;
    private $editing;
    private $id;
    private $s;
    private $tp;

    private $jQueryTwoColumnLoaded;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->s = $GLOBALS['pizza']['settings'];
        $this->tp = $GLOBALS['pizza']['toppings'];
        $this->jQueryTwoColumnLoaded = false;
    }

    function applyTopping($html)
    {
        // If this page is not an 'album', then only apply any
        // '[album]' elements and return.
        if ($GLOBALS['pizza']['page']['kind'] != 'album')
        {
            return preg_replace_callback(
                '/\[(album)([^]]+])/sU',
                array($this, '_applyTopping'),
                $html
            );
        }

        // This is an 'album' page.
        $this->id = 'Album_' . $GLOBALS['pizza']['pagePath'];
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
            return $this->tp->protect($this->showAlbumEditor());
        }

        if (isWritable() &&
            (hg('editPage') || $this->editing))
        {
            $this->editing = true;
            ss($this->id, $this);
            return $this->tp->protect($this->showAlbumEditor());
        }

        return $this->tp->protect($this->showAlbum());
    }

    function getFiles($pagePath)
    {
        $files = $this->cm->getFileInfo($pagePath);
        if ($files === false) $files = array();
        // Convert any old-style sortOrder to new-style.
        $oldSort = $this->s->get($pagePath, 'sortOrder');
        if ($oldSort !== false)
        {
            $fileNames = array_map(function($f) {return $f['fileName'];}, $files);
            $newSort = array();
            foreach ($oldSort as $f)
            {
                if (($k = array_search($f, $fileNames)) !== false)
                    $newSort[] = $files[$k]['id'];
            }
            $this->s->set($pagePath, 'Album_sortOrder', $newSort);
            $this->s->set($pagePath, 'sortOrder', '');
        }
        // Sort them in currently specified order.
        $sortIds = $this->s->get($pagePath, 'Album_sortOrder');
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

    private function _applyTopping($matches)
    {
        $e = substr($matches[0], 1, -1);
        $albumName = $this->tp->getString($e, 'album', '');
        $showCaptions = $this->tp->getString($e, 'show-captions', '');
        $theme = $this->tp->getString($e, 'theme', '');
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $mAlbumName = myHtmlEntities($albumName);
        $options = array();
        if ($theme != '') $options['theme'] = $theme;
        if ($showCaptions != '') $options['show-captions'] = $showCaptions;
        $albumPath = $pagePath . $this->cm->pageNameToPageUri($albumName) . '/';
        $p = $this->cm->getPage($albumPath);
        // If it's an album, show it and quit.
        if (($p !== false) && (($p['kind'] ?? '') == 'album'))
            return $this->tp->protect($this->showAlbum($albumName, $options));
        // Show other options if writable.
        if (!isWritable()) return '';
        if (($p === false) && (gge('createAlbum', '') != $albumName))
        {
            // Offer link to create the album.
            ob_start();
            ?>
            <p><a href="<?=$urlRoot?><?=$pagePath?>?createAlbum=<?=urlencode($albumName)?>">Create album "<?=$mAlbumName?>"</a></p>
            <?php
            return $this->tp->protect(ob_get_clean());
        }
        if (($p !== false) && ($p['kind'] ?? '') != 'album')
        {
            // A non-album page of the same name already exists.
            ob_start();
            ?>
            <p>Cannot create album "<?=$mAlbumName?>", page already exists.</p>
            <?php
            return $this->tp->protect(ob_get_clean());
        }
        if ($p === false)
        {
            // Create the album and reload page.
            $this->cm->addPage($pagePath, $albumName, '', 'album');
            relocateNow($urlRoot . $pagePath);
        }
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->attachedFiles = $o ? $o->attachedFiles : array('value' => '', 'error' => '');
        $this->editing = $o ? $o->editing : false;
        $this->files = $o ? $o->files : array('value' => '', 'errors' => array());
    }

    private function showAlbum($albumName = '', $options = array())
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        if ($albumName == '')
        {
            // This page is the album itself.
            $albumName = $GLOBALS['pizza']['page']['pageName'];
            $albumPath = $GLOBALS['pizza']['pagePath'];
        }
        else
        {
            // This page is the parent of albumName.
            $pagePath = $GLOBALS['pizza']['pagePath'];
            $albumUri = $this->cm->pageNameToPageUri($albumName);
            $albumPath = $pagePath . $albumUri . '/';
        }
        $mAlbumName = myHtmlEntities($albumName);
        $uAlbumName = urlencode($albumName);
        $files = $this->getFiles($albumPath);
        if ($files === false) $files = array();
        ob_start();
        if (empty($files))
        {
            ?>
            <p>
                Album "<?=$mAlbumName?>" has no photos.
                <?php
                if (isWritable())
                {
                    ?>
                    <a href="<?=$urlRoot?><?=$albumPath?>?editPage">Add Photos</a>
                    <?php
                }
                ?>
            </p>
            <?php
        }
        else
        {
            if (isWritable())
            {
                ?>
                <a href="<?=$urlRoot?><?=$albumPath?>?editPage">Edit Album "<?=$mAlbumName?>"</a>
                <?php
            }
            $theme = isset($options['theme']) ? $options['theme'] : '';
            if ($theme == 'column')
                echo $this->showGalleryTwoColumn($albumName, $albumPath, $files, $options);
            else
                echo $this->showGalleryUnite($albumName, $albumPath, $files, $options);
        }
        return trim(ob_get_clean());
    }

    private function showAlbumEditor()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $albumName = $GLOBALS['pizza']['page']['pageName'];
        $mAlbumName = myHtmlEntities($albumName);
        addStyle("$urlRoot/toppings/Album/default.css");
        addJavaScript("$urlRoot/toppings/Album/Album.js");
        ob_start();
        ?>
        <h1>Editing: <?=$mAlbumName?></h1>
        <div class="album-editor" id="album-editor">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" id="album-form" method="post">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <?php
                $files = $this->getFiles($pagePath);
                if ($files === false) $files = array();
                ?>
                <div id="gray-out" style="display: none; background-color: rgba(0,0,0,0.2); position: fixed; top: 0px; left: 0px; width: 100%; height: 100%; z-index: 11;"></div>
                <div class="attached-files">
                <ul class="album-files">
                <?php
                $n = count($files);
                for ($i = 0; $i < $n; $i++)
                {
                    $x = 300; $y = 300;
                    $f = $files[$i];
                    $fileName = $f['fileName'];
                    $fileSize = $f['fileSize'];
                    $mimeType = $f['mimeType'];
                    $text = $f['text'];
                    $mFileName = myHtmlEntities($fileName);
                    $mText = myHtmlEntities($text);
                    $imgUrl = "$urlRoot/images/image_error.png";
                    if (
                        ($mimeType == 'image/jpeg')
                        || ($mimeType == 'image/pjpeg')
                        || ($mimeType == 'image/gif')
                        || ($mimeType == 'image/png')
                        || ($mimeType == 'image/svg+xml')
                        || ($mimeType == 'image/webp'))
                    {
                        if ($f['dimensions'] != '')
                            list($x, $y) = explode('x', $f['dimensions']);
                        $imgUrl = $urlRoot . $pagePath . urlencode($fileName) . "?width=300&amp;height=300";
                    }
                    ?>
                    <li data-index="<?=$i?>">
                        <img alt="<?=$mFileName?>" data-width="<?=$x?>" data-height="<?=$y?>" src="<?=$imgUrl?>">
                        <textarea style="margin: 0; height: 4em;" name="caption[<?=$mFileName?>]" placeholder="Caption"><?=$mText?></textarea>
                        <div class="album-counter"><?=$i + 1?> of <?=$n?></div>
                        <div class="album-delete"><img data-file="<?=$mFileName?>" src="<?=$urlRoot?>/images/delete-x.svg"></div>
                        <div class="sort-handle"><img src="<?=$urlRoot?>/images/four-way-arrow.svg"></div>
                    </li>
                    <?php
                }
                $maxFileSize = ini_get('upload_max_filesize');
                $u = strtoupper(substr($maxFileSize, -1));
                if ($u == 'K') $maxFileSize = substr($maxFileSize, 0, -1) * 1024;
                else if ($u == 'M') $maxFileSize = substr($maxFileSize, 0, -1) * 1048576;
                else if ($u == 'G') $maxFileSize = substr($maxFileSize, 0, -1) * 1073741824;
                ?>
                </ul>
                </div>
                <?php
                addJavaScript("$urlRoot/js/filer.js");
                ?>
                <script type="text/javascript">
                    $(document).ready(function() {
                        $("#filer").pizzaFiler({
                            handler: '<?="$urlRoot/ajax/filer-handler"?>',
                            done: function() {
                                $('input[name="save"]').click();
                            },
                            listFiles: false,
                            maxFileSize: <?=$maxFileSize?>
                        });
                    });
                </script>
                <input name="MAX_FILE_SIZE" type="hidden" value="<?=$maxFileSize?>">
                <input id="filer" name="files[]" type="file" multiple="multiple">
                <br>
                <?php
                if (empty($files))
                {
                    ?>
                    <p>There are no photos in "<?=$mAlbumName?>".</p>
                    <?php
                }
                ?>
                <input class="submit" name="save" type="submit" value="Save">
                <input name="done" type="submit" value="Done">
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
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $parentPagePath = $this->cm->parentPath($pagePath);

        if ($v->submitted('done'))
        {
            sc($this->id);
            relocateNow($urlRoot . $parentPagePath);
        }

        if ($v->submitted('delete'))
        {
            // The textarea 'caption' has all the files, so use it to get
            // the current list.
            $files = array_keys(pg('caption'));
            $deleteMe = pg('delete');
            $k = array_search($deleteMe, $files);
            if ($k !== false)
                $this->cm->deleteFile($pagePath, $deleteMe);
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('save'))
        {
            // Update the captions.
            $captions = pg('caption');
            foreach ($captions as $f => $c)
            {
                if (strlen($c) > 1000) continue;
                $this->cm->updateFileText($pagePath, $f, $c);
            }
            if (!isset($_FILES['files']))
                relocateNow($urlRoot . $pagePath);
            // Upload new files.
            $this->files['value'] = $_FILES['files'];
            $fileErrorOccurred = false;
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
            ss($this->id, $this);
            relocateNow($urlRoot . $pagePath);
        }

        sleep(3);
    }

    private function showGalleryTwoColumn($albumName, $albumPath, $files, $options = array())
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $albumUri = $this->cm->pageNameToPageUri($albumName);
        $i = 0;
        $n = count($files);
        $loadedW = 500;
        if (!$this->jQueryTwoColumnLoaded)
        {
            $this->jQueryTwoColumnLoaded = true;
            ?>
            <script>
                $(function() {
                    // Dynamically scale album heights so browser knows where
                    // to land on anchored ids.  Also dynamically scale images
                    // to fill left column's actual width (50% of total width).
                    var leftW = parseInt($("div.two-column-left:first").width());
                    var loadedImgW = parseInt($("div.two-column-left:first img:first").attr("width"));
                    // Adjust image widths and heights, then album heights.
                    $("div.two-column-album").each(function(){
                        var albumH = parseInt($(this).css("height"));
                        var adjAlbumH = 0;
                        $(this).find("img").each(function(){
                            var loadedImgH = parseInt($(this).attr("height"));
                            var adjImgH = Math.ceil(leftW / loadedImgW * loadedImgH);
                            $(this).attr("width", leftW);
                            $(this).attr("height", adjImgH);
                            adjAlbumH += adjImgH + 30;
                        });
                        $(this).css("height", adjAlbumH + "px");
                    });
                });
            </script>
            <?php
        }
        // Calculate the loaded album height.
        $albumH = 0;
        foreach ($files as $f)
        {
            if ($f['dimensions'] != '')
            {
                list($x, $y) = explode('x', $f['dimensions']);
            }
            else
            {
                $x = 300;
                $y = 300;
            }
            $loadedH = ceil($y / $x * $loadedW);
            $albumH += $loadedH + 30;
        }
        ?>
        <div id="album-<?=$albumUri?>" class="two-column-album" style="height: <?=$albumH?>px;">
        <?php
        foreach ($files as $f)
        {
            $i++;
            $mimeType = $f['mimeType'];
            $x = 300; $y = 300;
            if (
                ($mimeType == 'image/jpeg')
                || ($mimeType == 'image/pjpeg')
                || ($mimeType == 'image/gif')
                || ($mimeType == 'image/png')
                || ($mimeType == 'image/svg+xml')
                || ($mimeType == 'image/webp'))
            {
                $slide = $urlRoot . $albumPath . urlencode($f['fileName']);
                $mAltText = myHtmlEntities($f['text']);
                $mCaption = myHtmlEntities($f['text']);
                if ($f['dimensions'] != '')
                    list($x, $y) = explode('x', $f['dimensions']);
            }
            else
            {
                $slide = "$urlRoot/images/image_error.png";
                $mAltText = "image error";
                $mCaption = "image error";
            }
            if ($mCaption != '') $mCaption = ' - ' . $mCaption;
            $loadedH = ceil($y / $x * $loadedW);
            ?>
            <div style="float: left; margin-bottom: 30px; width: 100%; clear: both;">
                <div class="two-column-left" style="float: left; font-size: 0; width: 50%;">
                    <a href="<?=$slide?>" style="font-size: 0;"><img src="<?=$slide?>?width=<?=$loadedW?>" border="0" alt="<?=$mAltText?>" width="<?=$loadedW?>" height="<?=$loadedH?>"></a>
                </div>
                <div style="float: left; width: 50%;">
                    <div style="font-size: smaller; margin-left: 10px;">
                        <?=$i?> of <?=$n?><?=$mCaption?>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
        </div>
        <?php
    }

    private function showGalleryUnite($albumName, $albumPath, $files, $options = array())
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $albumUri = $this->cm->pageNameToPageUri($albumName);
        $theme = isset($options['theme']) ? $options['theme'] : 'tiles';
        $uniteDocRoot = $GLOBALS['pizza']['docRoot'] . '/toppings/Unite';
        $uniteUrlRoot = $GLOBALS['pizza']['urlRoot'] . '/toppings/Unite';
        if (is_file("$uniteDocRoot/css/unite-gallery.css"))
            addStyle("$uniteUrlRoot/css/unite-gallery.css");
        if (is_file("$uniteDocRoot/js/unitegallery.min.js"))
            addJavaScript("$uniteUrlRoot/js/unitegallery.min.js");
        if (is_file("$uniteDocRoot/themes/$theme/ug-theme-$theme.css"))
            addStyle("$uniteUrlRoot/themes/$theme/ug-theme-$theme.css");
        if (is_file("$uniteDocRoot/themes/$theme/ug-theme-$theme.js"))
            addJavaScript("$uniteUrlRoot/themes/$theme/ug-theme-$theme.js");
        // Options for theme types.
        $showCaptions = isset($options['show-captions']) ? $options['show-captions'] : '';

        $themeOptions = array(
            'carousel' => array(
                'carousel_autoplay' => 'false',
                'slider_control_zoom' => 'false',
                'slider_scale_mode' => '"down"',
                'tile_enable_textpanel' => ($showCaptions == 'off') ? 'false' : 'true',
                'tile_textpanel_always_on' => ($showCaptions != 'on') ? 'false' : 'true',
                'tile_textpanel_title_text_align' => '"center"',
                'tile_width' => '200',
                'tile_height' => '200',
            ),
            'default' => array(
                'slider_control_zoom' => 'false',
                'slider_scale_mode' => '"down"',
                'tile_enable_textpanel' => ($showCaptions == 'off') ? 'false' : 'true',
                'tile_textpanel_title_text_align' => '"center"',
            ),
            'slider' => array(
                'slider_control_zoom' => 'false',
                'slider_scale_mode' => '"fill"',
                'slider_enable_textpanel' => ($showCaptions == 'off') ? 'false' : 'true',
                'slider_textpanel_always_on' => ($showCaptions != 'on') ? 'false' : 'true',
                'slider_textpanel_title_text_align' => '"center"',
            ),
            'tiles' => array(
                'slider_control_zoom' => 'false',
                'slider_scale_mode' => '"down"',
                'tile_enable_textpanel' => ($showCaptions == 'off') ? 'false' : 'true',
                'tile_textpanel_always_on' => ($showCaptions != 'on') ? 'false' : 'true',
                'tile_textpanel_title_text_align' => '"center"',
                'tiles_type' => '"justified"',
                'lightbox_type' => '"compact"',
            ),
        );
        $sw = '?width=300';
        $dw = '?width=300';
        if ($theme == 'carousel')
        {
            $sw = '?width=400';
            $dw = '?width=800';
        }
        else if ($theme == 'default')
        {
            $sw = '?width=144';
            $dw = '?width=800';
        }
        else if ($theme == 'slider')
        {
            $sw = '?width=400';
            $dw = '?width=800';
        }
        else if ($theme == 'tiles')
        {
            $sw = '?width=480';
            $dw = '?width=800';
        }
        ob_start();
        ?>
        <div id="album-<?=$albumUri?>">
        <?php
        foreach ($files as $f)
        {
            $mimeType = $f['mimeType'];
            if (
                ($mimeType == 'image/jpeg')
                || ($mimeType == 'image/pjpeg')
                || ($mimeType == 'image/gif')
                || ($mimeType == 'image/png')
                || ($mimeType == 'image/svg+xml')
                || ($mimeType == 'image/webp'))
            {
                $slide = $urlRoot . $albumPath . urlencode($f['fileName']);
                $mCaption = myHtmlEntities($f['text']);
            }
            else {
                $slide = "$urlRoot/images/image_error.png";
                $mCaption = "image error";
            }
            ?>
            <img alt="<?=$mCaption?>" src="<?=$slide . $sw?>" data-image="<?=$slide . $dw?>" data-description="<?=$mCaption?>">
            <?php
        }
        ?>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function(){
                jQuery("#album-<?=$albumUri?>").unitegallery({
                    <?php
                    foreach ($themeOptions[$theme] as $k => $v)
                        echo "$k: $v,\n";
                    ?>
                });
            });

            // Unite doesn't handle arrow keys & escape key when multiple albums
            // are on the page. This code makes those keys work for each album.
            jQuery(document).ready(function() {
                // One-time add, not for every album.
                if ($("#unite-keys-override").length != 0) return;
                $("#album-<?=$albumUri?>").append('<div id="unite-keys-override"></div>');

                // Beat Unite to the punch on preventing its default key stuff.
                window.addEventListener('keydown', function(e) {
                    var key = e.key || e.keyCode;
                    if (key === "ArrowRight" || key === 39) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                    }
                    else if (key === "ArrowLeft" || key === 37) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                    }
                    else if (key === "Escape" || key === 27) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                    }
                }, true); // Captures event forcing this to run first.

                // Recode how left & right arrows & escape work.
                // keydown doesn't work as well as keyup.
                jQuery(window).on("keyup", function(e) {
                    var activeLightbox = $(".ug-lightbox:visible");
                    if (activeLightbox.length === 0) return;
                    // activeLightbox.focus();
                    var key = e.key || e.keyCode;
                    if (key === "ArrowRight" || key === 39) {
                        activeLightbox.find(".ug-lightbox-arrow-right").trigger("click");
                    }
                    else if (key === "ArrowLeft" || key === 37) {
                        activeLightbox.find(".ug-lightbox-arrow-left").trigger("click");
                    }
                    else if (key === "Escape" || key === 27) {
                        activeLightbox.find(".ug-lightbox-button-close").trigger("click");
                    }
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
?>
