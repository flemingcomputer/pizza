<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Signup
{
    // Element attributes.
    public $useCaptcha;

    // Form inputs.
    private $captcha;
    private $email;
    private $firstName;
    private $invite;
    private $lastName;
    private $password;

    private $id;
    private $showSignupFailed;

    function __construct()
    {
        if (substr($GLOBALS['pizza']['pagePath'], -1) == '/') error404();
        $this->id = 'Signup';
    }

    function getHtml()
    {
        $this->initThis();
        if (hg('confirmAccount')) return $this->showAccountConfirmed();
        // if (hg('delete') && hg('key')) return $this->showDeleteAccount();
        if ($GLOBALS['pizza']['user']['id'] > 0) return $this->showAlreadySignedUp();
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);
        if ($this->showSignupFailed) return $this->showSignupFailed();
        return $this->showForm();
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->captcha = $o ? $o->captcha : array('value' => '', 'error' => '');
        $this->email = $o ? $o->email : array('value' => '', 'error' => '');
        $this->firstName = $o ? $o->firstName : array('value' => '', 'error' => '');
        $this->invite = $o ? $o->invite : array('value' => '', 'error' => '');
        $this->lastName = $o ? $o->lastName : array('value' => '', 'error' => '');
        $this->password = $o ? $o->password : array('value' => '', 'error' => '');
        $this->useCaptcha = $o ? $o->useCaptcha : true;
        $this->showSignupFailed = $o ? $o->showSignupFailed : false;
    }

    private function showAccountConfirmed()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $um = $GLOBALS['pizza']['um'];
        $key = substr(gg('confirmAccount'), 0, 32);
        $email = $um->confirmAccount($key);
        if ($email === false)
        {
            sleep(3);
            relocateNow($urlRoot);
        }
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        ob_start();
        echo <<<HTML
        <h1>Account Confirmed</h1>
        <p>
        Your account has been confirmed. You can <a href="$urlRoot/login">login now</a>.
        </p>
HTML;
        showFinal(ob_get_clean());
    }

    private function showAlreadySignedUp()
    {
        ob_start();
        ?>
        <h1>Already Signed Up</h1>
        <p>You are already signed up, and are currently logged into your account.</p>
        <?php
        showFinal(ob_get_clean());
    }

//     private function showDeleteAccount()
//     {
//         $urlRoot = $GLOBALS['pizza']['urlRoot'];
//         $um = $GLOBALS['pizza']['um'];
//         $key = substr(gg('key'), 0, 32);
//         $email = $um->deleteAccount($key);
//         if ($email === false)
//         {
//             sleep(3);
//             relocateNow($urlRoot);
//         }
//         resetSession();
//         ob_start();
//         echo <<<HTML
//         <h1>Account Deleted</h1>
//         <p>
//         This account is now deleted.  We are sorry that someone attempted to use your email address to sign up with us.
//         </p>
// HTML;
//         showFinal(ob_get_clean());
//     }

    private function showForm()
    {
        ob_start();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ?>
        <h1>Sign Up</h1>
        <div class="signup" id="signup">
            <form action="<?=$urlRoot?><?=$pagePath?>" autocomplete="off" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <div>
                    <?php
                    $class = $this->firstName['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="off" <?=$class?> name="firstName" placeholder="First Name" type="text" value="<?=myHtmlEntities($this->firstName['value'])?>" />
                    <?php
                    if ($this->firstName['error'] != '')
                    {
                        ?>
                        <div class="error">↑<?=myHtmlEntities($this->firstName['error'])?>↑</div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->lastName['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="off" <?=$class?> name="lastName" placeholder="Last Name" type="text" value="<?=myHtmlEntities($this->lastName['value'])?>" />
                    <?php
                    if ($this->lastName['error'] != '')
                    {
                        ?>
                        <div class="error">↑<?=myHtmlEntities($this->lastName['error'])?>↑</div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->email['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="off" <?=$class?> name="email" placeholder="Email" type="text" value="<?=myHtmlEntities($this->email['value'])?>" />
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
                    $class = $this->password['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="new-password" <?=$class?> name="password" placeholder="New Password" type="password" value="" />
                    <?php
                    if ($this->password['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->password['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->invite['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="off" <?=$class?> name="invite" placeholder="Invite Code (if given)" type="text" value="<?=myHtmlEntities($this->invite['value'])?>" />
                    <?php
                    if ($this->invite['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->invite['error'])?></span>
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
                        <input autocomplete="off" <?=$class?> name="captcha" placeholder="Enter CAPTCHA" type="text" value="" />
                        <?php
                        if ($this->captcha['error'] != '')
                        {
                            ?>
                            <div class="error">↑<?=myHtmlEntities($this->captcha['error'])?>↑</div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                }
                ?>

                <div>
                    <input name="signup" type="submit" value="Sign Up" />
                </div>
                <p>Already signed up? <a href="<?=$urlRoot?>/login">Log In</a></p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showSignupFailed()
    {
        ob_start();
        ?>
        <h1>Cannot sign you up.</h1>
        <p>
        Sorry, due to a system error we cannot sign you up at this time.  :-(
        </p>
        <p>
        Please try again later.
        </p>
        <p>
        If you need assistance, please email <b><?=myHtmlEntities($GLOBALS['pizza']['config']['adminEmail'])?></b> for help.
        </p>
        <?php
        showFinal(ob_get_clean());
    }

    private function showSignupSuccess()
    {
        ob_start();
        ?>
        <h1>Thank You!</h1>
        <p>Thank you for signing up! Next, check your email for instructions to confirm this account.</p>
        <?php
        showFinal(ob_get_clean());
    }

    private function validateInput($input)
    {
        $um = $GLOBALS['pizza']['um'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('signup'))
        {
            if ($this->useCaptcha)
            {
                $this->captcha = $v->checkCaptcha('captcha');
                // Only entertain other errors if they pass the captcha challenge.
                if ($this->captcha['error'] != '')
                {
                    sleep(3);
                    return;
                }
            }
            $company2 = $v->checkLength('company2', 0, 100);
            if ($company2['value'] != '') $v->error = true;
            $this->email = $v->checkEmail('email');
            $this->firstName = $v->checkLength('firstName', 2, 20, 'normalize');
            $this->invite = $v->checkLength('invite', 0, 20, 'normalize');
            $this->lastName = $v->checkLength('lastName', 2, 30, 'normalize');
            $this->password = $v->checkNewPassword('password');
            if (($this->email['error'] == '') && $um->userExists($this->email['value']))
                $this->email['error'] = 'account already exists';
            if (
                $v->error
                || ($this->email['error'] != '')
                || ($this->invite['error'] != '')
                || ($this->firstName['error'] != '')
                || ($this->lastName['error'] != '')
            ) {
                $this->password['value'] = '';
                sleep(3);
                return;
            }
            $email = $this->email['value'];
            $firstName = $this->firstName['value'];
            $invite = $this->invite['value'];
            $lastName = $this->lastName['value'];
            $password = $this->password['value'];
            $result = $um->addUser($email, $firstName, $lastName, $password, $invite);
            sc($this->id);
            $this->initThis();
            if ($this->useCaptcha) sc('captcha-code');
            sleep(3);
            if ($result === false)
            {
                $this->showSignupFailed = true;
                return;
            }
            // // Automatically log user in.
            // $user = $um->getUser($email);
            // ss('user', $user);
            // relocateNow($urlRoot . previousUri());
            $this->showSignupSuccess();
        }

        sleep(3);
    }
}
?>
