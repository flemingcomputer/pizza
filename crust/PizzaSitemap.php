<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
class PizzaSitemap
{
    private $id;
    private $marker;
    private $offNote;
    private $padlock;
    private $unlisted;

    function __construct()
    {
        $this->id = 'PizzaSitemap';
        $this->marker = '<div class="marker marker-down"></div>';
        $this->offNote = '<span style="color: red;"> off</span>';
        $this->padlock = template('padlock.svg');
        $this->unlisted = '<span style="color: red;"> unlisted</span>';
    }

    function getHtml()
    {
        $html = "<h1>Sitemap</h1>\n";
        $html .= "<ul id=\"sitemap\">\n";
        $sitemap = $GLOBALS['pizza']['cm']->getSitemap();
        foreach ($sitemap as $node)
            $html .= $this->printSitemapNode($node);
        $html .= "</ul>\n";
        return $html;
    }

    private function printSitemapNode($node, $indent = 4, $prefix = '')
    {
        $marker = '';
        if (isset($node['children']) && (count($node['children']) > 0))
            $marker = $this->marker;
        $name = $node['pageName'];
        $uri = $node['pageUri'];
        if ($name == '__domain__') $name = $GLOBALS['pizza']['config']['siteName'];
        if ($uri == '__domain__') $uri = '';
        $uri = $prefix . '/' . $uri . '/';
        $uri = str_replace('//', '/', $uri);
        $p = str_repeat(' ', $indent);
        $mName = myHtmlEntities($name);
        $html = "$p<li>$marker<a href=\"{$GLOBALS['pizza']['urlRoot']}{$uri}\">" . $mName . '</a>';
        if (isset($node['restricted'])) $html .= $this->padlock;
        if (substr($node['mode'], 0, 1) == 'n') $html .= $this->offNote;
        if (substr($node['mode'], 1, 1) == 'n') $html .= $this->unlisted;
        if (isset($node['children']) && (count($node['children']) > 0))
        {
            $html .= "\n$p    <ul>\n";
            // Then print its children nodes.
            foreach ($node['children'] as $child)
                $html .= $this->printSitemapNode($child, $indent + 8, $uri);
            $html .= "$p    </ul>\n" . str_repeat(' ', $indent);
        }
        $html .= "</li>\n";
        return $html;
    }
}
?>
