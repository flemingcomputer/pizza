<?php
// Copyright 2018 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
beginSession();
$v = $GLOBALS['pizza']['v'];
$v->setMethod('post');
$v->reset();
$json['name'] = false;
$email = $v->checkEmail('email');
$password = $v->checkLength('password', 8, 50);
if ($v->error)
{
    sleep(3);
    echo json_encode($json);
    exit();
}
$um = $GLOBALS['pizza']['um'];
$user = $um->getUser($email['value']);
if (($user === false)
    || !$um->verifyPassword($password['value'], $user['password']))
{
    sleep(3);
    echo json_encode($json);
    exit();
}
if ($user['isSuspended'] != 'n')
{
    // The user exists but is either unconfirmed ('?') or suspended ('y').
    sleep(3);
    echo json_encode($json);
    exit();
}
login($user);
$json['name'] = $user['firstName'];
echo json_encode($json);
?>
