<?php
// Copyright 2023 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');

// Don't start a session for a file request.
if (substr($GLOBALS['pizza']['page']['kind'], -5) == '-file')
{
    if (substr($GLOBALS['pizza']['page']['pageName'], -4) == '.css')
        header('Content-Type: text/css');
    else if (substr($GLOBALS['pizza']['page']['pageName'], -3) == '.js')
        header('Content-Type: application/javascript');
    echo $GLOBALS['pizza']['page']['body'];
    exit();
}

beginSession();
$urlRoot = $GLOBALS['pizza']['urlRoot'];
$pagePath = $GLOBALS['pizza']['pagePath'];

// If Sandbox and not admin, do 404.
if ((substr(strtolower($pagePath), 0, 9) == '/sandbox/') && !isAdministrator())
    error404();
// If page is turned off and not admin and not owner, do 404.
if ((substr($GLOBALS['pizza']['page']['mode'], 0, 1) != 'y')
    && !isOwner() && !isAdministrator())
    error404();

// If page is not readable by you, do unauthorized.
if (!isReadable()) errorUnauthorized();

$userId = $GLOBALS['pizza']['user']['id'];
if (($GLOBALS['pizza']['page']['kind'] == 'page')
    && (hg('editPage') || hs("EditPage_$pagePath")))
{
    // If attempting to edit the page without write permission, do unauthorized.
    if (!isWritable() || ($userId == 0)) errorUnauthorized();
    if ((lockedBy() == 0) && hs("EditPage_$pagePath"))
    {
        // This user had the lock but lost it to another user who changed the
        // page.  Clear this user's stale state and show page-changed error.
        sc("EditPage_$pagePath");
        errorPageChanged();
    }
    if ((lockedBy() > 0) && (lockedBy() != $userId))
    {
        // Another user has the lock.  Clear state and show page-locked error.
        sc("EditPage_$pagePath");  // If any.
        errorPageLocked();
    }
    // Grant/renew the lock.
    lockPage();

    rememberPrevious();
    if (!hs("EditPage_$pagePath"))
    {
        require_once('EditPage.php');
        $pe = new EditPage();
    }
    else $pe = sg("EditPage_$pagePath");
    // jQuery UI tabs has issue with query parameters being present. So redirect
    // back here if "?editPage" but without query parameters.
    if (hg('editPage')) relocateNow($urlRoot . $pagePath);
    $html = applyTheme($pe->getHtml());
    echo $html;
    exit();
}

if ($GLOBALS['pizza']['page']['kind'] == 'reserved')
{
    // If the body of this reserved page isn't a single word describing a
    // class name, then do a 404.
    if (!preg_match('/^\S+$/', $GLOBALS['pizza']['page']['body']))
        error404();
    // The body is assumed to be a class name.  Create an instance of it and
    // call its assumed getHtml() method.
    rememberPrevious();
    $className = $GLOBALS['pizza']['page']['body'];
    if (!hs($className))
    {
        require_once($className . '.php');
        $c = new $className();
    }
    else $c = sg($className);
    $body = '';
    if (!hs("PageActions_$pagePath"))
    {
        require_once('PageActions.php');
        $pa = new PageActions();
    }
    else $pa = sg("PageActions_$pagePath");
    $body .= $pa->getHtml();
    $body .= $c->getHtml();
    $html = applyTheme($body);
    echo $html;
    exit();
}

rememberPrevious();
// Increment the view count for this page.
$GLOBALS['pizza']['cm']->incrementViews();
$body = '';
if (isWritable())
{
    if (!hs("PageActions_$pagePath"))
    {
        require_once('PageActions.php');
        $pa = new PageActions();
    }
    else $pa = sg("PageActions_$pagePath");
    $body .= $pa->getHtml();
}
$body .= $GLOBALS['pizza']['toppings']->applyToppings($GLOBALS['pizza']['page']['body']);

// Show visibility flags if non-temp page and if owner or admin.
if (!$GLOBALS['pizza']['cm']->isTempPage($pagePath) && (isOwner() || isAdministrator()))
{
    $visibilityMessage = '';
    if (substr($GLOBALS['pizza']['page']['mode'], 0, 1) != 'y')
        $visibilityMessage .= '<span style="background-color: #000000; color: #ff5050;">&nbsp;Turned Off&nbsp;</span>&nbsp;&nbsp;';
    if (substr($GLOBALS['pizza']['page']['mode'], 1, 1) != 'y')
        $visibilityMessage .= '<span style="background-color: #000000; color: #ff5050;">&nbsp;Unlisted&nbsp;</span>&nbsp;&nbsp;';
    if ($visibilityMessage != '')
        $body = $visibilityMessage . "\n" . $body;
}

$html = applyTheme($body);
// $html = prettify($html);
// $html = str_replace('</body>', getDebugInfo() . '</body>', $html);
echo $html;
?>
