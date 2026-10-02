<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Login
{
    // Form inputs.
    private $email;
    private $password;

    private $id;
    private $key;
    private $showResetPasswordFailed;
    private $showResetPasswordStep1;
    private $showResetPasswordStep2;
    private $showResetPasswordStep1Complete;
    private $showResetPasswordStep2Complete;

    function __construct()
    {
        if (substr($GLOBALS['pizza']['pagePath'], -1) == '/') error404();
        $this->id = 'Login';
    }

    function getHtml()
    {
        $this->initThis();
        if ($GLOBALS['pizza']['user']['id'] > 0) return $this->showAlreadyLoggedIn();
        if (hg('reset') && !hg('key')) $this->showResetPasswordStep1 = true;
        if (hg('reset') && hg('key'))
        {
            $this->key = substr(gg('key'), 0, 32);
            $this->showResetPasswordStep2 = true;
            ss($this->id, $this);
        }
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);
        if ($this->showResetPasswordFailed) $this->showResetPasswordFailed();
        if ($this->showResetPasswordStep1Complete) $this->showResetPasswordStep1Complete();
        if ($this->showResetPasswordStep2Complete) $this->showResetPasswordStep2Complete();
        if ($this->showResetPasswordStep1) return $this->showResetPasswordStep1();
        if ($this->showResetPasswordStep2) return $this->showResetPasswordStep2();
        $location = '';
        if (hg('profile')) $location = '/profile/';
        return $this->showLoginForm($location);
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->email = $o ? $o->email : array('value' => '', 'error' => '');
        $this->password = $o ? $o->password : array('value' => '', 'error' => '');
        $this->key = $o ? $o->key : '';
        $this->showResetPasswordFailed = $o ? $o->showResetPasswordFailed : false;
        $this->showResetPasswordStep1 = $o ? $o->showResetPasswordStep1 : false;
        $this->showResetPasswordStep2 = $o ? $o->showResetPasswordStep2 : false;
        $this->showResetPasswordStep1Complete = $o ? $o->showResetPasswordStep1Complete : false;
        $this->showResetPasswordStep2Complete = $o ? $o->showResetPasswordStep2Complete : false;
    }

    private function showAlreadyLoggedIn()
    {
        ob_start();
        ?>
        <p>You are already logged into your account.</p>
        <?php
        showFinal(ob_get_clean());
    }

    private function showLoginForm($location = '')
    {
        ob_start();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ?>
        <h1>Log In</h1>
        <div class="login" id="login">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <?php
                if ($location !== '')
                {
                    ?>
                    <input name="location" type="hidden" value="<?=myHtmlEntities($location)?>" />
                    <?php
                }
                ?>
                <div>
                    <?php
                    $class = $this->email['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="email" placeholder="Email" type="text" value="" />
                </div>

                <div>
                    <?php
                    $class = $this->password['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="password" placeholder="Password" type="password" value="" />
                    <?php
                    if ($this->password['error'] != '')
                    {
                        ?>
                        <div class="error">↑<?=myHtmlEntities($this->password['error'])?>↑</div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <input name="login" type="submit" value="Log In" />
                </div>
                <p>No account? <a href="<?=$urlRoot?>/signup">Sign Up</a></p>
                <p><a href="<?=$urlRoot?>/login?reset">Forgot your password?</a></p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showResetPasswordFailed()
    {
        ob_start();
        ?>
        <h1>Reset Password</h1>
        <p>There was an error in attempting to reset your password.  Please try again later.</p>
        <?php
        showFinal(ob_get_clean());
    }

    private function showResetPasswordStep1()
    {
        ob_start();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ?>
        <h1>Reset Password</h1>
        <div class="login" id="login">
            <form action="<?=$urlRoot?><?=$pagePath?>" autocomplete="off" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <div>
                    <?php
                    $class = $this->email['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="off" <?=$class?> name="email" placeholder="Your Email" type="text" value="" />
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
                    <input name="resetPasswordStep1" type="submit" value="Submit" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showResetPasswordStep1Complete()
    {
        ob_start();
        ?>
        <h1>Reset Password</h1>
        <p>An email has been sent to you with instructions for resetting your password.</p>
        <?php
        showFinal(ob_get_clean());
    }

    private function showResetPasswordStep2()
    {
        ob_start();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ?>
        <h1>Reset Password</h1>
        <div class="login" id="login">
            <form action="<?=$urlRoot?><?=$pagePath?>" autocomplete="off" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <div>
                    <?php
                    $class = $this->password['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input autocomplete="off" <?=$class?> name="password" placeholder="New Password" type="password" value="" />
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
                    <input name="resetPasswordStep2" type="submit" value="Submit" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showResetPasswordStep2Complete()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        ob_start();
        resetSession();
        ?>
        <h1>Reset Password</h1>
        <p>You successfully reset your password.  <a href="<?=$urlRoot?>/login">Login</a> to use your account.</p>
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

        sc($this->id);

        if ($v->submitted('login'))
        {
            $this->email = $v->checkEmail('email');
            $this->password = $v->checkLength('password', 8, 50);
            if ($v->error)
            {
                $this->email['error'] = 'invalid email or password';
                $this->password['error'] = 'invalid email or password';
                sleep(3);
                return;
            }
            $user = $um->getUser($this->email['value']);
            if (
                ($user === false) ||
                !$um->verifyPassword($this->password['value'], $user['password'])
            ) {
                $this->email['error'] = 'invalid email or password';
                $this->password['error'] = 'invalid email or password';
                sleep(3);
                return;
            }
            if ($user['isSuspended'] != 'n')
            {
                // The user exists but is either unconfirmed ('?') or suspended ('y').
                $adminEmail = $GLOBALS['pizza']['config']['adminEmail'];
                $this->email['error'] = "account locked, email $adminEmail for help.";
                $this->password['error'] = "account locked, email $adminEmail for help.";
                sleep(3);
                return;
            }
            $location = $v->checkLength('location', 1, 100);
            if ($v->error) $location = '';
            else $location = $location['value'];
            login($user);
            sc($this->id);
            $this->initThis();
            if ($location == '')
                relocateNow($urlRoot . previousUri());
            relocateNow($urlRoot . $location);
        }

        if ($v->submitted('resetPasswordStep1'))
        {
            sleep(3);
            $this->email = $v->checkEmail('email');
            $this->showResetPasswordStep1 = false;
            if ($v->error)
            {
                $this->showResetPasswordStep1 = true;
                return;
            }
            $user = $um->getUser($this->email['value']);
            if ($user === false)
            {
                $this->email['error'] = 'account does not exist';
                $this->showResetPasswordStep1 = true;
                return;
            }
            $result = $um->initiateResetPassword($this->email['value']);
            if ($result === false) $this->showResetPasswordFailed = true;
            else $this->showResetPasswordStep1Complete = true;
            return;
        }

        if ($v->submitted('resetPasswordStep2'))
        {
            sleep(3);
            $this->password = $v->checkNewPassword('password');
            $this->showResetPasswordStep2 = false;
            if ($v->error)
            {
                $this->showResetPasswordStep2 = true;
                ss($this->id, $this);
                return;
            }
            $result = $um->resetPassword($this->key, $this->password['value']);
            if ($result === false) $this->showResetPasswordFailed = true;
            else $this->showResetPasswordStep2Complete = true;
            return;
        }

        sleep(3);
    }
}
?>
