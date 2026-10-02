<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
if (!isset($GLOBALS['pizza']['page']['breadcrumbs'])) return '';
$breadcrumbs = $GLOBALS['pizza']['page']['breadcrumbs'];
if (count($breadcrumbs) == 1) return ''; // Don't show if at top page.
// Don't show if contains a reserved page.
foreach ($breadcrumbs as $b)
    if ($b['kind'] == 'reserved') return '';
if (preg_match('/temp-[\d]{8}/', $breadcrumbs[count($breadcrumbs) - 1]['pageUri']))
    array_pop($breadcrumbs);
$url = $GLOBALS['pizza']['urlRoot'] . '/';
$html = '<ul id="breadcrumbs">';
$n = count($breadcrumbs);
for ($i = 0; $i < $n; $i++)
{
    $pageName = $breadcrumbs[$i]['pageName'];
    $pageUri = $breadcrumbs[$i]['pageUri'];
    if ($pageName == '__domain__') $pageName = 'Home';
    else $url .= $pageUri . '/';
    if ($i < $n - 1)
        $html .= '<li><a href="' . $url . '">' . myHtmlEntities($pageName) . '</a><ul>';
    else
        $html .= '<li>' . myHtmlEntities($pageName);
}
for ($i = 0; $i < $n; $i++)
    $html .= '</li></ul>';
$html .= "\n";
echo $html;
?>
