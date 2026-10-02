<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('password.php');

class UserManager
{
    private $t;

    function __construct()
    {
        $this->t = $GLOBALS['pizza']['t'];
        $this->purgeOldInfo();
    }

    function addGroup($name, $description)
    {
        $mName = $this->t->escapeString($name);
        $mDescription = $this->t->escapeString($description);
        $q = "
            INSERT INTO `groups` (`name`, `description`)
            VALUES ('$mName', '$mDescription')
        ";
        $this->t->query($q);
    }

    function addUser($email, $firstName, $lastName, $password, $invite)
    {
        $mEmail = $this->t->escapeString($email);
        $mFirstName = $this->t->escapeString($firstName);
        $mLastName = $this->t->escapeString($lastName);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $mHash = $this->t->escapeString($hash);
        $mInvite = $this->t->escapeString($invite);
        // Add the user.
        $q = "
            INSERT INTO users
            (email, firstName, lastName, `password`, invite, created, isSuspended, isSubscribed, notified, settings)
            VALUES ('$mEmail', '$mFirstName', '$mLastName', '$mHash', '$mInvite', UTC_TIMESTAMP(), '?', 'n', UTC_TIMESTAMP - INTERVAL 24 HOUR, '')
        ";
        $this->t->query($q);
        $userId = $this->t->insert_id;
        // Generate a new confirmation key for this user id.
        $key = $this->generateKey($userId, 'confirm_account');
        return $this->mailAccountCreated($email, $key);
    }

    function confirmAccount($key)
    {
        $mKey = $this->t->escapeString($key);
        $q = "
            SELECT id, email FROM users
            JOIN `keys` ON users.id = `keys`.userId
            WHERE `keys`.`key` = '$mKey'
            AND `keys`.door = 'confirm_account'
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        // Enable this new user account.
        $r = $this->t->getNextRecord();
        $id = $r['id'];
        $email = $r['email'];
        $q = "
            UPDATE users SET isSuspended = 'n', isSubscribed = 'y'
            WHERE id = $id
        ";
        $this->t->query($q);
        // Remove the key.
        $q = "
            DELETE FROM `keys`
            WHERE `keys`.userId = $id
            AND `keys`.door = 'confirm_account'
        ";
        $this->t->query($q);
        return $this->mailAccountConfirmed($email);
    }

    // function deleteAccount($key)
    // {
    //     $mKey = $this->t->escapeString($key);
    //     $q = "
    //         SELECT email FROM users
    //         JOIN `keys` ON users.id = `keys`.userId
    //         WHERE `keys`.`key` = '$mKey'
    //         AND `keys`.door = 'delete_account'
    //     ";
    //     $this->t->query($q);
    //     if ($this->t->num_rows == 0) return false;
    //     $r = $this->t->getNextRecord();
    //     $email = $r['email'];
    //     $q = "
    //         DELETE FROM users, `keys`
    //         USING users JOIN `keys` ON users.id = `keys`.userId
    //         WHERE `keys`.`key` = '$mKey'
    //         AND `keys`.door = 'delete_account'
    //     ";
    //     $this->t->query($q);
    //     return $this->mailAccountDeleted($email);
    // }

    function deleteGroup($groupName)
    {
        $mGroupName = $this->t->escapeString($groupName);
        $q = "
            DELETE groupMembers FROM `groups`
            JOIN groupMembers ON `groups`.id = groupMembers.groupId
            WHERE `groups`.`name` = '$mGroupName'
        ";
        $this->t->query($q);
        $q = "DELETE FROM `groups` WHERE `name` = '$mGroupName'";
        $this->t->query($q);
    }

    function deleteUser($email)
    {
        $mEmail = $this->t->escapeString($email);
        $q = "SELECT id FROM users WHERE email = '$mEmail'";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        $userId = $r['id'];
        $q = "DELETE FROM groupMembers WHERE userId = $userId";
        $this->t->query($q);
        $q = "DELETE FROM `keys` WHERE userId = $userId";
        $this->t->query($q);
        $q = "DELETE FROM users WHERE id = $userId";
        $this->t->query($q);
        return true;
    }

    function editGroup($oldName, $name, $description)
    {
        $mOldName = $this->t->escapeString($oldName);
        $mName = $this->t->escapeString($name);
        $mDescription = $this->t->escapeString($description);
        $q = "
            UPDATE `groups` SET `name` = '$mName', `description` = '$mDescription'
            WHERE `name` = '$mOldName'
        ";
        $this->t->query($q);
    }

    function editUser($oldEmail, $newEmail, $newFirstName, $newLastName, $isSubscribed)
    {
        // Get current user info before changing.
        $mOldEmail = $this->t->escapeString($oldEmail);
        $q = "
            SELECT firstName, lastName FROM users
            WHERE email = '$mOldEmail'
        ";
        $this->t->query($q);
        $n = $this->t->num_rows;
        if ($n != 1) return false;
        $r = $this->t->getNextRecord();
        $oldFirstName = $r['firstName'];
        $oldLastName = $r['lastName'];
        // Update user info.
        $mNewEmail = $this->t->escapeString($newEmail);
        $mNewFirstName = $this->t->escapeString($newFirstName);
        $mNewLastName = $this->t->escapeString($newLastName);
        $subscribedFlag = $isSubscribed ? 'y' : 'n';
        $q = "
            UPDATE users
            SET email = '$mNewEmail', firstName = '$mNewFirstName', lastName = '$mNewLastName',
                isSubscribed = '$subscribedFlag'
            WHERE email = '$mOldEmail'
        ";
        $this->t->query($q);
        $this->mailAccountEdited($oldEmail, $oldFirstName, $oldLastName,
            $newEmail, $newFirstName, $newLastName, $isSubscribed);
        return true;
    }

    function getGroup($groupName)
    {
        $mGroupName = $this->t->escapeString($groupName);
        $q = "SELECT * FROM `groups` WHERE `name` = '$mGroupName'";
        $this->t->query($q);
        $n = $this->t->num_rows;
        if ($n != 1) return false;
        return $this->t->getNextRecord();
    }

    function getGroupMembers($groupName)
    {
        $mGroupName = $this->t->escapeString($groupName);
        $q = "
            SELECT * FROM users WHERE id IN (
                SELECT userId FROM groupMembers
                JOIN `groups` ON groupMembers.groupId = `groups`.id
                WHERE `groups`.`name` = '$mGroupName'
            )
            ORDER BY lastName, firstName, email
        ";
        $this->t->query($q);
        $n = $this->t->num_rows;
        $users = array();
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            unset($r['password']);
            $users[] = $r;
        }
        return $users;
    }

    function getGroups()
    {
        $q = "SELECT * FROM `groups` ORDER BY `name`";
        $this->t->query($q);
        $n = $this->t->num_rows;
        $groups = array();
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            $groups[$r['id']] = $r;
        }
        return $groups;
    }

    function getUser($emailOrId)
    {
        if (is_numeric($emailOrId))
        {
            $where = "id = $emailOrId";
        }
        else
        {
            $mEmail = $this->t->escapeString($emailOrId);
            $where = "email = '$mEmail'";
        }
        $q = "SELECT * FROM users WHERE $where";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $user = $this->t->getNextRecord();
        $userId = $user['id'];
        // Assemble this user's keys (if any) into an array.
        $q = "SELECT `key`, created FROM `keys` WHERE userId = $userId";
        $this->t->query($q);
        $keys = array();
        $n = $this->t->num_rows;
        for ($i = 0; $i < $n; $i++)
            $keys[] = $this->t->getNextRecord();
        $user['keys'] = $keys;
        // Assemble this user's groups into an array.
        $q = "
            SELECT id, `name`
            FROM `groups`
            LEFT JOIN groupMembers ON `groups`.id = groupMembers.groupId
            WHERE userId = $userId
            OR `groups`.id = 2
        ";
        $this->t->query($q);
        $groups = array();
        $n = $this->t->num_rows;
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            $groups[$r['id']] = $r['name'];
        }
        $user['groups'] = $groups;
        return $user;
    }

    function getUsers($orderBy = '', $direction = 'a')
    {
        if ($direction == 'd') $direction = 'DESC';
        else $direction = 'ASC';

        if ($orderBy == 'email')
            $orderBy = "ORDER BY email $direction";
        else if ($orderBy == 'firstName')
            $orderBy = "ORDER BY firstName $direction, lastName $direction";
        else if ($orderBy == 'lastName')
            $orderBy = "ORDER BY lastName $direction, firstName $direction";
        else
            $orderBy = "ORDER BY created $direction, lastName $direction, firstName $direction";
        $q = "SELECT email FROM users $orderBy";
        $this->t->query($q);
        $emails = array();
        $n = $this->t->num_rows;
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            $emails[] = $r['email'];
        }
        $users = array();
        foreach ($emails as $email)
            $users[] = $this->getUser($email);
        return $users;
    }

    function groupExists($groupName)
    {
        $mGroupName = $this->t->escapeString($groupName);
        $q = "SELECT COUNT(*) FROM `groups` WHERE `name` = '$mGroupName'";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        return $r['COUNT(*)'] > 0;
    }

    function initiateResetPassword($email)
    {
        $mEmail = $this->t->escapeString($email);
        $q = "SELECT id FROM users WHERE email = '$mEmail'";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        $userId = $r['id'];
        // Generate a new reset-password key for this user id.
        $key = $this->generateKey($userId, 'reset_password');
        return $this->mailResetPasswordInstructions($email, $key);
    }

    function resetPassword($key, $password)
    {
        $mKey = $this->t->escapeString($key);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $mHash = $this->t->escapeString($hash);
        $q = "
            SELECT userId FROM `keys`
            WHERE `key` = '$mKey' AND door = 'reset_password'
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        $userId = $r['userId'];
        $q = "SELECT email FROM users WHERE id = $userId";
        $this->t->query($q);
        if ($this->t->num_rows == 0) return false;
        $r = $this->t->getNextRecord();
        $email = $r['email'];
        $q = "UPDATE users SET `password` = '$mHash' WHERE id = $userId";
        $this->t->query($q);
        $q = "
            DELETE FROM `keys`
            WHERE `key` = '$mKey' AND door = 'reset_password'
        ";
        $this->t->query($q);
        return $this->mailPasswordIsReset($email);
    }

    function subscribeUser($email)
    {
        $mEmail = $this->t->escapeString($email);
        $q = "UPDATE users SET isSubscribed = 'y' WHERE email = '$mEmail'";
        $this->t->query($q);
    }

    function toggleMember($groupName, $email)
    {
        $mGroupName = $this->t->escapeString($groupName);
        $mEmail = $this->t->escapeString($email);
        $q = "SELECT id FROM `groups` WHERE `name` = '$mGroupName'";
        $this->t->query($q);
        if ($this->t->num_rows != 1) return false;
        $r = $this->t->getNextRecord();
        $groupId = $r['id'];
        $q = "SELECT id FROM users WHERE email = '$mEmail'";
        $this->t->query($q);
        if ($this->t->num_rows != 1) return false;
        $r = $this->t->getNextRecord();
        $userId = $r['id'];
        $q = "
            SELECT * FROM groupMembers
            WHERE groupId = $groupId AND userId = $userId
        ";
        $this->t->query($q);
        if ($this->t->num_rows == 0)
        {
            // Add user to group.
            $q = "
                INSERT INTO groupMembers (groupId, userId)
                VALUES ($groupId, $userId)
            ";
            $this->t->query($q);
        }
        else
        {
            // Remove user from group.
            // But...you can't remove the last administrator.
            if ($groupId == 1)
            {
                $q = "SELECT * FROM groupMembers WHERE groupId = 1";
                $this->t->query($q);
                if ($this->t->num_rows < 2) return false;
            }
            // Remove them.
            $q = "
                DELETE FROM groupMembers
                WHERE groupId = $groupId AND userId = $userId
            ";
            $this->t->query($q);
        }
    }

    function unsubscribeUser($email)
    {
        $mEmail = $this->t->escapeString($email);
        $q = "UPDATE users SET isSubscribed = 'n' WHERE email = '$mEmail'";
        $this->t->query($q);
    }

    function userExists($email)
    {
        $mEmail = $this->t->escapeString($email);
        $q = "SELECT COUNT(*) FROM users WHERE email = '$mEmail'";
        $this->t->query($q);
        $r = $this->t->getNextRecord();
        return $r['COUNT(*)'] == 1;
    }

    function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    private function generateKey($userId, $door)
    {
        while (true)
        {
            $key = '';
            for ($i = 1; $i <= 16; $i++)
                $key .= str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
            $mKey = $this->t->escapeString($key);
            $q = "SELECT `key` FROM `keys` WHERE `key` = '$mKey'";
            $this->t->query($q);
            if ($this->t->num_rows == 0) break;
        }
        $mKey = $this->t->escapeString($key);
        $mDoor = $this->t->escapeString($door);
        $q = "DELETE FROM `keys` WHERE userId = $userId AND door = '$mDoor'";
        $this->t->query($q);
        $q = "
            INSERT INTO `keys` (userId, `key`, door, created)
            VALUES ($userId, '$mKey', '$mDoor', UTC_TIMESTAMP())
        ";
        $this->t->query($q);
        return $key;
    }

    private function mailAccountConfirmed($email)
    {
        $user = $this->getUser($email);
        if ($user === false) return false;
        $firstName = $user['firstName'];
        $lastName = $user['lastName'];
        $invite = $user['invite'];
        if ($invite != '') $invite = "\nInvite Code: $invite";
        // Send the new user an account-confirmed email.
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        ob_start();
        echo <<<TEXT
Your account with $siteName is confirmed!

Your Email: $email
Your Name: $firstName $lastName{$invite}

Log in to your account using this link:

$urlRoot/login
TEXT;
        $bodyText = ob_get_clean();
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        $mt->setTo("$firstName $lastName <$email>");
        $mt->setSubject("Account Confirmed!");
        $mt->setBody($bodyText);
        $result = $mt->send();
        // Now send the admin a new user sign-up notice.
        ob_start();
        echo <<<TEXT
A new user has signed up with $siteName.

Their Email: $email
Their Name: $firstName $lastName{$invite}
TEXT;
        $bodyText = ob_get_clean();
        $mt->setReplyTo("$firstName $lastName <$email>");
        $mt->setTo($GLOBALS['pizza']['config']['adminEmail']);
        $mt->setSubject("New User at {$GLOBALS['pizza']['config']['siteName']}");
        $mt->setBody($bodyText);
        $mt->send();
        // Success if the new user's notification email sent.
        return $email;
    }

    private function mailAccountCreated($email, $key)
    {
        $user = $this->getUser($email);
        if (($user === false) || empty($user['keys'])) return false;
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $firstName = $user['firstName'];
        $lastName = $user['lastName'];
        $invite = $user['invite'];
        if ($invite != '') $invite = "\nInvite Code: $invite";
        // Send the new user a confirmation request email.
        ob_start();
        echo <<<TEXT
Thank you for signing up with $siteName!

Your Email: $email
Your Name: $firstName $lastName{$invite}

You must confirm the creation of this account by following the link below:

$urlRoot/signup?confirmAccount=$key

If you did not sign up for this account, please disregard this email and the account will be deleted automatically.
TEXT;
        $bodyText = ob_get_clean();
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        $mt->setTo("$firstName $lastName <$email>");
        $mt->setSubject("Confirm account with {$GLOBALS['pizza']['config']['siteName']}");
        $mt->setBody($bodyText);
        return $mt->send();
    }

    private function mailAccountEdited($oldEmail, $oldFirstName, $oldLastName,
        $newEmail, $newFirstName, $newLastName, $isSubscribed)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $adminEmail = $GLOBALS['pizza']['config']['adminEmail'];
        $emailNote = $oldEmail;
        if ($oldEmail != $newEmail) $emailNote = "$newEmail (was $oldEmail)";
        $nameNote = "$oldFirstName $oldLastName";
        $subscribedNote = $isSubscribed ? 'Yes' : 'No';
        if (($oldFirstName != $newFirstName) || ($oldLastName != $newLastName))
            $nameNote = "$newFirstName $newLastName (was $oldFirstName $oldLastName)";
        ob_start();
        echo <<<TEXT
Your profile at $siteName has been updated!

Your Email: $emailNote
Your Name: $nameNote
Subscribed to email notifications: $subscribedNote

If you did not make these changes to your profile, please email $adminEmail for assistance.
TEXT;
        $bodyText = ob_get_clean();
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        $mt->setTo("$newFirstName $newLastName <$newEmail>");
        $mt->setSubject("Profile updated at {$GLOBALS['pizza']['config']['siteName']}");
        $mt->setBody($bodyText);
        $result = $mt->send();
        if ($oldEmail != $newEmail)
        {
            $mt->setTo("$oldFirstName $oldLastName <$oldEmail>");
            $mt->setBody($bodyText);
            $result = $mt->send() && $result;
        }
        return $result;
    }

//     private function mailAccountDeleted($email)
//     {
//         $siteName = $GLOBALS['pizza']['config']['siteName'];
//         ob_start();
//         echo <<<TEXT
// The account on $siteName that was created using your email address ($email) has been deleted.  We are sorry that someone attempted to use your email address to sign up with us.
// TEXT;
//         $bodyText = ob_get_clean();
//         require_once('MailTools.php');
//         $mt = new MailTools();
//         $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
//         $mt->setTo($email);
//         $mt->setSubject("{$GLOBALS['pizza']['config']['siteName']}:  Account Deleted");
//         $mt->setBody($bodyText);
//         $result = $mt->send();
//         return $result;
//     }

    private function mailPasswordIsReset($email)
    {
        $user = $this->getUser($email);
        if ($user === false) return false;
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $firstName = $user['firstName'];
        $lastName = $user['lastName'];
        ob_start();
        echo <<<TEXT
You successfully reset your password on $siteName.

Your Email: $email
Your Name: $firstName $lastName

Use your account by logging in at:

$urlRoot/login
TEXT;
        $bodyText = ob_get_clean();
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        $mt->setTo("$firstName $lastName <$email>");
        $mt->setSubject("{$GLOBALS['pizza']['config']['siteName']}:  Password Has Been Reset");
        $mt->setBody($bodyText);
        $result = $mt->send();
        return $result;
    }

    private function mailResetPasswordInstructions($email, $key)
    {
        $user = $this->getUser($email);
        if (($user === false) || empty($user['keys'])) return false;
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $firstName = $user['firstName'];
        $lastName = $user['lastName'];
        ob_start();
        echo <<<TEXT
It appears you have requested your password be reset on $siteName.

Your Email: $email
Your Name: $firstName $lastName

Follow the link below to reset your password:

$urlRoot/login?reset&key=$key

If you did not make this request, simply delete this email.
TEXT;
        $bodyText = ob_get_clean();
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        $mt->setTo("$firstName $lastName <$email>");
        $mt->setSubject("Reset Password on {$GLOBALS['pizza']['config']['siteName']}");
        $mt->setBody($bodyText);
        $result = $mt->send();
        return $result;
    }

    private function purgeOldInfo()
    {
        // Delete sign-up attempts older than 24 hours.
        $q = "
            DELETE FROM users
            WHERE isSuspended = '?'
            AND UTC_TIMESTAMP() - INTERVAL 24 HOUR > created
        ";
        $this->t->query($q);
        // Delete account keys that are older than 24 hours.
        $q = "
            DELETE FROM `keys`
            WHERE UTC_TIMESTAMP() - INTERVAL 24 HOUR > created
        ";
        $this->t->query($q);
    }
}
?>
