<?php
// Copyright 2022 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Profile
{
    // Form inputs.
    private $email;
    private $isSubscribed;
    private $firstName;
    private $lastName;

    private $id;

    function __construct()
    {
        $this->id = 'Profile';
    }

    function getHtml()
    {
        if ($GLOBALS['pizza']['user']['id'] == 0) error404();
        $this->initThis();
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);
        return $this->showForm();
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->email = $o ? $o->email : array('value' => $GLOBALS['pizza']['user']['email'], 'error' => '');
        $this->isSubscribed = $o ? $o->isSubscribed : ($GLOBALS['pizza']['user']['isSubscribed'] == 'y');
        $this->firstName = $o ? $o->firstName : array('value' => $GLOBALS['pizza']['user']['firstName'], 'error' => '');
        $this->lastName = $o ? $o->lastName : array('value' => $GLOBALS['pizza']['user']['lastName'], 'error' => '');
    }

    private function showForm()
    {
        ob_start();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ?>
        <h1>Profile</h1>
        <div>
            <form action="<?=$urlRoot?><?=$pagePath?>" autocomplete="off" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <div>
                    <?php
                    $class = $this->firstName['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <div class="form-label">First Name</div>
                    <input autocomplete="off" <?=$class?> name="firstName" type="text" value="<?=myHtmlEntities($this->firstName['value'])?>" />
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
                    <div class="form-label">Last Name</div>
                    <input autocomplete="off" <?=$class?> name="lastName" type="text" value="<?=myHtmlEntities($this->lastName['value'])?>" />
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
                    <div class="form-label">Email</div>
                    <input autocomplete="off" <?=$class?> name="email" type="text" value="<?=myHtmlEntities($this->email['value'])?>" />
                    <?php
                    if ($this->email['error'] != '')
                    {
                        ?>
                        <div class="error">↑<?=myHtmlEntities($this->email['error'])?>↑</div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $isSubscribedChecked = $this->isSubscribed ? 'checked="checked"' : '';
                    ?>
                    <br><input name="is-subscribed" type="checkbox" <?=$isSubscribedChecked?> />
                    &nbsp;Subscribe to email notifications
                </div>

                <div>
                    <input name="save" type="submit" value="Save" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $um = $GLOBALS['pizza']['um'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('save'))
        {
            $this->email = $v->checkEmail('email');
            $isSubscribed = $v->checkCheckbox('is-subscribed');
            $this->isSubscribed = $isSubscribed['value'];
            $this->firstName = $v->checkLength('firstName', 2, 20, 'normalize');
            $this->lastName = $v->checkLength('lastName', 2, 30, 'normalize');
            if (($this->email['error'] == '')
                && ($this->email['value'] != $GLOBALS['pizza']['user']['email'])
                && $um->userExists($this->email['value']))
                $this->email['error'] = 'account already exists';
            if (
                $v->error
                || ($this->email['error'] != '')
            ) {
                sleep(3);
                return;
            }
            $newEmail = $this->email['value'];
            $newFirstName = $this->firstName['value'];
            $newLastName = $this->lastName['value'];
            if (($newEmail != $GLOBALS['pizza']['user']['email'])
                || ($this->isSubscribed !== ($GLOBALS['pizza']['user']['isSubscribed'] == 'y'))
                || ($newFirstName != $GLOBALS['pizza']['user']['firstName'])
                || ($newLastName != $GLOBALS['pizza']['user']['lastName']))
            {
                $result = $um->editUser($GLOBALS['pizza']['user']['email'], $newEmail, $newFirstName, $newLastName,
                    $this->isSubscribed);
                if ($result)
                {
                    $user = $um->getUser($newEmail);
                    ss('user', $user);
                }
            }
            sc($this->id);
            relocateNow($urlRoot . previousUri());
        }

        sleep(3);
    }
}
?>
