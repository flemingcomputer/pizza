<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Guestbook
{
    // Element attributes.
    public $useCaptcha;

    // Form inputs when editing a 'guestbook-entry' page.
    private $captcha;
    private $comments;
    private $name;

    private $cm;
    private $editing;
    private $id;
    private $tempPagePath;
    private $tp;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->tp = $GLOBALS['pizza']['toppings'];
    }

    function applyTopping($html)
    {
        if (hg('guestbook-thank-you'))
            return $this->tp->protect($this->showThankYou());

        // If this page is not a 'guestbook-entry', then only apply any
        // '[guestbook]' element and return.
        if ($GLOBALS['pizza']['page']['kind'] != 'guestbook-entry')
        {
            return preg_replace_callback(
                '/\[(guestbook)(]|\s+.*]|,.*])/sU',
                array($this, '_applyTopping'),
                $html
            );
        }

        // This is a 'guestbook-entry' page.
        // For a guestbook entry, we are not allowing processing of toppings.

        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $parentPagePath = $this->cm->parentPath($pagePath);
        $settings = $GLOBALS['pizza']['page']['settings'];

        // The use-captcha attribute was set at the parent level.
        $o = sge("Guestbook_$parentPagePath", false);
        $this->useCaptcha = $o ? $o->useCaptcha : true;

        $this->id = "Guestbook_$pagePath";
        $this->initThis();

        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id))
        {
            $this->validateInput($_POST);
            return $this->tp->protect($this->showForm());
        }

        if (isAdministrator())
        {
            if (hg('delete'))
            {
                $this->cm->deletePage($pagePath, false);
                relocateNow($urlRoot . $parentPagePath);
            }
            if (hg('edit') || $this->editing)
            {
                $this->editing = true;
                ss($this->id, $this);
                return $this->tp->protect($this->showForm());
            }
            if (hg('turn-off'))
            {
                $this->cm->changePermissions(currentPath(), 'nnr-r-r-');
                relocateNow($urlRoot . $parentPagePath);
            }
            if (hg('turn-on'))
            {
                $this->cm->changePermissions(currentPath(), 'ynr-r-r-');
                relocateNow($urlRoot . $parentPagePath);
            }
            return $this->tp->protect($this->showGuestbookEntry());
        }
        if ((hg('edit') || $this->editing) && $this->cm->isTempPage($pagePath))
        {
            // An anonymous user can edit their temp page.
            $this->editing = true;
            ss($this->id, $this);
            return $this->tp->protect($this->showForm());
        }
        if (substr($GLOBALS['pizza']['page']['mode'], 0, 1) != 'y') error404();
        return $this->tp->protect($this->showGuestbookEntry());
    }

    private function _applyTopping($matches)
    {
        $e = substr($matches[0], 1, -1);
        $this->useCaptcha = $this->tp->getYesNo($e, 'use-captcha', 'yes') != 'no';
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $this->id = "Guestbook_$pagePath";
        $o = sge($this->id, false);
        $this->tempPagePath = $o ? $o->tempPagePath : '';

        if (hg('sign') || $this->cm->hasPage($this->tempPagePath))
        {
            // Either signing just now or started earlier and need to finish.
            if (!$this->cm->hasPage($this->tempPagePath))
            {
                $this->tempPagePath = $this->cm->addTempPage($pagePath, 'guestbook-entry', 'n');
                ss($this->id, $this);
            }
            relocateNow($urlRoot . $this->tempPagePath . '?edit');
        }

        return $this->tp->protect($this->showGuestbook());
    }

    private function initThis()
    {
        $settings = $GLOBALS['pizza']['page']['settings'];
        $o = sge($this->id, false);
        $this->captcha = $o ? $o->captcha : array('value' => '', 'error' => '');
        $this->comments = $o ? $o->comments : array(
            'value' => !$this->cm->isTempPage($GLOBALS['pizza']['pagePath'])
                ? $GLOBALS['pizza']['page']['body'] : '',
            'error' => ''
        );
        $this->name = $o ? $o->name : array(
            'value' => isset($settings['name']) ? $settings['name'] : '',
            'error' => ''
        );
        $this->editing = $o ? $o->editing : false;
    }

    private function showForm()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="page-editor" id="page-editor">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
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

                <?php
                $class = $this->comments['error'] != '' ? 'class="error-placeholder"' : '';
                ?>
                <textarea <?=$class?> name="comments" placeholder="Your Comments"><?=myHtmlEntities($this->comments['value'])?></textarea>
                <?php
                if ($this->comments['error'] != '')
                {
                    ?>
                    <div class="error" style="clear: both;">↑<?=myHtmlEntities($this->comments['error'])?>↑</div>
                    <?php
                }
                ?>

                <?php
                if ($this->useCaptcha)
                {
                    ?>
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
                    <?php
                }
                ?>

                <div>
                    <input class="submit" name="save" type="submit" value="Save" />
                    <input class="submit" name="cancel" type="submit" value="Cancel" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showGuestbook()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $settings = $GLOBALS['pizza']['settings'];
        ob_start();
        $entries = $this->cm->getChildren($pagePath, 'guestbook-entry');
        // We only want an admin to see all guestbook entries, both approved
        // and non-approved.  Everyone else - including the owner of a
        // non-approved entry - only sees approved entries.  So we need to
        // weed out non-approved entries owned by the current user, because
        // the children contain user-owned pages as well.
        if (!isAdministrator())
        {
            $userId = $GLOBALS['pizza']['user']['id'];
            foreach ($entries as $i => $e)
                if (substr($e['mode'], 0, 1) == 'n' && ($e['userId'] == $userId))
                    unset($entries[$i]);
        }
        uasort($entries, function ($a, $b) {
            if ($a['created'] < $b['created']) return 1;
            if ($a['created'] > $b['created']) return -1;
            return 0;
        });
        $numEntries = count($entries);
        ?>
        <p><a href="?sign">Sign Our Guestbook</a></p>
        <?php
        $n = $numEntries;
        $now = time();
        $midnightNow = strtotime(date('Y-m-d', $now));
        foreach ($entries as $e)
        {
            $pageUri = $e['pageUri'];
            $comments = $e['body'];
            $entryPagePath = $pagePath . $pageUri . '/';
            $name = $settings->get($entryPagePath, 'name', 'unknown');
            $created = $e['created'];
            $then = strtotime($created . ' UTC');
            $midnightThen = strtotime(date('Y-m-d', $then));
            if (($midnightNow - $midnightThen) / 86400 >= 1)
                $date = date('M j, Y', $then);
            else $date = date('g:ia', $then);
            ?>
            <p>
            <?=$n?>) <?=$date?> from  <?=myHtmlEntities($name)?>:<br>
            <?=myHtmlEntities($comments)?>
            <?php
            if (isAdministrator())
            {
                ?>
                <br><a href="<?=$entryPagePath?>?edit">edit</a>&nbsp;&nbsp;
                <a href="<?=$entryPagePath?>?delete">delete</a>&nbsp;&nbsp;
                <?php
                if (substr($e['mode'], 0, 1) == 'n')
                {
                    ?>
                    <a style="color: red;" href="<?=$entryPagePath?>?turn-on">turn on</a>
                    <?php
                }
                else
                {
                    ?>
                    <a href="<?=$entryPagePath?>?turn-off">turn off</a>
                    <?php
                }
            }
            ?>
            </p>
            <?php
            $n--;
        }
        return ob_get_clean();
    }

    private function showGuestbookEntry()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $parentPagePath = $this->cm->parentPath($pagePath);
        $settings = $GLOBALS['pizza']['settings'];
        $e = $GLOBALS['pizza']['page'];
        $comments = $this->tp->applyCrust(myHtmlEntities($e['body']));
        $name = $settings->get($pagePath, 'name', 'unknown');
        $created = $e['created'];
        $then = strtotime($created . ' UTC');
        $date = date('M j, Y', $then);
        ob_start();
        ?>
        <h1>From <?=myHtmlEntities($name)?> on <?=$date?></h1>
        <?=$comments?>
        <?php
        return ob_get_clean();
    }

    private function showThankYou()
    {
        ob_start();
        ?>
        <h1>Thank You</h1>
        <p>Thank you for signing our guestbook.  In order to prevent inappropriate content, all guestbook entries are reviewed before being published.</p>
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
            // If temp page, delete it.
            if ($this->cm->isTempPage($pagePath))
            {
                $this->cm->deletePage($pagePath);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Guestbook_' . $parentPagePath);
            }
            sc('captcha-code');
            sc($this->id);
            relocateNow($urlRoot . $parentPagePath);
        }

        if ($v->submitted('save'))
        {
            if ($this->cm->isReservedPage($pagePath)) relocateNow($urlRoot . currentUri());
            if ($this->useCaptcha) $this->captcha = $v->checkCaptcha('captcha');
            $this->name = $v->checkLength('name', 2, 50);
            $this->comments = $v->checkLength('comments', 2, 30000);
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                return;
            }
            // Don't do the edit if the body didn't change.
            $oldBody = trim($GLOBALS['pizza']['page']['body']);
            $newBody = trim($this->comments['value']);
            if ($oldBody != $newBody)
                $this->cm->editPage($pagePath, $this->comments['value'], false);
            $GLOBALS['pizza']['settings']->set($pagePath, 'name', $this->name['value']);
            if ($this->cm->isTempPage($pagePath))
            {
                $c = 1;
                while (true)
                {
                    $newPageName = "Guestbook Entry $c";
                    $newPageUri = $this->cm->pageNameToPageUri($newPageName);
                    $newPagePath = $parentPagePath . $newPageUri . '/';
                    if (!$this->cm->hasPage($newPagePath)) break;
                    $c++;
                }
                $this->cm->renamePage($pagePath, $newPageName);
                // The 'edit' logic in applyTopping only allows for admins to
                // edit a guestbook entry. But just in case, set the
                // permissions so that nobody can edit it (admins always can).
                // Also, a guestbook entry is turned off until an admin
                // approves it.
                $this->cm->changePermissions($newPagePath, 'nnr-r-r-');
                require_once('MailTools.php');
                $mt = new MailTools();
                $name = $this->name['value'] != '' ? $this->name['value'] : 'Anonymous';
                $mt->setReplyTo('noreply@' . strtolower($GLOBALS['pizza']['config']['siteName']));
                $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
                $mt->setTo($GLOBALS['pizza']['config']['adminEmail']);
                $mt->setSubject($GLOBALS['pizza']['config']['siteName'] . ' Guestbook Signed');
                ob_start();
                echo "A visitor has signed your guestbook.  ";
                echo "Your approval is required before the entry will be shown.  ";
                echo 'Please login to ' . $GLOBALS['pizza']['config']['siteName'] . ' ';
                echo "to approve, delete, or edit the entry.\n\n";
                echo "Guest Name:  $name\n";
                echo 'Comments:  ' . $this->comments['value'] . "\n";
                $message = ob_get_clean();
                $mt->setBody($message);
                $result = $mt->send();
                sc('captcha-code');
                sc($this->id);
                // Also clear the parent's state (i.e. tempPagePath)
                sc('Guestbook_' . $parentPagePath);
                relocateNow($urlRoot . $parentPagePath . '?guestbook-thank-you');
            }
            sc($this->id);
            relocateNow($urlRoot . $parentPagePath);
        }

        sleep(3);
    }
}
?>
