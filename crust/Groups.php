<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Groups
{
    // Form inputs.
    private $groupName;
    private $description;

    private $id;

    function __construct()
    {
        $this->id = 'Groups';
    }

    function getHtml()
    {
        if (!isAdministrator()) error404();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        $this->initThis();
        if (hg('group'))
        {
            $groupName = substr(gg('group'), 0, 40);
            if (hg('delete')) return $this->confirmDeleteGroup($groupName);
            if (hg('edit'))
            {
                return $this->showAddEditGroup('edit');
            }
            if (hg('remove'))
            {
                $email = filter_var(gg('remove'), FILTER_VALIDATE_EMAIL);
                if ($email === false) error404();
                $um->toggleMember($groupName, $email);
                relocateNow($urlRoot . $pagePath . '?group=' . urlencode($groupName));
            }
            return $this->showGroupOptions($groupName);
        }
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);
        if (hg('addGroup'))
        {
            return $this->showAddEditGroup('add');
        }
        ob_start();
        ?>
        <h1>Groups</h1>
        <?php
        $groups = $um->getGroups();
        if (count($groups) == 0)
        {
            ?>
            <p>
            There are no groups at this time.
            </p>
            <?php
        }
        else
        {
            ?>
            <div style="overflow: auto; width: 100%;">
            <table border="0" cellpadding="0" cellspacing="0" summary="Group Information">
                <tr>
                    <th style="padding-right: 1em; text-align: left;">Name</th>
                    <th style="text-align: left;">Description</th>
                </tr>

                <?php
                foreach ($groups as $i => $g)
                {
                    $id = $g['id'];
                    $name = $g['name'];
                    $description = $g['description'];
                    $mName = myHtmlEntities($name);
                    $mDescription = myHtmlEntities($description);
                    if ($i == 0) $row = 'first';
                    else if ($i % 2 == 0) $row = 'odd';
                    else $row = 'even';
                    ?>
                    <tr class="<?=$row?>">
                        <?php
                        if ($i == 2) // Users
                        {
                            ?>
                            <td style="padding-right: 1em;"><?=$mName?></td>
                            <?php
                        }
                        else
                        {
                            ?>
                            <td style="padding-right: 1em;"><a href="<?=$urlRoot?><?=$pagePath?>?group=<?=urlencode($name)?>"><?=$mName?></a></td>
                            <?php
                        }
                        ?>
                        <td class="rightmost"><?=$mDescription?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <p><a href="<?=$urlRoot?><?=$pagePath?>?addGroup">Add Group</a></p>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    private function confirmDeleteGroup($groupName)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        if (hg('no'))
            relocateNow($urlRoot . $pagePath . '?group=' . urlencode($groupName));
        if (hg('yes'))
        {
            $um->deleteGroup($groupName);
            relocateNow($urlRoot . $pagePath);
        }
        ob_start();
        ?>
        <h1>Are You Sure?</h1>
        <p>
            Do you really want to delete group <?=myHtmlEntities($groupName)?>?
        </p>
        <p>
            <a href="<?=$urlRoot?><?=$pagePath?>?group=<?=urlencode($groupName)?>&amp;delete&amp;yes">Yes, delete</a>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <a href="<?=$urlRoot?><?=$pagePath?>?group=<?=urlencode($groupName)?>&amp;delete&amp;no">No, cancel</a>
        </p>
        <?php
        return ob_get_clean();
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->groupName = $o ? $o->groupName : array('value' => '', 'error' => '');
        $this->description = $o ? $o->description : array('value' => '', 'error' => '');
    }

    private function showAddEditGroup($mode = 'add')
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $h1 = 'Add Group';
        $saveMode = 'addGroup';
        if (($mode == 'edit') && hg('group'))
        {
            $h1 = 'Edit Group';
            $saveMode = 'editGroup';
            $groupName = substr(gg('group'), 0, 40);
            if (($this->groupName['value'] == '') && ($this->groupName['error'] == '')
                && ($this->description['value'] == '') && ($this->description['error'] == ''))
            {
                // Initialize with existing name and description.
                $um = $GLOBALS['pizza']['um'];
                $group = $um->getGroup($groupName);
                $this->groupName['value'] = $group['name'];
                $this->description['value'] = $group['description'];
                ss($this->id, $this);
            }
        }
        ob_start();
        ?>
        <h1><?=$h1?></h1>
        <form action="<?=$urlRoot?><?=$pagePath?>" enctype="application/x-www-form-urlencoded" method="post" name="<?=myHtmlEntities($this->id)?>">
            <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>">
            <?php
            if (($mode == 'edit') && hg('group'))
            {
                ?>
                <input name="oldGroupName" type="hidden" value="<?=myHtmlEntities($groupName)?>">
                <?php
            }
            ?>
            <input name="groupName" placeholder="Group Name" type="text" value="<?=myHtmlEntities($this->groupName['value'])?>"><br>
            <?php
            if ($this->groupName['error'] != '')
            {
                ?>
                <div class="error">↑<?=myHtmlEntities($this->groupName['error'])?>↑</div>
                <?php
            }
            ?>
            <textarea name="description" placeholder="Description" style="height: 5em;"><?=myHtmlEntities($this->description['value'])?></textarea><br>
            <?php
            if ($this->description['error'] != '')
            {
                ?>
                <div class="error">↑<?=myHtmlEntities($this->description['error'])?>↑</div>
                <?php
            }
            ?>
            <input name="<?=$saveMode?>" type="submit" value="Save">
            <input name="cancel" type="submit" value="Cancel">
        </form>
        <?php
        return ob_get_clean();
    }

    private function showGroupOptions($groupName)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        $group = $um->getGroup($groupName);
        if ($group === false) error404();
        $id = $group['id'];
        $name = $group['name'];
        $description = $group['description'];
        $users = $um->getGroupMembers($name);
        ob_start();
        ?>
        <a href="<?=$urlRoot?><?=$pagePath?>">Back to All Groups</a>
        <h1><?=myHtmlEntities($name)?></h1>
        <?php
        if ($id > 2)
        {
            ?>
            <p><?=myHtmlEntities($description)?> <a href="<?=$urlRoot?><?=$pagePath?>?group=<?=urlencode($name)?>&amp;edit">edit</a></p>
            <?php
        }
        else
        {
            ?>
            <p><?=myHtmlEntities($description)?></p>
            <?php
        }
        if ($id != 2)
        {
            ?>
            <h2>Members</h2>
            <?php
            if (empty($users))
            {
                ?>
                <p>No members</p>
                <?php
            }
        }
        foreach ($users as $u)
        {
            $userName = $u['firstName'] . ' ' . $u['lastName'];
            $email = $u['email'];
            $mUser = myHtmlEntities($userName . ' (' . $email . ')');
            // You can't remove the last administrator.
            if (($id == 1) && (count($users) < 2))
            {
                ?>
                <?=$mUser?><br>
                <?php
            }
            else
            {
                ?>
                <?=$mUser?>&nbsp;&nbsp;<a href="<?=$urlRoot?><?=$pagePath?>?group=<?=urlencode($name)?>&amp;remove=<?=urlencode($email)?>">remove</a><br>
                <?php
            }
        }
        if ($id > 2)
        {
            ?>
            <p><a href="<?=$urlRoot?><?=$pagePath?>?group=<?=urlencode($name)?>&amp;delete">Delete Group</a></p>
            <?php
        }
        ?>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('cancel'))
        {
            sc($this->id);
            if (isset($_POST['oldGroupName']))
            {
                $name = $_POST['oldGroupName'];
                relocateNow($urlRoot . $pagePath . '?group=' . urlencode($name));
            }
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('addGroup'))
        {
            $this->groupName = $v->checkLength('groupName', 1, 40);
            if ((!$v->error) && ($um->groupExists($this->groupName['value'])))
            {
                $v->error = true;
                $this->groupName['error'] = 'group exists';
            }
            $this->description = $v->checkLength('description', 1, 200);
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . $pagePath . '?addGroup');
            }
            $groupName = $this->groupName['value'];
            $description = $this->description['value'];
            $um->addGroup($groupName, $description);
            sc($this->id);
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('editGroup'))
        {
            if (!isset($_POST['oldGroupName'])) relocateNow($urlRoot . $pagePath);
            $oldGroupName = $_POST['oldGroupName'];
            $this->groupName = $v->checkLength('groupName', 1, 40);
            $this->description = $v->checkLength('description', 1, 200);
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . $pagePath . '?group=' . urlencode($oldGroupName) . '&edit');
            }
            $groupName = $this->groupName['value'];
            $description = $this->description['value'];
            $um->editGroup($oldGroupName, $groupName, $description);
            sc($this->id);
            relocateNow($urlRoot . $pagePath);
        }

        sleep(3);
    }
}
?>
