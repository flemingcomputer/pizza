<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');

function printSitemapNode($node, $prefix = '')
{
    $uri = $node['pageUri'];
    $modified = date('c', strtotime($node['modified'] . ' UTC'));
    if ($uri == '__domain__') $uri = '';
    $uri = $prefix . '/' . $uri . '/';
    $uri = str_replace('//', '/', $uri);
    $xml = "<url>\n";
    $xml .= '    <loc>' . $GLOBALS['pizza']['urlRoot'] . "$uri</loc>\n";
    $xml .= "    <lastmod>$modified</lastmod>\n";
    $xml .= "</url>\n";
    if (isset($node['children']) && (count($node['children']) > 0))
    {
        // Then print its children nodes.
        foreach ($node['children'] as $child)
            $xml .= printSitemapNode($child, $uri);
    }
    return $xml;
}

header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
$sitemap = $GLOBALS['pizza']['cm']->getSitemap();
foreach ($sitemap as $node)
    echo printSitemapNode($node);
echo '</urlset>';
?>
