<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Contact
{
    // Element attributes.
    public $alsoEmail;
    public $requirePhone;
    public $sendTo;
    public $useCaptcha;

    // Form inputs.
    private $captcha;
    private $email;
    private $message;
    private $name;
    private $phone;

    private $id;
    private $mode;
    private $tp;

    function __construct()
    {
        $this->id = 'Contact_' . $GLOBALS['pizza']['pagePath'];
        $this->tp = $GLOBALS['pizza']['toppings'];
    }

    function applyTopping($html)
    {
        $html = preg_replace_callback(
            '/\[(contact)(]|\s+.*]|,.*])/sU',
            array($this, '_applyTopping'),
            $html
        );
        return $html;
    }

    private function _applyTopping($matches)
    {
        $this->initThis();
        $e = substr($matches[0], 1, -1);
        $this->requirePhone = $this->tp->getYesNo($e, 'require-phone', 'no') == 'yes';
        $this->useCaptcha = $this->tp->getYesNo($e, 'use-captcha', 'yes') != 'no';
        $this->sendTo = $this->tp->getString($e, 'send-to', '');
        if (($this->sendTo == '') || !filter_var($this->sendTo, FILTER_VALIDATE_EMAIL))
            $this->sendTo = $GLOBALS['pizza']['config']['adminEmail'];
        $this->alsoEmail = array_filter(explode(';', $this->tp->getString($e, 'also-email', '')));
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);

        if ($this->mode == 'contact-sent')
            showFinal($this->showContactSent());
        if ($this->mode == 'contact-failed')
            showFinal($this->showContactFailed());
        return $this->tp->protect($this->showForm());
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->captcha = $o ? $o->captcha : array('value' => '', 'error' => '');
        $this->email = $o ? $o->email : array('value' => '', 'error' => '');
        $this->message = $o ? $o->message : array('value' => '', 'error' => '');
        $this->mode = $o ? $o->mode : '';
        $this->name = $o ? $o->name : array('value' => '', 'error' => '');
        $this->phone = $o ? $o->phone : array('value' => '', 'error' => '');
    }

    private function showContactFailed()
    {
        ob_start();
        ?>
        <p>
        Due to a system error, your message could not be sent. Please email <b><?=myHtmlEntities($this->sendTo)?></b> for help.
        </p>
        <?php
        sc($this->id);
        return ob_get_clean();
    }

    private function showContactSent()
    {
        ob_start();
        ?>
        <p>
        Thank you, your message has been sent.
        </p>
        <p>
        If you do not hear back soon, please email <b><?=myHtmlEntities($this->sendTo)?></b> for help.
        </p>
        <?php
        sc($this->id);
        return ob_get_clean();
    }

    private function showForm()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="contact" id="contact">
            <form action="<?=$urlRoot?><?=$pagePath?>#contact" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
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

                <?php
                if ($this->requirePhone)
                {
                    ?>
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
                }
                ?>

                <div>
                    <?php
                    $class = $this->message['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <textarea <?=$class?> name="message" placeholder="Message"><?=myHtmlEntities($this->message['value'])?></textarea>
                    <?php
                    if ($this->message['error'] != '')
                    {
                        ?>
                        <div class="error">&nbsp;&nbsp;↑ <?=myHtmlEntities($this->message['error'])?> ↑</div>
                        <?php
                    }
                    ?>
                </div>

                <?php
                if ($this->useCaptcha)
                {
                    ?>
                    <div>
                        <input name="company2" type="text" value="" />
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
                    <?php
                }
                ?>

                <div>
                    <input name="send" type="submit" value="Send" />
                    <input name="clear" type="submit" value="Clear" />
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
        return trim(ob_get_clean());
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('cancel'))
        {
            sc('captcha-code');
            sc($this->id);
            relocateNow($urlRoot . previousUri());
        }

        if ($v->submitted('clear'))
        {
            sc('captcha-code');
            sc($this->id);
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('send'))
        {
            if ($this->useCaptcha)
            {
                $this->captcha = $v->checkCaptcha('captcha');
                $company2 = $v->checkLength('company2', 0, 100);
                if ($company2['value'] != '') $v->error = true;
            }
            $this->email = $v->checkEmail('email');
            $this->message = $v->checkLength('message', 5, 15000);
            $this->name = $v->checkLength('name', 2, 50, 'normalize');
            if ($this->requirePhone
                || isset($input['phone']) && (trim($input['phone']) != '')
            ) $this->phone = $v->checkPhone('phone');
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . $pagePath);
            }
            require_once('MailTools.php');
            $mt = new MailTools();
            $name = $this->name['value'];
            if ($name == '') $name = 'Anonymous';
            $email = $this->email['value'];
            $from = "$name <$email>";
            $subject = "{$GLOBALS['pizza']['config']['siteName']} Inquiry";
            $message = "Inquiry From: $from\n\n" . $this->message['value'];
            $phone = $this->phone['value'];
            if ($phone != '') $message = "Sender's Phone #:  $phone\n\n" . $message;
            $mt->setReplyTo($from);
            $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
            $mt->setTo($this->sendTo);
            $mt->setSubject($subject);
            $mt->setBody($message);
            $mt->setHeader('X-Inquiry-Reply-To', $from);
            $result = $mt->send();
            // Also email...
            foreach ($this->alsoEmail as $alsoTo)
            {
                if (!filter_var($alsoTo, FILTER_VALIDATE_EMAIL)) continue;
                $mt->setTo($alsoTo);
                $result = $mt->send();
            }
            sc('captcha-code');
            sc($this->id);
            if ($result !== false)
                $this->mode = 'contact-sent';
            else
                $this->mode = 'contact-failed';
            ss($this->id, $this);
            relocateNow($urlRoot . $pagePath);
        }

        sleep(3);
    }
}
?>
