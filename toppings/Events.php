<?php
// Copyright 2020 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Events
{
    // Element attributes.
    public $paypalSelector;
    public $sortBy;
    public $sortDirection;

    // Form inputs for when editing an 'event' page.
    private $startDate;
    private $endDate;
    private $attachedFiles;
    private $body;
    private $deposit;
    private $files;
    private $isOpen;
    private $optionInstructions;
    private $option1;
    private $option2;
    private $option3;
    private $option4;
    private $option5;
    private $title;

    // Form inputs when registering for an event.
    private $captcha;
    private $email;
    private $name;
    private $phone;
    private $optionChosen;
    private $notes;

    private $cm;
    private $editing;
    private $id;
    private $preview;
    private $tempPagePath;
    private $tp;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->tp = $GLOBALS['pizza']['toppings'];
    }

    function applyTopping($html)
    {
        // If this page is not an 'event', then only apply any
        // '[events]' element and return.
        if ($GLOBALS['pizza']['page']['kind'] != 'event')
        {
            return preg_replace_callback(
                '/\[(events)(]|\s+.*]|,.*])/sU',
                array($this, '_applyTopping'),
                $html
            );
        }

        // This is an 'event' page.
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        // Get parent's [events] options.
        $parentPagePath = $this->cm->parentPath($pagePath);
        $pp = $this->cm->getPage($parentPagePath);
        preg_match('/\[(events)(]|\s+.*]|,.*])/sU', $pp['body'], $matches);
        $e = substr($matches[0], 1, -1);
        $this->paypalSelector = $this->tp->getString($e, 'paypal-selector', '');

        $this->id = "Events_$pagePath";
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
            return $this->tp->protect($this->showAddEvent());
        }

        if (hg('editPage') || $this->editing)
        {
            $this->editing = true;
            ss($this->id, $this);
            return $this->tp->protect($this->showAddEvent());
        }

        if (hg('register') && $this->isOpen)
        {
            return $this->tp->protect($this->showRegisterStep1());
        }

        if (hg('register2') && hs('cart-transaction-id'))
        {
            $this->showRegisterStep2();
        }

        if (hg('successPayPal')
            && hs('cart-transaction-id') && hg('transactionId')
            && (sg('cart-transaction-id') == gg('transactionId'))
            && hg('email'))
        {
            sc('cart-transaction-id');
            $this->showThankYou();
        }

        return $this->showEvent($html);
    }

    private function _applyTopping($matches)
    {
        $e = substr($matches[0], 1, -1);
        $this->sortDirection = $this->tp->getString($e, 'direction', ''); // up or down
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $this->id = "Events_$pagePath";
        $o = sge($this->id, false);
        $this->tempPagePath = $o ? $o->tempPagePath : '';

        if (isWritable() &&
            (hg('addEvent') || $this->cm->hasPage($this->tempPagePath)))
        {
            // Either adding just now or started earlier and need to finish.
            if (!$this->cm->hasPage($this->tempPagePath))
            {
                $this->tempPagePath = $this->cm->addTempPage($pagePath, 'event', 'y');
                ss($this->id, $this);
            }
            relocateNow($urlRoot . $this->tempPagePath . '?editPage');
        }

        return $this->tp->protect($this->showEventList());
    }

    private function friendlyDates($startDate, $endDate)
    {
        $dates = '';
        if (($startDate != '') && ($endDate != ''))
        {
            if ($endDate == $startDate)
            {
                $dates = date('F j, Y', strtotime($startDate));
            }
            else
            {
                $y1 = date('Y', strtotime($startDate));
                $y2 = date('Y', strtotime($endDate));
                if ($y1 == $y2)
                    $dates = date('F j', strtotime($startDate));
                else
                    $dates = date('F j, Y', strtotime($startDate));
                $m1 = date('F', strtotime($startDate));
                $m2 = date('F', strtotime($endDate));
                if (($y1 == $y2) && ($m1 == $m2))
                    $dates .= ' - ' . date('j, Y', strtotime($endDate));
                else
                    $dates .= ' - ' . date('F j, Y', strtotime($endDate));
            }
        }
        return $dates;
    }

    private function initThis()
    {
        $settings = $GLOBALS['pizza']['page']['settings'];
        $o = sge($this->id, false);
        $this->startDate = $o ? $o->startDate : array(
            'value' => isset($settings['startDate']) ? $settings['startDate'] : '',
            'error' => ''
        );
        $this->endDate = $o ? $o->endDate : array(
            'value' => isset($settings['endDate']) ? $settings['endDate'] : '',
            'error' => ''
        );
        $this->attachedFiles = $o ? $o->attachedFiles : array('value' => '', 'error' => '');
        $this->body = $o ? $o->body : array(
            'value' => $GLOBALS['pizza']['page']['body'],
            'error' => ''
        );
        $this->deposit = $o ? $o->deposit : array(
            'value' => isset($settings['deposit']) ? $settings['deposit'] : '',
            'error' => ''
        );
        $this->files = $o ? $o->files : array('value' => '', 'errors' => array());
        $this->isOpen = $o ? $o->isOpen : ($settings['isOpen'] ?? false);
        $this->optionInstructions = $o ? $o->optionInstructions : array(
            'value' => isset($settings['optionInstructions']) ? $settings['optionInstructions'] : '',
            'error' => ''
        );
        $this->option1 = $o ? $o->option1 : array(
            'value' => isset($settings['option1']) ? $settings['option1'] : '',
            'error' => ''
        );
        $this->option2 = $o ? $o->option2 : array(
            'value' => isset($settings['option2']) ? $settings['option2'] : '',
            'error' => ''
        );
        $this->option3 = $o ? $o->option3 : array(
            'value' => isset($settings['option3']) ? $settings['option3'] : '',
            'error' => ''
        );
        $this->option4 = $o ? $o->option4 : array(
            'value' => isset($settings['option4']) ? $settings['option4'] : '',
            'error' => ''
        );
        $this->option5 = $o ? $o->option5 : array(
            'value' => isset($settings['option5']) ? $settings['option5'] : '',
            'error' => ''
        );
        $this->title = $o ? $o->title : array(
            'value' => !$this->cm->isTempPage($GLOBALS['pizza']['pagePath'])
                ? $GLOBALS['pizza']['page']['pageName'] : '',
            'error' => ''
        );

        $this->captcha = $o ? $o->captcha : array('value' => '', 'error' => '');
        $this->email = $o ? $o->email : array('value' => '', 'error' => '');
        $this->name = $o ? $o->name : array('value' => '', 'error' => '');
        $this->phone = $o ? $o->phone : array('value' => '', 'error' => '');
        $this->optionChosen = $o ? $o->optionChosen : array('value' => '', 'error' => '');
        $this->notes = $o ? $o->notes : array('value' => '', 'error' => '');

        $this->editing = $o ? $o->editing : false;
        $this->preview = $o ? $o->preview : false;
    }

    private function sendMail($replyTo, $to, $subject, $body)
    {
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setReplyTo($replyTo);
        if (($GLOBALS['pizza']['config']['smtpAuth'] ?? false) === true)
        {
            // Using, for example, Gmail's SMTP service.
            $mt->setFrom($GLOBALS['pizza']['config']['smtpUsername']);
        }
        else
        {
            // Using localhost.
            $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        }
        $mt->setTo($to);
        $mt->setSubject($subject);
        $mt->setHtmlBody($body);
        return $mt->send();
    }

    private function showAddEvent()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <script>
            $(function() {
                var startDateSelected = false;
                var currentDate = new Date();
                $("#startDate").datepicker({
                    defaultDate: "+1w",
                    changeMonth: false,
                    numberOfMonths: 1,
                    onSelect: function(selectedDate) {
                        startDateSelected = true;
                        var minEndDate = new Date(selectedDate);
                        // minEndDate.setDate(minEndDate.getDate() + 1);
                        $("#endDate").datepicker("option", "minDate", minEndDate);
                    }
                });
                $("#endDate").datepicker({
                    defaultDate: "+1w",
                    changeMonth: false,
                    numberOfMonths: 1,
                    beforeShowDay: function(date) {
                        return [startDateSelected];
                    },
                });
            });
        </script>
        <h1>Add Event</h1>
        <div class="page-editor" id="page-editor">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <?php
                if ($this->preview)
                {
                    $body = $this->tp->applyToppings($this->body['value'], get_class($this));
                    // $body = $this->tp->deactivateForms($body);
                    ?>
                    <div class="preview">
                        <h1><?=myHtmlEntities($this->title['value'])?></h1>
                        <div><?=$body?></div>

                        <hr style="clear: both;" />
                    </div>
                    <?php
                    $this->preview = false;
                }
                ?>

                <div>
                    <?php
                    $fileInfo = $this->cm->getFileInfo($pagePath);
                    ?>
                    <img src="<?=$urlRoot?>/images/paperclip.gif" border="0" width="15" height="15" alt="paperclip" />
                    Attached Files:
                    <?php
                    if ($fileInfo === false) echo '<br>';
                    else
                    {
                        ?>
                        <input name="deleteSelected" type="submit" value="Delete Selected" />
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
                                    list($x, $y) = explode('x', $f['dimensions']);
                                    $fileDetails = "$fileSize, {$x}x{$y}";
                                }
                                else
                                {
                                    $fileDetails = "$fileSize";
                                }
                                $imgUrl = $urlRoot . $pagePath . $mFileName . '?width=48&amp;height=16';
                            }
                            else if (substr($f['mimeType'], -3, 3) == 'pdf')
                                $imgUrl = "$urlRoot/images/mime/pdf.gif";
                            else if (substr($f['mimeType'], 0, 5) == 'audio')
                                $imgUrl = "$urlRoot/images/mime/audio.gif";
                            ?>
                            <input name="attachedFiles[]" type="checkbox" value="<?=$mFileName?>" />
                            <img src="<?=$imgUrl?>" border="0" alt="<?=$mFileName?>" />
                            <a href="<?=$urlRoot?><?=$pagePath?><?=$mFileName?>"><?=$mFileName?></a> (<?=$fileDetails?>)<br>
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
                    <input name="MAX_FILE_SIZE" type="hidden" value="<?=$maxFileSize?>" />
                    <input name="files[]" type="file" multiple="multiple" />
                    <input name="upload" type="submit" value="Upload" />
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

                <div>
                    <?php
                    $class = $this->title['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="title" placeholder="Title" type="text" value="<?=myHtmlEntities($this->title['value'])?>" />
                    <?php
                    if ($this->title['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->title['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div class="events">
                    <?php
                    $class = $this->startDate['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> id="startDate" name="startDate" placeholder="Start Date" type="text" value="<?=myHtmlEntities($this->startDate['value'])?>" />

                    <?php
                    $class = $this->endDate['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> id="endDate" name="endDate" placeholder="End Date" type="text" value="<?=myHtmlEntities($this->endDate['value'])?>" />
                    <?php
                    if ($this->startDate['error'] != '')
                    {
                        ?>
                        <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->startDate['error'])?>↑</div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->deposit['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    $<input <?=$class?> name="deposit" placeholder="Deposit" style="width: 5em;" type="text" value="<?=myHtmlEntities($this->deposit['value'])?>" />
                    <?php
                    if ($this->deposit['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->deposit['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $isRegistrationOpen = $this->isOpen ? 'checked="checked"' : '';
                    ?>
                    <input <?=$class?> name="is-open" type="checkbox" <?=$isRegistrationOpen?> /> Registration Open
                </div>

                <div>
                    <?php
                    $class = $this->option1['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <p>Optionally provide instructions for up to 5 options below from which a registrant will choose 1:</p>

                    <input class="event-option" name="optionInstructions" placeholder="Options Instructions" type="text" value="<?=myHtmlEntities($this->optionInstructions['value'])?>" />
                    <?php
                    if ($this->optionInstructions['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->optionInstructions['error'])?></span>
                        <br>
                        <?php
                    }
                    ?>

                    <input class="event-option" name="option1" placeholder="Option 1 Text" type="text" value="<?=myHtmlEntities($this->option1['value'])?>" />
                    <?php
                    if ($this->option1['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->option1['error'])?></span>
                        <br>
                        <?php
                    }
                    ?>

                    <input class="event-option" name="option2" placeholder="Option 2 Text" type="text" value="<?=myHtmlEntities($this->option2['value'])?>" />
                    <?php
                    if ($this->option2['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->option2['error'])?></span>
                        <br>
                        <?php
                    }
                    ?>

                    <input class="event-option" name="option3" placeholder="Option 3 Text" type="text" value="<?=myHtmlEntities($this->option3['value'])?>" />
                    <?php
                    if ($this->option3['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->option3['error'])?></span>
                        <br>
                        <?php
                    }
                    ?>

                    <input class="event-option" name="option4" placeholder="Option 4 Text" type="text" value="<?=myHtmlEntities($this->option4['value'])?>" />
                    <?php
                    if ($this->option4['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->option4['error'])?></span>
                        <br>
                        <?php
                    }
                    ?>

                    <input class="event-option" name="option5" placeholder="Option 5 Text" type="text" value="<?=myHtmlEntities($this->option5['value'])?>" />
                    <?php
                    if ($this->option5['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->option5['error'])?></span>
                        <br>
                        <?php
                    }
                    ?>
                </div>

                <p>Provide your event details below:</p>
                <textarea name="body" placeholder="Body"><?=myHtmlEntities($this->body['value'])?></textarea>
                <?php
                if ($this->body['error'] != '')
                {
                    ?>
                    <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->body['error'])?>↑</div>
                    <?php
                }
                ?>

                <div>
                    <input class="submit" name="save" type="submit" value="Save" />
                    <input class="submit" name="preview" type="submit" value="Preview" />
                    <input class="submit" name="cancel" type="submit" value="Cancel" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showEvent($html)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        $mTitle = myHtmlEntities($this->title['value']);
        $startDate = date('Y-m-d', strtotime($this->startDate['value']));
        $endDate = date('Y-m-d', strtotime($this->endDate['value']));
        $mDates = myHtmlEntities($this->friendlyDates($startDate, $endDate));
        $currentDate = date('Y-m-d');
        if (($currentDate <= $startDate) && $this->isOpen)
        {
            echo <<<P
<h1>$mTitle</h1>
<a class="book-now-link register-now-link" href="?register">$mDates<br>REGISTER NOW</a>
P;
        }
        else
        {
            echo <<<P
<h1>$mTitle</h1>
<span class="book-now-link register-now-link">$mDates<br>REGISTRATION CLOSED</span>
P;
        }
        return $this->tp->protect(ob_get_clean()) . "\n\n" . trim($html);
    }

    private function showEventList()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $settings = $GLOBALS['pizza']['settings'];
        ob_start();
        $events = $this->cm->getChildren($pagePath, 'event');
        // Augment events with startDate field for sorting.
        foreach ($events as $k => $e)
        {
            $pageUri = $e['pageUri'];
            $startDate = $settings->get("{$pagePath}{$pageUri}/", 'startDate', '');
            $events[$k]['startDate'] = $startDate;
        }
        $key = 'startDate';
        $direction = -1;
        uasort($events,
            function ($a, $b) use($key, $direction)
            {
                if ($a[$key] < $b[$key]) return -$direction;
                if ($a[$key] > $b[$key]) return $direction;
                return 0;
            }
        );
        if (count($events) == 0)
        {
            echo <<<P
<p>There are no entries at this time.</p>
P;
            if (isWritable())
            {
                echo <<<P

<p><a href="$urlRoot$pagePath?addEvent">Add an Event</a></p>
P;
            }
            return ob_get_clean();
        }
        if (isWritable())
            echo <<<P
<p><a href="$urlRoot$pagePath?addEvent">Add an Event</a></p>

P;
        foreach ($events as $a)
        {
            $pageName = $a['pageName'];
            $pageUri = $a['pageUri'];
            $created = $a['created'];
            $startDate = $settings->get("{$pagePath}{$pageUri}/", 'startDate', '');
            $endDate = $settings->get("{$pagePath}{$pageUri}/", 'endDate', '');
            $mDates = myHtmlEntities($this->friendlyDates($startDate, $endDate));
            $mPageName = myHtmlEntities($pageName);
            $mode = '';
            if (substr($a['mode'], 0, 1) == 'n')
                $mode = ' <span style="color: red;">OFF</span>';
            echo <<<P
<p>
<a href="$urlRoot$pagePath$pageUri/">$mPageName</a><br>
<span style="font-size: smaller;">$mDates{$mode}</span>
</p>

P;
        }
        return rtrim(ob_get_clean());
    }

    private function showRegisterStep1()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $pageName = $GLOBALS['pizza']['page']['pageName'];
        $mEventName = myHtmlEntities($pageName);
        // Get event start/end dates and deposit required.
        $settings = $GLOBALS['pizza']['page']['settings'];
        $startDate = isset($settings['startDate']) ? $settings['startDate'] : '';
        $endDate = isset($settings['endDate']) ? $settings['endDate'] : '';
        $mDates = myHtmlEntities($this->friendlyDates($startDate, $endDate));
        $deposit = isset($settings['deposit']) ? $settings['deposit'] : '';
        if ($deposit == 0) $deposit = '';
        $optionInstructions = isset($settings['optionInstructions']) ? $settings['optionInstructions'] : '';
        $option1 = isset($settings['option1']) ? $settings['option1'] : '';
        $option2 = isset($settings['option2']) ? $settings['option2'] : '';
        $option3 = isset($settings['option3']) ? $settings['option3'] : '';
        $option4 = isset($settings['option4']) ? $settings['option4'] : '';
        $option5 = isset($settings['option5']) ? $settings['option5'] : '';
        $mOptionInstructions = myHtmlEntities($optionInstructions);
        $options = array();
        if ($option1 != '') $options[1] = myHtmlEntities($option1);
        if ($option2 != '') $options[2] = myHtmlEntities($option2);
        if ($option3 != '') $options[3] = myHtmlEntities($option3);
        if ($option4 != '') $options[4] = myHtmlEntities($option4);
        if ($option5 != '') $options[5] = myHtmlEntities($option5);
        ob_start();
        ?>
        <h1>Register for <?=$mEventName?></h1>
        <p>
            <?=$mDates?>
        </p>
        <p>
            <?php
            if ($deposit != '')
            {
                ?>
                A non-refundable deposit of $<?=$deposit?> will be required.
                <?php
            }
            ?>
            Please provide the information below to continue.
        </p>
        <div class="event-register" id="event-register">
            <form action="<?=$urlRoot?><?=$pagePath?>#event-register" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <div>
                    <?php
                    $class = $this->name['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="name" placeholder="Your Name" type="text" value="<?=myHtmlEntities($this->name['value'])?>" />
                    <?php
                    if ($this->name['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->name['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->email['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="email" placeholder="Your Email" type="text" value="<?=myHtmlEntities($this->email['value'])?>" />
                    <?php
                    if ($this->email['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->email['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->phone['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="phone" placeholder="Your Phone Number" type="text" value="<?=myHtmlEntities($this->phone['value'])?>" />
                    <?php
                    if ($this->phone['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->phone['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <?php
                if (!empty($options))
                {
                    $class = $this->optionChosen['error'] != '' ? 'class="error"' : '';
                    ?>
                    <div>
                    <input name="optionsInPlay" type="hidden" value="true" />
                    <p <?=$class?>><?=$mOptionInstructions?></p>

                    <?php
                    foreach ($options as $k => $o)
                    {
                        $chosen = $this->optionChosen['value'] == $k ? 'checked="checked"' : '';
                        ?>
                        <input <?=$chosen?> name="optionChosen" type="radio" value="<?=$k?>"> <?=$o?><br>
                        <?php
                    }
                    ?>
                    </div>
                    <br>
                    <?php
                }
                ?>

                <div>
                    <textarea style="height: 4em;" name="notes" placeholder="Notes"><?=myHtmlEntities($this->notes['value'])?></textarea>
                    <?php
                    if ($this->notes['error'] != '')
                    {
                        ?>
                        <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->notes['error'])?>↑</div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <img alt="CAPTCHA" class="captcha" src="<?=$urlRoot?>/captcha-code"/>
                </div>
                <div>
                    <?php
                    $class = $this->captcha['error'] != '' ? 'class="captcha error-placeholder"' : 'class="captcha"';
                    ?>
                    <input autocomplete="off" <?=$class?> name="captcha" placeholder="Enter Code" type="text" value="" />
                    <?php
                    if ($this->captcha['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->captcha['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <input name="sendRegistration1" type="submit" value="Next" />
                    <?php
                    if (previousPath() != currentPath())
                    {
                        ?>
                        <input name="cancel" type="submit" value="Cancel" />
                        <?php
                    }
                    ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showRegisterStep2()
    {
        $pageName = $GLOBALS['pizza']['page']['pageName'];
        $mEventName = myHtmlEntities($pageName);
        $providedName = $this->name['value'];
        $providedEmail = $this->email['value'];
        $providedPhone = $this->phone['value'];
        $providedNotes = $this->notes['value'];
        $mProvidedName = myHtmlEntities($providedName);
        $mProvidedEmail = myHtmlEntities($providedEmail);
        $mProvidedPhone = myHtmlEntities($providedPhone);
        $mProvidedNotes = myHtmlEntities($providedNotes);
        // Get event start/end dates and deposit required.
        $settings = $GLOBALS['pizza']['page']['settings'];
        $startDate = $settings['startDate'] ?? '';
        $endDate = $settings['endDate'] ?? '';
        $deposit = $settings['deposit'] ?? '';
        if ($deposit == 0) $deposit = '';
        $mDates = myHtmlEntities($this->friendlyDates($startDate, $endDate));
        ob_start();
        ?>
        <h1 id="review">Almost Done</h1>
        <p>
            Review your information and select a payment option to complete your registration.
        </p>
        <p>
            Event: <?=$mEventName?><br>
            Dates: <?=$mDates?><br>
            Deposit: $<?=$deposit?><br>
            <br>
            Name: <?=$mProvidedName?><br>
            Email: <?=$mProvidedEmail?><br>
            Phone: <?=$mProvidedPhone?>
            <?php
            if ($this->optionChosen['value'] > 0)
            {
                $k = $this->optionChosen['value'];
                $mOption = myHtmlEntities($settings["option$k"]);
                ?>
                <br><br>
                Option Chosen:<br>
                <?=$mOption?>
                <?php
            }
            ?>
            <?php
            if ($providedNotes != '')
            {
                ?>
                <br><br>
                Notes:<br>
                <?=$mProvidedNotes?>
                <?php
            }
            ?>
        </p>
        <?php
        $html = ob_get_clean();
        $eventName = $GLOBALS['pizza']['page']['pageName'];
        // Get event start/end dates and deposit required.
        $settings = $GLOBALS['pizza']['page']['settings'];
        $startDate = $settings['startDate'] ?? '';
        $endDate = $settings['endDate'] ?? '';
        $dates = $this->friendlyDates($startDate, $endDate);
        $deposit = $settings['deposit'] ?? '';
        $name = 'Deposit for ' . $eventName . ' (' . $dates . ')';
        $item = array(
            'item-name' => $name,
            'item-description' => '',
            'item-price' => $deposit,
            'item-quantity' => 1
        );
        $cart = array();
        $cart[] = $item;
        require_once('PayPal.php');
        $pp = new PayPal();
        $html .= $pp->getPayPalButtonV2($this->paypalSelector, sg('cart-transaction-id'), $cart, $deposit, 0, 0, 0);
        showFinal($html);
    }

    private function showThankYou()
    {
        $pageName = $GLOBALS['pizza']['page']['pageName'];
        $mEventName = myHtmlEntities($pageName);
        $settings = $GLOBALS['pizza']['page']['settings'];
        $startDate = isset($settings['startDate']) ? $settings['startDate'] : '';
        $endDate = isset($settings['endDate']) ? $settings['endDate'] : '';
        $deposit = isset($settings['deposit']) ? $settings['deposit'] : '';
        $mDates = myHtmlEntities($this->friendlyDates($startDate, $endDate));

        $providedName = $this->name['value'];
        $providedEmail = $this->email['value'];
        $providedPhone = $this->phone['value'];
        $providedNotes = $this->notes['value'];
        $mProvidedName = myHtmlEntities($providedName);
        $mProvidedEmail = myHtmlEntities($providedEmail);
        $mProvidedPhone = myHtmlEntities($providedPhone);
        $mProvidedNotes = myHtmlEntities($providedNotes);

        $mOption = '';
        if (isset($this->optionChosen))
        {
            $k = $this->optionChosen['value'];
            $mOption = myHtmlEntities($settings["option$k"]);
        }

        $custEmail = substr(gg('email'), 0, 254);
        $firstName = substr(gg('firstName'), 0, 254);
        $lastName = substr(gg('lastName'), 0, 254);
        $shippingLine1 = substr(gge('address1', ''), 0, 254);
        $shippingLine2 = substr(gge('address2', ''), 0, 254);
        $shippingCity = substr(gge('city', ''), 0, 254);
        $shippingState = substr(gge('state', ''), 0, 254);
        $shippingZip = substr(gge('zip', ''), 0, 254);
        $shippingCountry = substr(gge('country', ''), 0, 254);
        $mCustEmail = myHtmlEntities($custEmail);
        $mFirstName = myHtmlEntities($firstName);
        $mLastName = myHtmlEntities($lastName);
        $mShippingLine1 = myHtmlEntities($shippingLine1);
        $mShippingLine2 = $shippingLine2 != 'undefined' ? '<br>' . myHtmlEntities($shippingLine2) : '';
        $mShippingCity = myHtmlEntities($shippingCity);
        $mShippingState = myHtmlEntities($shippingState);
        $mShippingZip = myHtmlEntities($shippingZip);
        $mShippingCountry = $shippingCountry != 'US' ? '<br>' . myHtmlEntities($shippingCountry) : '';
        $siteName = $GLOBALS['pizza']['config']['siteName'];

        if (($deposit == '') || ($deposit == 0))
        {
            $depositHtml = <<<DEPOSIT_HTML
            <p>
            Event: $mEventName
            <br>Dates: $mDates
            </p>
            DEPOSIT_HTML;
        }
        else
        {
            $depositHtml = <<<DEPOSIT_HTML
            <p>
            Event: $mEventName
            <br>Dates: $mDates
            <br>Deposit: \$$deposit
            </p>
            DEPOSIT_HTML;
        }

        $providedHtml = <<<PROVIDED_HTML
        <p>
        Provided Information:
        <br>Name: $mProvidedName
        <br>Email: $mProvidedEmail
        <br>Phone: $mProvidedPhone
        </p>
        PROVIDED_HTML;

        $optionHtml = '';
        if ($mOption != '')
        {
            $optionHtml = <<<OPTION_HTML
            <p>
            Option Chosen:<br>
            $mOption
            </p>
            OPTION_HTML;
        }

        $notesHtml = '';
        if ($mProvidedNotes != '')
        {
            $notesHtml = <<<NOTES_HTML
            <p>
            Notes:<br>
            $mProvidedNotes
            </p>
            NOTES_HTML;
        }

        if ($custEmail == '') // No PayPal info, free event
        {
            $paypalHtml = '';
        }
        else if ($shippingLine1 == '') // Digital goods, no shipping address given.
        {
            $paypalHtml = <<<PAYPAL_HTML
            <p>
            PayPal Information:
            <br>$mFirstName $mLastName ($mCustEmail)
            </p>
            PAYPAL_HTML;
        }
        else
        {
            $paypalHtml = <<<PAYPAL_HTML
            <p>
            PayPal Information:
            <br>$mFirstName $mLastName ($mCustEmail)
            <br>$mShippingLine1
            $mShippingLine2
            <br>$mShippingCity, $mShippingState $mShippingZip
            $mShippingCountry
            </p>
            PAYPAL_HTML;
        }

        if ((strtolower($providedEmail) == strtolower($custEmail))
            || ($custEmail == ''))
        {
            $webHtml = <<<WEB_HTML
<h1 id="thank-you">Thank you, your registration is complete!</h1>
<p>
A confirmation has been emailed to:
<br>$mProvidedName ($mProvidedEmail)
</p>
<h2>Registration Summary</h2>
$depositHtml
$providedHtml
$optionHtml
$notesHtml
$paypalHtml
WEB_HTML;
        }
        else
        {
            $webHtml = <<<WEB_HTML
<h1 id="thank-you">Thank you, your registration is complete!</h1>
<p>
Confirmation emails have been sent to:
<br>$mProvidedName ($mProvidedEmail)
<br>$mFirstName $mLastName ($mCustEmail) — according to PayPal
</p>
<h2>Registration Summary</h2>
$depositHtml
$providedHtml
$optionHtml
$notesHtml
$paypalHtml
WEB_HTML;
        }

        $emailCustomerHtml = <<<EMAIL_CUSTOMER_HTML
<html>
<body>
<h1>$siteName Registration Confirmation</h1>
<p>Thank you, your registration is complete!</p>
<h2>Registration Summary</h2>
$depositHtml
$providedHtml
$optionHtml
$notesHtml
$paypalHtml
</body>
</html>
EMAIL_CUSTOMER_HTML;

        $emailAdminHtml = <<<EMAIL_ADMIN_HTML
<html>
<body>
<h1>$siteName Registration Confirmation</h1>
<p>An event registration has been received!</p>
<h2>Registration Summary</h2>
$depositHtml
$providedHtml
$optionHtml
$notesHtml
$paypalHtml
</body>
</html>
EMAIL_ADMIN_HTML;

        $subject = "$siteName Registration Confirmation";
        $adminEmail = $GLOBALS['pizza']['config']['adminEmail'];
        $this->sendMail($adminEmail, $providedEmail, $subject, $emailCustomerHtml);
        // Also send to email on file with PayPal if different than what was provided.
        if (strtolower($providedEmail) != strtolower($custEmail))
            $this->sendMail($adminEmail, $custEmail, $subject, $emailCustomerHtml);
        $this->sendMail($providedEmail, $adminEmail, $subject, $emailAdminHtml);
        if (isset($GLOBALS['pizza']['config']['cart']['email'])
            && is_array($GLOBALS['pizza']['config']['cart']['email']))
        {
            foreach ($GLOBALS['pizza']['config']['cart']['email'] as $alsoTo)
            {
                if (!filter_var($alsoTo, FILTER_VALIDATE_EMAIL)) continue;
                $this->sendMail($custEmail, $alsoTo, $subject, $emailAdminHtml);
            }
        }
        sc($this->id);
        showFinal($webHtml);
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
            // If temp page, delete it.
            if ($this->cm->isTempPage($pagePath))
            {
                $this->cm->deletePage($pagePath);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Events_' . $parentPagePath);
            }
            sc($this->id);
            relocateNow($urlRoot . $parentPagePath);
        }

        if (
            $v->submitted('deleteSelected')
            || $v->submitted('upload')
            || $v->submitted('preview')
            || $v->submitted('save')
        ) {
            if ($this->cm->isReservedPage($pagePath)) relocateNow($urlRoot . currentUri());
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
            $this->startDate = $v->checkDate('startDate');
            $this->endDate = $v->checkDate('endDate');
            $this->body = $v->checkLength('body', 2, 30000);
            $this->deposit = $v->checkMoney('deposit', true, true);
            $isOpen = $v->checkCheckbox('is-open');
            $this->isOpen = $isOpen['value'];
            $this->optionInstructions = $v->checkLength('optionInstructions', 0, 200);
            $this->option1 = $v->checkLength('option1', 0, 200);
            $this->option2 = $v->checkLength('option2', 0, 200);
            $this->option3 = $v->checkLength('option3', 0, 200);
            $this->option4 = $v->checkLength('option4', 0, 200);
            $this->option5 = $v->checkLength('option5', 0, 200);
            if ($v->error)
            {
                ss($this->id, $this);
                return;
            }
            $startDate = date('Y-m-d', strtotime($this->startDate['value']));
            $endDate = date('Y-m-d', strtotime($this->endDate['value']));
            if ($endDate < $startDate)
            {
                $this->startDate['error'] = 'invalid date range';
                ss($this->id, $this);
                return;
            }
            $fileChangesMade = false;
            // Delete files whose checkboxes were set.
            if ($v->submitted('deleteSelected'))
            {
                $deleteFileNames = isset($input['attachedFiles']) && is_array($input['attachedFiles'])
                ? $input['attachedFiles'] : array();
                foreach ($deleteFileNames as $f) $this->cm->deleteFile($pagePath, $f);
                if (!empty($deleteFileNames)) $fileChangesMade = true;
            }
            // See if files are to be uploaded.
            if (isset($_FILES['files']))
            {
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
                    $fileChangesMade = true;
                }
                if ($fileErrorOccurred)
                {
                    ss($this->id, $this);
                    return;
                }
            }
            // deleteSelected and upload
            if ($v->submitted('deleteSelected') || $v->submitted('upload'))
            {
                ss($this->id, $this);
                return;
            }
            // preview
            if ($v->submitted('preview'))
            {
                if (!$v->error) $this->preview = true;
                ss($this->id, $this);
                return;
            }
            // save
            if ($v->submitted('save'))
            {
                if ($v->error || $fileChangesMade)
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
                $GLOBALS['pizza']['settings']->set($newPagePath, 'startDate', $this->startDate['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'endDate', $this->endDate['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'deposit', $this->deposit['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'isOpen', $this->isOpen);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'optionInstructions', $this->optionInstructions['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'option1', $this->option1['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'option2', $this->option2['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'option3', $this->option3['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'option4', $this->option4['value']);
                $GLOBALS['pizza']['settings']->set($newPagePath, 'option5', $this->option5['value']);
                sc($this->id);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Events_' . $parentPagePath);
                relocateNow($urlRoot . $newPagePath);
            }
        }

        if ($v->submitted('sendRegistration1'))
        {
            $this->captcha = $v->checkCaptcha('captcha');
            $this->email = $v->checkEmail('email');
            $this->name = $v->checkLength('name', 2, 50, 'normalize');
            $this->phone = $v->checkPhone('phone');
            if (hp('optionsInPlay'))
            {
                $this->optionChosen = $v->checkRadio('optionChosen');
                if ($this->optionChosen['error'] == 'not defined')
                    $v->error = true;
            }
            $this->notes = $v->checkLength('notes', 0, 1000, 'normalize');
            ss($this->id, $this);
            if ($v->error)
            {
                sleep(3);
                relocateNow($urlRoot . $pagePath . '?register');
            }
            // If this is a free event, skip second (PayPal) registration step.
            $settings = $GLOBALS['pizza']['page']['settings'];
            $deposit = isset($settings['deposit']) ? $settings['deposit'] : '';
            if (($deposit === '') || ($deposit == 0))
            {
                $this->showThankYou();
            }
            // Generate a transaction ID.
            ss('cart-transaction-id', mt_rand(1000000000, 9999999999));
            relocateNow($urlRoot . $pagePath . '?register2#review');
        }

        sleep(3);
    }
}
?>
