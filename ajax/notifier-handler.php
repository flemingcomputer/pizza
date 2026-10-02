<?php
// Copyright 2022 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'))
    error404();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_POST['path']))
{
    echo json_encode(array(
        'result' => false,
        'error' => 'path missing'
    ));
    exit();
}
$pagePath = strlen($_POST['path']) > 250 ? '' : $_POST['path'];
beginSession();
if (($pagePath == '') || !isAdministrator())
{
    echo json_encode(array(
        'result' => false,
        'error' => "unauthorized"
    ));
    exit();
}

$urlRoot = $GLOBALS['pizza']['urlRoot'];
if (!$GLOBALS['pizza']['cm']->hasPage($pagePath))
{
    echo json_encode(array(
        'result' => false,
        'error' => "invalid page path"
    ));
    exit();
}

function initialize()
{
    $themes = $GLOBALS['pizza']['cm']->getThemes();
    $themes = array_values(array_filter($themes, function($t) {
        // return true;
        return substr($t, 0, 5) == 'email';
    }));
    $json = array(
        'result' => true,
        'test-emails' => $GLOBALS['pizza']['user']['email'],
        'themes' => $themes
    );
    echo json_encode($json);
    exit();
}

function sendAll($altUrl)
{
    global $urlRoot, $pagePath;
    // Start the NotificationManager daemon.
    $docRoot = $GLOBALS['pizza']['docRoot'];
    $theme = $_POST['theme'] ?? false;
    if ($theme)
        exec("php $docRoot/bin/NotificationManager.php \"$urlRoot\" all $theme \"$pagePath\" \"$altUrl\" >/dev/null 2>&1 &");
    echo json_encode(array(
        'result' => true
    ));
    exit();
}

function sendTest($emails, $altUrl)
{
    global $urlRoot, $pagePath;
    // Start the NotificationManager daemon.
    $docRoot = $GLOBALS['pizza']['docRoot'];
    $theme = $_POST['theme'] ?? false;
    if ($theme)
        exec("php $docRoot/bin/NotificationManager.php \"$urlRoot\" test $theme \"$pagePath\" \"$altUrl\" $emails >/dev/null 2>&1 &");
    echo json_encode(array(
        'result' => true
    ));
    exit();
}

$mode = $_POST['mode'] ?? '';
$testEmails = $_POST['testEmails'] ?? '';
$altUrl = $_POST['altUrl'] ?? '';
if ($altUrl == '') $altUrl = 'none';
if ($mode == 'initialize') initialize();
if ($mode == 'all') sendAll($altUrl);
if (($mode == 'test') && ($testEmails != '')) sendTest($testEmails, $altUrl);

echo json_encode(array(
    'result' => false,
    'error' => "invalid usage"
));
?>
