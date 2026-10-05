<?php
// Copyright 2025 Fleming Computer.
// All Rights Reserved.
?>
<?php
class PageActions
{
    // Form inputs.
    private $pageName;
    private $parentPath;

    private $id;
    private $cm;

    function __construct()
    {
        $this->id = 'PageActions_' . $GLOBALS['pizza']['pagePath'];
        $this->cm = $GLOBALS['pizza']['cm'];
    }

    function getHtml()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        if (hg('sandboxExport') && isAdministrator())
        {
            // Export master sandbox to SQL file.
            $this->cm->sandboxExport();
            relocateNow($urlRoot . $pagePath);
        }
        if (hg('sandboxImport') && isAdministrator())
        {
            // Import master sandbox from SQL file.
            $this->cm->sandboxImport();
            relocateNow($urlRoot . $pagePath);
        }
        if (hg('sandboxPull') && isAdministrator())
        {
            // Copy master sandbox into user's sandbox.
            $this->cm->deleteTree('/sandbox/');
            $this->cm->copyTree('/-sandbox-/', '/', 'Sandbox');
            relocateNow($urlRoot . '/Sandbox');
        }
        if (hg('sandboxPush') && ($pagePath == '/sandbox/') && isAdministrator())
        {
            // Copy admin's updated sandbox into master sandbox.
            $this->cm->deleteTree('/-sandbox-/');
            $this->cm->copyTree('/sandbox/', '/', '_Sandbox_');
            relocateNow($urlRoot . $pagePath);
        }
        if (hg('turnOff'))
        {
            $this->cm->turnOff();
            relocateNow($urlRoot . $pagePath);
        }
        if (hg('turnOn'))
        {
            $this->cm->turnOn();
            relocateNow($urlRoot . $pagePath);
        }
        if (hg('unlistPage'))
        {
            $this->cm->listPageNo();
            relocateNow($urlRoot . $pagePath);
        }
        if (hg('listPage'))
        {
            $this->cm->listPageYes();
            relocateNow($urlRoot . $pagePath);
        }
        $this->initThis();
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);
        if (hg('addPage'))
            return $this->showAddPage();
        if (hg('changePermissions'))
            return $this->showChangePermissions();
        // if (hg('copyTree'))
        //     return $this->showCopyTree();
        if (hg('deletePage') && !$this->cm->pageHasChildren($pagePath))
        {
            $pagePath = currentPath();
            $result = $this->cm->deletePage($pagePath);
            if ($result === false) relocateNow($urlRoot . previousPath());
            relocateNow($urlRoot . $this->cm->parentPath($pagePath));
        }
        if (hg('renamePage') && $GLOBALS['pizza']['page']['pageName'] != '__domain__')
            return $this->showRenamePage();
        if (hg('setTheme'))
            return $this->showSetTheme();
        return '';
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->pageName = $o ? $o->pageName : array('value' => '', 'error' => '');
        $this->parentPath = $o ? $o->parentPath : array('value' => '', 'error' => '');
    }

    private function showAddPage()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="page-options" id="page-options">
            <form action="<?=$urlRoot?><?=$pagePath?>?addPage" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <input name="pageName" placeholder="Page Title" type="text" value="<?=myHtmlEntities($this->pageName['value'])?>">
                <input name="addPage" type="submit" value="Add Page">
                <input name="cancel" type="submit" value="Cancel">
                <?php
                if ($this->pageName['error'] != '')
                {
                    ?>
                    <div class="error">↑<?=myHtmlEntities($this->pageName['error'])?>↑</div>
                    <?php
                }
                ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showChangePermissions()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        // Collect this page's user and group ownership information.
        $pageUserId = $GLOBALS['pizza']['page']['userId'];
        $pageGroupId = $GLOBALS['pizza']['page']['groupId'];
        // Don't reveal all users in HTML unless admin is logged in.
        // Also, only admins can change the user who owns the page.
        if (!isAdministrator())
        {
            $allUsers = array(0 => $um->getUser($pageUserId));
            $disabledChangeUser = 'disabled="disabled"';
        }
        else
        {
            $allUsers = $um->getUsers('lastName');
            $disabledChangeUser = '';
        }
        $allGroups = $um->getGroups();
        $pageGroupName = false;
        foreach ($allGroups as $g)
        {
            if ($pageGroupId == $g['id'])
            {
                $pageGroupName = $g['name'];
                break;
            }
        }
        if ($pageGroupName === false)
        {
            // This page's group doesn't or no longer exists.
            array_unshift($allGroups, array('id' => $pageGroupId, 'name' => 'no group', 'description' => 'No Group.'));
        }
        // Now convert this page's mode into checkbox-friendly form.
        $mode = $GLOBALS['pizza']['page']['mode'];
        $checkedUr = substr($mode, 2, 1) == 'r' ? 'checked="checked"' : '';
        $checkedUw = substr($mode, 3, 1) == 'w' ? 'checked="checked"' : '';
        $checkedGr = substr($mode, 4, 1) == 'r' ? 'checked="checked"' : '';
        $checkedGw = substr($mode, 5, 1) == 'w' ? 'checked="checked"' : '';
        $checkedOr = substr($mode, 6, 1) == 'r' ? 'checked="checked"' : '';
        $checkedOw = substr($mode, 7, 1) == 'w' ? 'checked="checked"' : '';
        ob_start();
        ?>
        <div class="page-options" id="page-options">
            <form action="<?=$urlRoot?><?=$pagePath?>?changePermissions" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <table border="0" cellpadding="0" cellspacing="0" summary="Permissions">
                    <tr>
                        <th>&nbsp;</th>
                        <th style="text-align: center;">&nbsp;R&nbsp;</th>
                        <th style="text-align: center;">&nbsp;W&nbsp;</th>
                        <th style="text-align: center;">&nbsp;</th>
                    </tr>
                    <tr>
                        <td style="text-align: right;">User</td>
                        <td style="text-align: center;"><input <?=$checkedUr?> <?=$disabledChangeUser?> name="ur" type="checkbox"></td>
                        <td style="text-align: center;"><input <?=$checkedUw?> <?=$disabledChangeUser?> name="uw" type="checkbox"></td>
                        <td style="text-align: left;">
                            <select <?=$disabledChangeUser?> name="pageUserEmail">
                                <?php
                                foreach ($allUsers as $u)
                                {
                                    $mEmail = myHtmlEntities($u['email']);
                                    $mName = myHtmlEntities($u['firstName'] . ' ' . $u['lastName']);
                                    $selected = $u['id'] == $pageUserId ? 'selected="selected"' : '';
                                    ?>
                                    <option <?=$selected?> value="<?=$mEmail?>"><?=$mName?></option>
                                    <?php
                                }
                                ?>
                            </select>
                            <?php
                            if (!isAdministrator())
                            {
                                $mPageUserEmail = myHtmlEntities($allUsers[0]['email']);
                                ?>
                                <input name="pageUserEmail" type="hidden" value="<?=$mPageUserEmail?>">
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right;">Group</td>
                        <td style="text-align: center;"><input <?=$checkedGr?> name="gr" type="checkbox"></td>
                        <td style="text-align: center;"><input <?=$checkedGw?> name="gw" type="checkbox"></td>
                        <td style="text-align: left;">
                            <select name="pageGroupName">
                                <?php
                                foreach ($allGroups as $g)
                                {
                                    $mGroup = myHtmlEntities($g['name']);
                                    $selected = $g['id'] == $pageGroupId ? 'selected="selected"' : '';
                                    $disabled = ($g['id'] == $pageGroupId) && ($pageGroupName === false)
                                        ? 'disabled="disabled"' : '';
                                    ?>
                                    <option <?=$disabled?> <?=$selected?> value="<?=$mGroup?>"><?=$mGroup?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right;">Other</td>
                        <td style="text-align: center;"><input <?=$checkedOr?> name="or" type="checkbox"></td>
                        <td style="text-align: center;"><input <?=$checkedOw?> name="ow" type="checkbox"></td>
                        <td style="text-align: left;">&nbsp;</td>
                    </tr>
                    <tr><td colspan="4" style="line-height: 0.5;">&nbsp;</td></tr>
                </table>
                <input name="changeSubpages" type="checkbox"> Change subpages too<br>
                <input name="changePermissions" type="submit" value="Change Permissions">
                <input name="cancel" type="submit" value="Cancel">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showCopyTree()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="page-options" id="page-options">
            <form action="<?=$urlRoot?><?=$pagePath?>?copyTree" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <input name="parentPath" placeholder="Parent Path" type="text" value="<?=myHtmlEntities($this->parentPath['value'])?>">
                <input name="copyTree" type="submit" value="Copy Tree">
                <input name="cancel" type="submit" value="Cancel">
                <?php
                if ($this->parentPath['error'] != '')
                {
                    ?>
                    <div class="error">↑<?=myHtmlEntities($this->parentPath['error'])?>↑</div>
                    <?php
                }
                ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showRenamePage()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        if ($this->pageName['value'] == '')
            $this->pageName['value'] = $GLOBALS['pizza']['page']['pageName'];
        ob_start();
        ?>
        <div class="page-options" id="page-options">
            <form action="<?=$urlRoot?><?=$pagePath?>?renamePage" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <input name="pageName" placeholder="New Title" type="text" value="<?=myHtmlEntities($this->pageName['value'])?>">
                <input name="renamePage" type="submit" value="Rename Page">
                <input name="cancel" type="submit" value="Cancel">
                <?php
                if ($this->pageName['error'] != '')
                {
                    ?>
                    <div class="error">↑<?=myHtmlEntities($this->pageName['error'])?>↑</div>
                    <?php
                }
                ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showSetTheme()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        ob_start();
        ?>
        <div class="page-options" id="page-options">
            <form action="<?=$urlRoot?><?=$pagePath?>?setTheme" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
                <select name="themeName">
                    <?php
                    $themes = $this->cm->getThemes();
                    foreach ($themes as $t)
                    {
                        $selected = $t == sge('theme', $GLOBALS['pizza']['theme']) ? 'selected="selected"' : '';
                        ?>
                        <option <?=$selected?> value="<?=myHtmlEntities($t)?>"><?=myHtmlEntities($t)?></option>
                        <?php
                    }
                    ?>
                </select>
                <br><input name="setSubpages" type="checkbox"> apply to subpages<br>
                <input name="setTheme" type="submit" value="Set Theme">
                <input name="cancel" type="submit" value="Cancel">
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

        if ($v->submitted('cancel'))
        {
            relocateNow($urlRoot . currentPath());
        }

        if ($v->submitted('addPage'))
        {
            $this->pageName = $v->checkLength('pageName', 1, 200);
            if ($v->error) return;
            $pagePath = currentPath();
            if ($this->cm->isReservedPage($pagePath))
            {
                $this->pageName['error'] = 'cannot add below reserved page';
                return;
            }
            $pageName = $this->pageName['value'];
            $pageUri = $this->cm->pageNameToPageUri($pageName);
            // If pageUri starts and ends with '-', e.g. '-hello-', reserved.
            if (preg_match('/^-.*-$/', $pageUri))
            {
                $this->pageName['error'] = 'reserved';
                return;
            }
            $newPagePath = $pagePath . $pageUri . '/';
            if ($this->cm->hasPage($newPagePath))
            {
                $this->pageName['error'] = 'page exists';
                return;
            }
            $result = $this->cm->addPage($pagePath, $pageName);
            sc($this->id);
            $this->initThis();
            if ($result === false) return;
            relocateNow($urlRoot . $newPagePath . '?editPage');
        }

        if ($v->submitted('changePermissions'))
        {
            $pageUserEmail = $v->checkEmail('pageUserEmail');
            $pageGroupName = $v->checkLength('pageGroupName', 1, 80);
            $ur = $v->checkCheckbox('ur');
            $uw = $v->checkCheckbox('uw');
            $gr = $v->checkCheckbox('gr');
            $gw = $v->checkCheckbox('gw');
            $or = $v->checkCheckbox('or');
            $ow = $v->checkCheckbox('ow');
            $changeSubpages = $v->checkCheckbox('changeSubpages');
            if ($v->error)
            {
                sleep(3);
                relocateNow($urlRoot . currentPath() . '?changePermissions');
            }
            $pageUserEmail = $pageUserEmail['value'];
            $pageGroupName = $pageGroupName['value'];
            $ur = $ur['value'] !== false ? 'r' : '-';
            $uw = $uw['value'] !== false ? 'w' : '-';
            $gr = $gr['value'] !== false ? 'r' : '-';
            $gw = $gw['value'] !== false ? 'w' : '-';
            $or = $or['value'] !== false ? 'r' : '-';
            $ow = $ow['value'] !== false ? 'w' : '-';
            $changeSubpages = $changeSubpages['value'] !== false;
            $mode = $GLOBALS['pizza']['page']['mode'];
            $turnedOn = substr($mode, 0, 1);
            $inSitemap = substr($mode, 1, 1);
            if (!isAdministrator())
            {
                // Only admins can change the page's r/w mode for the owning user.
                $ur = substr($mode, 2, 1);
                $uw = substr($mode, 3, 1);
            }
            $mode = $turnedOn . $inSitemap . $ur . $uw . $gr . $gw . $or . $ow;
            // Validate submitted user email and group name.
            $um = $GLOBALS['pizza']['um'];
            $user = $um->getUser($pageUserEmail);
            $group = $um->getGroup($pageGroupName);
            if (($user === false) || ($group === false))
            {
                sleep(3);
                relocateNow($urlRoot);
            }
            // Only admins can change the user who owns the page.
            $userId = isAdministrator() ? $user['id'] : false;
            $groupId = $group['id'];
            $this->cm->changePermissions(currentPath(), $mode, $userId, $groupId, $changeSubpages);
            relocateNow($urlRoot . currentPath());
        }

        // if ($v->submitted('copyTree'))
        // {
        //     $this->parentPath = $v->checkLength('parentPath', 1, 200);
        //     if ($v->error) return;
        //     $srcPagePath = currentPath();
        //     $dstPagePath = $this->parentPath['value'];
        //     $result = $this->cm->copyTree($srcPagePath, $dstPagePath);
        //     sc($this->id);
        //     $this->initThis();
        //     relocateNow($urlRoot . $dstPagePath . basename($srcPagePath));
        // }

        if ($v->submitted('renamePage'))
        {
            $this->pageName = $v->checkLength('pageName', 1, 200);
            if ($v->error) return;
            $pagePath = currentPath();
            if ($pagePath == '/') relocateNow($urlRoot . $pagePath);
            if ($this->cm->isReservedPage($pagePath)) relocateNow($urlRoot . $pagePath);
            $currentPageName = $GLOBALS['pizza']['page']['pageName'];
            $newPageName = $this->pageName['value'];
            if ($currentPageName == $newPageName) relocateNow($urlRoot . $pagePath);
            $newPageUri = $this->cm->pageNameToPageUri($newPageName);
            // If newPageUri starts and ends with '-', e.g. '-hello-', reserved.
            if (preg_match('/^-.*-$/', $newPageUri))
            {
                $this->pageName['error'] = 'reserved';
                return;
            }
            $newPagePath = $this->cm->parentPath($pagePath) . $this->cm->pageNameToPageUri($newPageName) . '/';
            if (($pagePath != $newPagePath) && $this->cm->hasPage($newPagePath))
            {
                $this->pageName['error'] = 'page exists';
                return;
            }
            $result = $this->cm->renamePage($pagePath, $newPageName);
            sc($this->id);
            $this->initThis();
            if ($result === false) return;
            relocateNow($urlRoot . $newPagePath);
        }

        if ($v->submitted('setTheme'))
        {
            $themeName = $v->checkLength('themeName', 1, 80);
            $setSubpages = $v->checkCheckbox('setSubpages');
            if ($v->error)
            {
                sleep(3);
                relocateNow($urlRoot . currentPath() . '?setTheme');
            }
            $themeName = $themeName['value'];
            $setSubpages = $setSubpages['value'] !== false;
            $themes = $this->cm->getThemes();
            if (!in_array($themeName, $themes))
            {
                sleep(3);
                relocateNow($urlRoot);
            }
            // Set this pages' theme.
            $this->cm->setTheme(currentPath(), $themeName, $setSubpages);
            relocateNow($urlRoot . currentPath());
        }

        sleep(3);
    }
}
?>
