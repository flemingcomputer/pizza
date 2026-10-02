<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Tiles
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
            '/\[(tiles=)([^]]+])/sU',
            array($this, '_applyTopping'),
            $html
        );
    }

    private function _applyTopping($matches)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $e = substr($matches[0], 1, -1);
        $subpageList = explode('tiles=', $e);
        $subpageList = array_map('trim', explode(',', $subpageList[1]));
        foreach ($subpageList as $k => $s)
            if ($s == '') unset($subpageList[$k]);
        if (empty($subpageList)) return '';
        return $this->tp->protect($this->showSubpages($subpageList));
    }

    private function showSubpages($subpageList)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        addStyle("$urlRoot/toppings/Tiles/default.css");
        addJavaScript("$urlRoot/toppings/Tiles/Tiles.js");
        $subpages = $this->cm->getChildren($pagePath, 'page');
        foreach ($subpages as $k => $subpage)
        {
            // Get the subpage's files.
            $files = $this->cm->getFileInfo($GLOBALS['pizza']['cm']->pageIdToPagePath($subpage['id']));
            if ($files === false) $files = array();
            $subpages[$k]['files'] = $files;
        }
        $newSubpages = array();
        foreach ($subpageList as $s1)
        {
            $s1 = strtolower($s1);
            foreach ($subpages as $sp)
            {
                $s2 = strtolower($sp['pageName']);
                if ($s1 == $s2) $newSubpages[] = $sp;
            }
        }
        $images = array();
        foreach ($newSubpages as $i)
        {
            $mode = $i['mode'];
            $pageName = $i['pageName'];
            $pageUri = $i['pageUri'];
            $itemUrl = $urlRoot . $pagePath . $pageUri . '/';
            $x = 300; $y = 300;
            $slide = "$urlRoot/images/rounded_square.svg";
            foreach ($i['files'] as $f)
            {
                // Use first image found and break.
                if (($f['mimeType'] == 'image/jpeg')
                    || ($f['mimeType'] == 'image/pjpeg')
                    || ($f['mimeType'] == 'image/gif')
                    || ($f['mimeType'] == 'image/png')
                    || ($f['mimeType'] == 'image/svg+xml')
                    || ($f['mimeType'] == 'image/webp'))
                {
                    $mFileName = myHtmlEntities($f['fileName']);
                    if ($f['dimensions'] != '')
                        list($x, $y) = explode('x', $f['dimensions']);
                    $slide = $pageUri . "/$mFileName?width=300&amp;height=300";
                    break;
                }
            }
            $description = '<span style="font-size: smaller; font-weight: bold;">' . $pageName . '</span>';
            $images[] = array(
                'slide' => $slide,
                'height' => $y,
                'width' => $x,
                'caption' => $pageName,
                'description' => $description,
                'url' => $itemUrl,
                'mode' => $mode
            );
        }
        return trim($this->showSubpagesGrid($images));
    }

    private function showSubpagesGrid($slides)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        ob_start();
        ?>
        <div class="wait"></div>
        <div style="position: relative;">
        <ul class="store-items">
        <?php
        foreach ($slides as $i => $s)
        {
            $slide = $s['slide'];
            $sw = $s['width'];
            $sh = $s['height'];
            $caption = $s['caption'];
            $description = $s['description'];
            $url = $s['url'];
            $mode = $s['mode'];
            $altText = myHtmlEntities($caption);
            ?>
            <li data-index="<?=$i?>">
                <div class="item-content">
                    <a href="<?=$url?>"><span style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></span></a>
                    <img alt="<?=$altText?>" class="item-image" data-width="<?=$sw?>" data-height="<?=$sh?>" src="<?=$slide?>">
                    <div class="item-description">
                        <?=$description?>
                        <?php
                        if (substr($mode, 0, 1) == 'n')
                        {
                            ?>
                            <span style="color: red; font-size: smaller;">turned off</span>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </li>
            <?php
        }
        ?>
        </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>
