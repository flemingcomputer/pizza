<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class PizzaBar
{
    private $id;
    private $cm;

    function __construct()
    {
        $this->id = 'PizzaBar';
        $this->cm = $GLOBALS['pizza']['cm'];
    }

    function getHtml()
    {
        // All form submission of PizzaBar is now handled via jQuery, thus the
        // POST checking below is commented out.
        //
        // // Since PizzaBar is also rendered in 404 pages, we must make sure to
        // // only validate POST input if the page exists.
        // if (($GLOBALS['pizza']['page'] !== false)
        //     && !empty($_POST) && isset($_POST['formId'])
        //     && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);
        ob_start();
        ?>
        <div class="pizza-bar-wrapper">
            <div id="pizza-bar">
            <ul class="nav-menu">
                <?php
                echo $this->showPageMenu();
                if ($GLOBALS['pizza']['user']['id'] == 0) echo $this->showLoginMenu();
                else echo $this->showAccountMenu();
                echo $this->showCart();
                echo $this->showMainMenu();
                ?>
            </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showAccountMenu()
    {
        $docRoot = $GLOBALS['pizza']['docRoot'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $theme = sge('theme', $GLOBALS['pizza']['theme']);
        $themeFile = "$docRoot/themes/$theme/profile-icon.svg";
        $themeUri = $this->cm->pageNameToPageUri($theme);
        if (is_file($themeFile))
        {
            ob_start();
            include($themeFile);
            $iconSvg = ob_get_clean();
        }
        else if ($this->cm->hasFile("/themes/$themeUri/", 'profile-icon.svg'))
        {
            $iconSvg = $this->cm->getFileContents("/themes/$themeUri/", 'profile-icon.svg');
        }
        else
        {
            ob_start();
            include('profile-icon.svg');
            $iconSvg = ob_get_clean();
        }
        ob_start();
        $firstName = $GLOBALS['pizza']['user']['firstName'];
        ?>
        <li class="menu">
            <span class="menu-icon"><?php echo $iconSvg;?></span>
            <span class="menu-title"><?=myHtmlEntities($firstName)?></span>
            <ul class="submenu">
                <li class="entry"><a href="<?=$urlRoot?>/profile/">Profile</a></li>
                <li class="entry"><a class="logout" href="<?=$urlRoot?>/logout">Log Out</a></li>
            </ul>
        </li>
        <?php
        return ob_get_clean();
    }

    private function showCart()
    {
        if (!isset($GLOBALS['pizza']['cart']) // Could happen if here by 404.
            || ($GLOBALS['pizza']['cart']->numRows() == 0))
            return '';
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        ob_start();
        ?>
        <li class="menu">
            <a class="no-hover" href="<?=$urlRoot?>/cart/"><?php include('pizza-cart.svg');?></a>
        </li>
        <?php
        return ob_get_clean();
    }

    private function showLoginMenu()
    {
        $docRoot = $GLOBALS['pizza']['docRoot'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $currentUrl = $urlRoot . currentUri();
        $theme = sge('theme', $GLOBALS['pizza']['theme']);
        $themeFile = "$docRoot/themes/$theme/profile-icon.svg";
        $themeUri = $this->cm->pageNameToPageUri($theme);
        if (is_file($themeFile))
        {
            ob_start();
            include($themeFile);
            $iconSvg = ob_get_clean();
        }
        else if ($this->cm->hasFile("/themes/$themeUri/", 'profile-icon.svg'))
        {
            $iconSvg = $this->cm->getFileContents("/themes/$themeUri/", 'profile-icon.svg');
        }
        else
        {
            ob_start();
            include('profile-icon.svg');
            $iconSvg = ob_get_clean();
        }
        ob_start();
        ?>
        <li class="menu">
            <span class="menu-icon"><?php echo $iconSvg;?></span>
            <span class="menu-title">Log In</span>
            <ul class="submenu">
                <li class="entry" style="text-align: left;">
                    <div class="login">
                        <form enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>_login">
                            <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                            <input name="currentUrl" type="hidden" value="<?=$currentUrl?>">
                            <div>
                                <input name="email" placeholder="Email" type="text" value="">
                            </div>

                            <div>
                                <input name="password" placeholder="Password" type="password" value="">
                            </div>

                            <div>
                                <input name="login" type="submit" value="Log In">
                            </div>
                        </form>

                        <div style="margin-top: 30px;">
                            <a href="<?=$urlRoot?>/signup" style="display: inline; font-size: smaller; margin: 0px; padding: 0px;">No account?  Sign up!</a>
                            <br><a href="<?=$urlRoot?>/login?reset" style="display: inline; font-size: smaller; margin: 0px; padding: 0px;">Forgot your password?</a>
                        </div>
                    </div>
                </li>
            </ul>
        </li>
        <?php
        return ob_get_clean();
    }

    private function showMainMenu()
    {
        $docRoot = $GLOBALS['pizza']['docRoot'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $theme = sge('theme', $GLOBALS['pizza']['theme']);
        $themeFile = "$docRoot/themes/$theme/menu-icon.svg";
        $themeUri = $this->cm->pageNameToPageUri($theme);
        if (is_file($themeFile))
        {
            ob_start();
            include($themeFile);
            $iconSvg = ob_get_clean();
        }
        else if ($this->cm->hasFile("/themes/$themeUri/", 'menu-icon.svg'))
        {
            $iconSvg = $this->cm->getFileContents("/themes/$themeUri/", 'menu-icon.svg');
        }
        else
        {
            ob_start();
            include('menu-icon.svg');
            $iconSvg = ob_get_clean();
        }
        ob_start();
        ?>
        <li class="menu">
            <span class="menu-icon"><?php echo $iconSvg;?></span>
            <span class="menu-title">Menu</span>
            <ul class="submenu">
                <?php
                $menuFromDb = $this->cm->getMenu('main');
                $menu = array();
                foreach ($menuFromDb as $k => $v)
                    $menu[] = array('label' => $k, 'url' => $v);
                // Put a divider before system menu options.
                $menu[] = array('label' => '---', 'url' => '---');
                foreach ($menu as $m)
                {
                    $label = $m['label'];
                    $iconSvg = '';
                    if (preg_match('/\[(.+)\]/i', $label, $matches))
                    {
                        $label = preg_replace('/\[.+\]/', '', $label);
                        $iconSvg = $matches[1] . '.svg';
                        $svgFile = "$docRoot/themes/$theme/$iconSvg";
                        if (is_file($svgFile))
                        {
                            ob_start();
                            include($svgFile);
                            $iconSvg = trim(ob_get_clean());
                        }
                        else if ($this->cm->hasFile("/themes/$themeUri/", $iconSvg))
                        {
                            $iconSvg = trim($this->cm->getFileContents("/themes/$themeUri/", $iconSvg));
                        }
                        else
                        {
                            ob_start();
                            $result = @include($iconSvg);
                            $iconSvg = trim(ob_get_clean());
                            if (!$result) $iconSvg = '[svg not found]';
                        }
                    }
                    $url = $m['url'];
                    if ($url == '---')
                    {
                        echo "<li class=\"divider\"></li>\n";
                        continue;
                    }
                    if (substr($url, 0, 1) == '/')  // treat as relative
                        $url = $urlRoot . $url;
                    else  // treat as absolute.
                    {
                        if (strtolower(substr($url, 0, 3) != 'ftp')
                            && strtolower(substr($url, 0, 4) != 'http'))
                            $url = 'https://' . $url;
                    }
                    ?>
                    <li class="entry"><a href="<?=$url?>"><?=$iconSvg?><?=myHtmlEntities($label)?></a></li>
                    <?php
                }
                if (isAdministrator() && ($GLOBALS['pizza']['config']['sandbox'] ?? false))
                {
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?>/sandbox/">Sandbox</a></li>
                    <?php
                }
                if (isAdministrator())
                {
                    $menuGear = trim(template('menu-gear.svg'));
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?>/settings/"><?=$menuGear?>Settings</a></li>
                    <?php
                }
                ?>
                <li class="entry"><a href="<?=$urlRoot?>/sitemap/">Sitemap</a></li>
            </ul>
        </li>
        <?php
        return ob_get_clean();
    }

    private function showPageMenu()
    {
        if (hg('addPage')
            || hg('changePermissions')
            || hg('editPage')
            || hg('setTheme')) return;
        // // If this a reserved page, don't show.
        // if (!isset($GLOBALS['pizza']['page']['kind'])
        //     || ($GLOBALS['pizza']['page']['kind'] == 'reserved'))
        //     return '';
        // If the page is locked by this user, don't show.
        if (lockedBy() == $GLOBALS['pizza']['user']['id']) return '';
        // The 'Page' menu has various suboptions, each of which might show up
        // based on certain conditions.  If all suboptions are ruled out,
        // don't show.
        $isOwnerOrAdmin = isOwner() || isAdministrator();
        $canEdit = isWritable();
        $canAddSubpage = $isOwnerOrAdmin && isWritable();
        $canDelete = $isOwnerOrAdmin && isWritable()
            && !$this->cm->pageHasChildren($GLOBALS['pizza']['pagePath']);
        $canRename = $isOwnerOrAdmin && isWritable()
            && $GLOBALS['pizza']['page']['pageName'] != '__domain__';
        $canTurnOnOff = $isOwnerOrAdmin && isWritable();
        $canSetTheme = $canEdit && $isOwnerOrAdmin;
        if (!$canEdit
            && !$canAddSubpage
            && !$canDelete
            && !$canRename
            && !$canTurnOnOff
            && !$canSetTheme)
            return '';
        // && !hg('addArticle')
        // && !hg('addPage')
        // && !hg('deletePage')
        // && !hg('renamePage')
        $docRoot = $GLOBALS['pizza']['docRoot'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $theme = sge('theme', $GLOBALS['pizza']['theme']);
        $themeFile = "$docRoot/themes/$theme/page-icon.svg";
        $themeUri = $this->cm->pageNameToPageUri($theme);
        if (0)//is_file($themeFile))
        {
            ob_start();
            include($themeFile);
            $iconSvg = ob_get_clean();
        }
        else if (0)//$this->cm->hasFile("/themes/$themeUri/", 'page-icon.svg'))
        {
            $iconSvg = $this->cm->getFileContents("/themes/$themeUri/", 'page-icon.svg');
        }
        else
        {
            ob_start();
            include('page-icon.svg');
            $iconSvg = ob_get_clean();
        }
        ob_start();
        ?>
        <li class="menu">
            <span class="menu-icon"><?php echo $iconSvg;?></span>
            <span class="menu-title">Page</span>
            <ul class="submenu">
                <?php
                if ($canEdit)
                {
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?editPage">Edit</a></li>
                    <?php
                }
                /*
                if ($canAddSubpage)
                {
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?copyTree">Copy Tree</a></li>
                    <?php
                }
                */
                if ($canAddSubpage)
                {
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?addPage">Add Subpage</a></li>
                    <?php
                }
                if ($canRename)
                {
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?renamePage">Rename</a></li>
                    <?php
                }
                if ($canEdit && isAdministrator())
                {
                    ?>
                    <li class="divider"></li>
                    <li class="entry"><a class="notify-page" href="<?=$urlRoot?><?=$pagePath?>#0">Notify</a></li>
                    <?php
                }
                if ($canTurnOnOff)
                {
                    ?>
                    <li class="divider"></li>
                    <?php
                    if (isTurnedOn())
                    {
                        ?>
                        <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?turnOff">Turn Off</a></li>
                        <?php
                    }
                    else
                    {
                        ?>
                        <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?turnOn">Turn On</a></li>
                        <?php
                    }
                    if (inSitemap())
                    {
                        ?>
                        <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?unlistPage">Unlist</a></li>
                        <?php
                    }
                    else
                    {
                        ?>
                        <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?listPage">List</a></li>
                        <?php
                    }
                    ?>
                    <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?changePermissions">Permissions</a></li>
                    <?php
                }
                if ($canSetTheme)
                {
                    ?>
                    <li class="divider"></li>
                    <li class="entry"><a href="<?=$urlRoot?><?=$pagePath?>?setTheme">Set Theme</a></li>
                    <?php
                }
                if ($canDelete)
                {
                    ?>
                    <li class="divider"></li>
                    <li class="entry delete-page"><a href="<?=$urlRoot?><?=$pagePath?>?deletePage">Delete</a></li>
                    <?php
                }
                ?>
            </ul>
        </li>
        <?php
        return ob_get_clean();
    }

    // All form submission of PizzaBar is now handled via jQuery, thus the
    // function validateInput below is commented out.
    //
    // private function validateInput($input)
    // {
    //     $urlRoot = $GLOBALS['pizza']['urlRoot'];
    //     $pagePath = $GLOBALS['pizza']['pagePath'];
    //     $um = $GLOBALS['pizza']['um'];
    //     $v = $GLOBALS['pizza']['v'];
    //     $v->setMethod('post');
    //     $v->reset();
    //     if ($v->submitted('login'))
    //     {
    //         $email = $v->checkEmail('email');
    //         $password = $v->checkLength('password', 8, 50);
    //         if ($v->error)
    //         {
    //             sleep(3);
    //             relocateNow($urlRoot . $pagePath . "?loginError");
    //         }
    //         $user = $um->getUser($email['value']);
    //         if (
    //             ($user === false) ||
    //             !$um->verifyPassword($password['value'], $user['password'])
    //         ) {
    //             sleep(3);
    //             relocateNow($urlRoot . $pagePath . "?loginError");
    //         }
    //         if ($user['isSuspended'] != 'n')
    //         {
    //             // The user exists but is either unconfirmed ('?') or suspended ('y').
    //             sleep(3);
    //             relocateNow($urlRoot . $pagePath . "?loginError");
    //         }
    //         ss('user', $user);
    //         relocateNow($urlRoot . $pagePath);
    //     }
    //     sleep(3);
    // }
}
