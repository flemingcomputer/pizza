<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Users
{
    private $id;

    function __construct()
    {
        $this->id = 'Users';
    }

    function getHtml()
    {
        if (!isAdministrator()) error404();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        if (hg('email'))
        {
            $email = filter_var(gg('email'), FILTER_VALIDATE_EMAIL);
            if ($email === false) error404();
            if (hg('toggleGroup'))
            {
                $groupName = substr(gg('toggleGroup'), 0, 40);
                $um->toggleMember($groupName, $email);
                relocateNow($urlRoot . $pagePath . '?email=' . urlencode($email));
            }
            return $this->showUserOptions($email);
        }
        ob_start();
        // 1. Set field default orderings.
        $dirEmail = 'a';
        $dirFirstName = 'a';
        $dirLastName = 'a';
        $dirCreated = 'a';
        $arrowEmail = '';
        $arrowFirstName = '';
        $arrowLastName = '';
        $arrowCreated = '';

        // 2. Determine current sort field.
        $sortBy = gge('sortBy', 'created');
        if (
            ($sortBy != 'email')
            && ($sortBy != 'firstName')
            && ($sortBy != 'lastName')
            && ($sortBy != 'created')
        )
            $sortBy = 'created';

        // 3. Determine current direction.
        $direction = gge('direction', 'd');
        if (
            ($direction != 'a')
            && ($direction != 'd')
        )
            $direction = 'a';

        // 4. Get users sorted by the current field in the current direction.
        $users = $um->getUsers($sortBy, $direction);

        // 4.5. Remove '?' users.
        $users = array_filter($users, function($u) {return $u['isSuspended'] != '?';});

        // 5. Toggle the direction...
        if ($direction == 'a')
        {
            $direction = 'd';
            $arrow = ' ↓';
        }
        else
        {
            $direction = 'a';
            $arrow = ' ↑';
        }

        // 6. ...for the current field only.
        if ($sortBy == 'email')
        {
            $dirEmail = $direction;
            $arrowEmail = $arrow;
        }
        else if ($sortBy == 'firstName')
        {
            $dirFirstName = $direction;
            $arrowFirstName = $arrow;
        }
        else if ($sortBy == 'lastName')
        {
            $dirLastName = $direction;
            $arrowLastName = $arrow;
        }
        else if ($sortBy == 'created')
        {
            $dirCreated = $direction;
            $arrowCreated = $arrow;
        }
        ?>
        <h1>Users (<?=count($users)?>)</h1>

        <?php
        if (count($users) == 0)
        {
            ?>
            <p>
            There are no users at this time.
            </p>
            <?php
        }
        else
        {
            ?>
            <div style="overflow: auto; width: 100%;">
            <table border="0" cellpadding="0" cellspacing="0" summary="User Information">
                <tr>
                    <th style="padding-right: 1em; text-align: left;"><a href="<?=$urlRoot?><?=$pagePath?>?sortBy=email&amp;direction=<?=$dirEmail?>">Email Address</a><?=$arrowEmail?></th>
                    <th style="padding-right: 1em; text-align: left;"><a href="<?=$urlRoot?><?=$pagePath?>?sortBy=firstName&amp;direction=<?=$dirFirstName?>">First Name</a><?=$arrowFirstName?></th>
                    <th style="padding-right: 1em; text-align: left;"><a href="<?=$urlRoot?><?=$pagePath?>?sortBy=lastName&amp;direction=<?=$dirLastName?>">Last Name</a><?=$arrowLastName?></th>
                    <th style="text-align: left;"><a href="<?=$urlRoot?><?=$pagePath?>?sortBy=created&amp;direction=<?=$dirCreated?>">Created</a><?=$arrowCreated?></th>
                </tr>

                <?php
                foreach ($users as $i => $u)
                {
                    $email = $u['email'];
                    $dEmail = $email;
                    if (strlen($dEmail) > 34)
                        $dEmail = substr($dEmail, 0, 31) . '...';
                    $subscribedText = '';
                    if ($u['isSubscribed'] == 'n') $subscribedText = '<br>(unsubscribed)';
                    $firstName = $u['firstName'];
                    $lastName = $u['lastName'];
                    $created = date('n/j/y', strtotime($u['created'] . ' UTC'));

                    if ($i == 0) $row = 'first';
                    else if ($i % 2 == 0) $row = 'odd';
                    else $row = 'even';
                    ?>
                    <tr class="<?=$row?>">
                        <td style="padding-right: 1em;"><a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>"><?=myHtmlEntities($dEmail)?></a><?=$subscribedText?></td>
                        <td style="padding-right: 1em; vertical-align: top;"><?=myHtmlEntities($firstName)?></td>
                        <td style="padding-right: 1em; vertical-align: top;"><?=myHtmlEntities($lastName)?></td>
                        <td class="rightmost" style="vertical-align: top;"><?=myHtmlEntities($created)?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    private function confirmDeleteUser($email)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        if (hg('no'))
            relocateNow($urlRoot . $pagePath . '?email=' . urlencode($email));
        if (hg('yes'))
        {
            $um->deleteUser($email);
            relocateNow($urlRoot . $pagePath);
        }
        ob_start();
        ?>
        <h1>Are You Sure?</h1>
        <p>
            Do you really want to delete user <?=myHtmlEntities($email)?>?
        </p>
        <p>
            <a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>&amp;delete&amp;yes">Yes, delete</a>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>&amp;delete&amp;no">No, cancel</a>
        </p>
        <?php
        return ob_get_clean();
    }

    private function showUserOptions($email)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        $user = $um->getUser($email);
        if ($user === false) error404();
        if ($user['id'] != $GLOBALS['pizza']['user']['id'])
        {
            if (hg('delete')) return $this->confirmDeleteUser($email);
            else if (hg('subscribe')) return $this->subscribeUser($email);
            else if (hg('unsubscribe')) return $this->unsubscribeUser($email);
        }
        $firstName = $user['firstName'];
        $lastName = $user['lastName'];
        $created = date('F j, Y @ g:ia', strtotime($user['created'] . ' UTC'));
        $subscribed = $user['isSubscribed'];
        $theirGroups = $user['groups'];
        $theirGroupIds = array_keys($theirGroups);
        $allGroups = $um->getGroups();
        unset($allGroups[2]); // Remove 'Users' group from options; can't leave this group.
        ob_start();
        ?>
        <a href="<?=$urlRoot?><?=$pagePath?>">Back to All Users</a>
        <h1><?=myHtmlEntities($firstName . ' ' . $lastName)?></h1>
        <p>
            Email: <?=myHtmlEntities($email)?><br>
            Created: <?=myHtmlEntities($created)?><br>
            Subscribed:
            <?php
            if ($subscribed == 'y')
            {
                ?>
                Yes (<a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>&amp;unsubscribe">unsubscribe</a>)
                <?php
            }
            else
            {
                ?>
                No (<a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>&amp;subscribe">subscribe</a>)
                <?php
            }
            ?>
        </p>
        <h2>Groups</h2>
        <?php
        foreach ($allGroups as $id => $group)
        {
            $checked = in_array($id, $theirGroupIds) ? '&check;' : '&#9744;';
            $groupName = $group['name'];
            ?>
            <a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>&amp;toggleGroup=<?=urlencode($groupName)?>"><div style="display: inline-block; width: 1em;"><?=$checked?></div></a><?=myHtmlEntities($groupName)?><br>
            <?php
        }
        if ($user['id'] != $GLOBALS['pizza']['user']['id'])
        {
            ?>
            <p>
                <a href="<?=$urlRoot?><?=$pagePath?>?email=<?=urlencode($email)?>&amp;delete">Delete User</a>
            </p>
            <?php
        }
        return ob_get_clean();
    }

    private function subscribeUser($email)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        $um->subscribeUser($email);
        relocateNow($urlRoot . $pagePath . '?email=' . urlencode($email));
    }

    private function unsubscribeUser($email)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $um = $GLOBALS['pizza']['um'];
        $um->unsubscribeUser($email);
        relocateNow($urlRoot . $pagePath . '?email=' . urlencode($email));
    }
}
?>
