<?php
// Copyright 2018 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
beginSession();
$urlRoot = $GLOBALS['pizza']['urlRoot'];
$currentPath = currentPath();
if (!isReadable($currentPath)
    || (strtolower($currentPath) == '/login')
    || (strtolower($currentPath) == '/profile/')
    || (strtolower(substr($currentPath, 0, 10)) == '/settings/')
    || (strtolower($currentPath) == '/signup'))
    $currentPath = '/';
logout();
$json['logout'] = true;
$json['currentUrl'] = $urlRoot . $currentPath;
echo json_encode($json);
?>
