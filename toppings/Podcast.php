<?php
// Copyright 2020 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Podcast
{
    private $cm;
    private $tp;

    function __construct()
    {
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->tp = $GLOBALS['pizza']['toppings'];
    }

    function applyTopping($html)
    {
        return preg_replace_callback(
            '/\[(podcast)(]|\s+.*]|,.*])/sU',
            array($this, '_applyTopping'),
            $html
        );
    }

    private function _applyTopping($matches)
    {
        if (hg('feed')) return $this->showAsRss($matches);
        return $this->showAsHtml($matches);
    }

    private function getCoverArt()
    {
        $files = $this->cm->getFileInfo($GLOBALS['pizza']['pagePath']);
        if ($files === false) return false;
        foreach ($files as $f)
        {
            // Use first image found.
            if (($f['mimeType'] == 'image/jpeg')
                || ($f['mimeType'] == 'image/pjpeg')
                || ($f['mimeType'] == 'image/gif')
                || ($f['mimeType'] == 'image/png')
                || ($f['mimeType'] == 'image/svg+xml')
                || ($f['mimeType'] == 'image/webp'))
            {
                return $f['fileName'];
            }
        }
        return false;
    }

    private function getDescription($body)
    {
        // return $this->textOnly($body);
        $error = "Missing 'div data-description' plain text element.";
        if (preg_match_all("/<div\s+data-description[^>]*>([^<]+)<\/div>/i", $body, $matches))
        {
            $description = '';
            foreach ($matches[1] as $d) $description .= $d;
            return trim($description);
        }
        return $error;
    }

    private function getPodcasts($pagePath)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $subpages = $this->cm->getChildren($pagePath, 'page');
        // Augment subpages with 'audio' information if present, discard if not.
        $audioPages = array();
        foreach ($subpages as $k => $p)
        {
            $fileInfo = $this->cm->getFileInfo($GLOBALS['pizza']['cm']->pageIdToPagePath($p['id']));
            if ($fileInfo === false) continue;
            foreach ($fileInfo as $f)
            {
                $fileUrl = $urlRoot . $pagePath . $p['pageUri'] . '/' . $f['fileName'];
                $fileLength = $f['fileSize'];
                $fileType = $f['mimeType'];
                $views = $f['views'];
                if (substr($fileType, 0, 5) == 'audio')
                {
                    $subpages[$k]['audio']['url'] = $fileUrl;
                    $subpages[$k]['audio']['length'] = $fileLength;
                    $subpages[$k]['audio']['type'] = $fileType;
                    $subpages[$k]['audio']['views'] = $views;
                    $audioPages[$k] = $subpages[$k];
                    break;
                }
            }
        }
        unset($subpages);
        // Sort from newly created to oldest.
        $key = 'created';
        $direction = -1;
        uasort($audioPages,
            function ($a, $b) use($key, $direction)
            {
                if ($a[$key] < $b[$key]) return -$direction;
                if ($a[$key] > $b[$key]) return $direction;
                return 0;
            }
        );
        return $audioPages;
    }

    private function showAsHtml($matches)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $e = substr($matches[0], 1, -1);
        $subscribe = $this->tp->getString($e, 'subscribe', '');
        $subLinks = explode(';', $subscribe);
        if ($subLinks[0] == '') $subLinks = array();
        $subscriptions = array();
        foreach ($subLinks as $sub)
        {
            $sub = trim($sub);
            $subscriptions[] = explode('/', $sub, 2);
        }
        // Create a header link for RSS discovery.
        $rssTitle = 'Unknown';
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $GLOBALS['pizza']['page']['body'], $matches))
            $rssTitle = $matches[1];
        $rssLink = $urlRoot . $pagePath . '?feed';
        addLink('alternate', 'application/rss+xml', $rssTitle, $rssLink);
        ob_start();
        echo <<<P
<p>
Listen here or at:
P;
        foreach ($subscriptions as $sub)
        {
            $name = $sub[0];
            $url = $sub[1];
            echo <<<P
 <a href="$url" target="_blank">$name</a>,
P;
        }
        echo <<<P
 <a href="?feed" target="_blank">RSS</a>
</p>

P;
        $podcasts = $this->getPodcasts($pagePath);
        if (count($podcasts) == 0)
        {
            echo <<<P
<p>There are no podcasts at this time.</p>
P;
            return ob_get_clean();
        }
        foreach ($podcasts as $a)
        {
            $pageName = $a['pageName'];
            $mPageName = myHtmlEntities($pageName);
            $pageUri = $a['pageUri'];
            $created = $a['created'];
            $mCreated = myHtmlEntities(date('F j, Y', strtotime($created . ' UTC')));
            // $modified = $a['modified'];
            // $mModified = myHtmlEntities(date('F j, Y', strtotime($modified . ' UTC')));
            $views = '';
            $mode = '';
            if (isset($a['audio']))
            {
                if ($a['audio']['views'] == 1)
                    $views = ' (' . $a['audio']['views'] . ' download)';
                else
                    $views = ' (' . $a['audio']['views'] . ' downloads)';
            }
            else
            {
                $mode .= 'No Audio, ';
                $views = '';
            }
            if (!isAdministrator()) $views = '';
            if (substr($a['mode'], 0, 1) == 'n') $mode .= 'OFF, ';
            $mode = ' <span style="color: red;">' . substr($mode, 0, -2) . '</span>';
            echo <<<P
<p>
<a href="$urlRoot$pagePath$pageUri/">$mPageName</a><br>
<span style="font-size: smaller;">$mCreated{$mode}{$views}</span>
</p>

P;
        }
        return $this->tp->protect(rtrim(ob_get_clean()));
    }

    private function showAsRss($matches)
    {
        $e = substr($matches[0], 1, -1);
        $feedTitle = $this->tp->getString($e, 'title', 'Unknown');
        $feedAuthor = $this->tp->getString($e, 'author', $GLOBALS['pizza']['config']['siteName']);
        $feedCategory = $this->tp->getString($e, 'category', 'Leisure/Hobbies');
        $feedEmail = $this->tp->getString($e, 'email', $GLOBALS['pizza']['config']['adminEmail']);
        $feedExplicit = $this->tp->getString($e, 'explicit', 'No');
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $body = $GLOBALS['pizza']['page']['body'];
        if ($feedTitle != 'Unknown') $feedTitle = myHtmlEntities($feedTitle);
        else if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $body, $matches))
            $feedTitle = myHtmlEntities($matches[1]); // Apple wants HTML entities encoded.
        $feedLink = $urlRoot . $pagePath;
        $feedDescription = '<![CDATA[' . $this->getDescription($body) . ']]>';
        $feedCopyright = 'Copyright 2019-' . date('Y');
        $feedImage = $urlRoot . $pagePath . $this->getCoverArt();
        // Create iTunes categories.
        $categories = explode(';', $feedCategory);
        $s = '';
        foreach ($categories as $c)
        {
            $sub = explode('/', $c);
            if (count($sub) == 1)
                $s .= '<itunes:category text="' . myHtmlEntities($sub[0]) . '"/>' . "\n";
            else
            {
                $s .= '<itunes:category text="' . myHtmlEntities($sub[0]) . '">' . "\n";
                $s .= '    <itunes:category text="' . myHtmlEntities($sub[1]) . '"/>' . "\n";
                $s .= '</itunes:category>' . "\n";
            }
        }
        $categoriesXml = substr($s, 0, -1);
        $podcasts = $this->getPodcasts($pagePath);
        // Strip out those with no audio.
        foreach ($podcasts as $k => $v)
            if (!isset($v['audio'])) unset($podcasts[$k]);
        // Get most recent modified date as lastBuildDate; newest of page or any item.
        $lastBuildDate = $GLOBALS['pizza']['page']['modified'];
        foreach ($podcasts as $p)
            if ($p['modified'] > $lastBuildDate) $lastBuildDate = $p['modified'];
        $lastBuildDate = date('D, d M Y H:i:s', strtotime($lastBuildDate)) . ' UTC';

        $xml = <<<PODCAST_HEADER
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title>$feedTitle</title>
<link>$feedLink</link>
<description>$feedDescription</description>
<copyright>$feedCopyright</copyright>
<atom:link href="$feedLink?feed" rel="self" type="application/rss+xml"></atom:link>
<itunes:owner>
    <itunes:name>$feedTitle</itunes:name>
    <itunes:email>$feedEmail</itunes:email>
</itunes:owner>
$categoriesXml
<itunes:summary>$feedDescription</itunes:summary>
<itunes:author>$feedAuthor</itunes:author>
<itunes:image href="$feedImage"></itunes:image>
<itunes:block>No</itunes:block>
<itunes:explicit>$feedExplicit</itunes:explicit>
<language>en-us</language>
<image>
    <url>$feedImage</url>
    <title>$feedTitle</title>
    <link>$feedLink</link>
</image>
<lastBuildDate>$lastBuildDate</lastBuildDate>

PODCAST_HEADER;

        foreach ($podcasts as $p)
        {
            $title = $p['pageName'];
            $link = $urlRoot . $pagePath . $p['pageUri'] . '/';
            $description = '<![CDATA[' . $this->getDescription($p['body']) . ']]>';
            $enclosureUrl = $p['audio']['url'];
            $enclosureLength = $p['audio']['length'];
            $enclosureType = $p['audio']['type'];
            $pubDate = date('D, d M Y H:i:s', strtotime($p['created'])) . ' UTC';
            $xml .= <<<ITEM
<item>
    <title>$title</title>
    <link>$link</link>
    <description>$description</description>
    <enclosure url="$enclosureUrl" length="$enclosureLength" type="$enclosureType"></enclosure>
    <guid isPermaLink="false">$link</guid>
    <pubDate>$pubDate</pubDate>
</item>

ITEM;
        }

        $xml .= <<<PODCAST_FOOTER
</channel>
</rss>
PODCAST_FOOTER;

        $pageName = strtolower($GLOBALS['pizza']['page']['pageName']);
        header("Content-type: application/rss+xml");
        $fileName = $pageName != '__domain__' ? $pageName : 'podcast';
        header('Content-Disposition: attachment; filename="' . $fileName . '.rss"');
        echo $xml . "\n";
        exit();
        // return $xml . "\n";
    }

    private function textOnly($body)
    {
        // Remove [...] regions, and all HTML tags with content.
        $t = preg_replace(
            array('/\[[^\]]*\]/', '@<(\w+)\b.*?>.*?</\1>@si', '@<(\w+)\b.*?>@si'),
            array('', '', ''),
            $body
        );
        return trim($t);
    }
}
?>
