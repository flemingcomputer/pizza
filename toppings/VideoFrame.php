<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
class VideoFrame
{
    private $css;

    function applyTopping($html)
    {
        $html = preg_replace_callback(
            '/\[(video)([^]]+])/sU',
            array($this, '_applyTopping'),
            $html
        );
        return $html;
    }

    private function _applyTopping($matches)
    {
        $tp = $GLOBALS['pizza']['toppings'];
        $e = substr($matches[0], 1, -1);
        $fileName = $tp->getString($e, 'video', '');
        $width = $tp->getString($e, 'width', '');
        $align = $tp->getString($e, 'align', 'center');
        $poster = $tp->getString($e, 'poster', '');
        $autoplay = $tp->getString($e, 'autoplay', '');
        $autoplay = ($autoplay == 'true') ? ' autoplay muted loop' : '';
        if ($fileName == '') return $tp->error($e);
        $caption = '';
        preg_match("/caption\s*=\s*(.+)/", $e, $matches);
        if (count($matches) == 2) $caption = trim($matches[1]);
        $pageId = $GLOBALS['pizza']['page']['id'];
        // For responsive design, we must express dimensions as percentages.
        // If dimensions are given as pixels, ignore that and use a reasonable
        // percentage width instead.
        // Bound a percentage width to within 1% and 100%.
        if (substr($width, -1) == '%')
        {
            $w = substr($width, 0, -1);
            if (!is_numeric($w) || ($w < 1) || ($w > 100)) $width = '50%';
        }
        else $width = '50%';
        $src = ' src="' . myHtmlEntities($fileName) . '"';
        if ($poster != '') $poster = ' poster="' . myHtmlEntities($poster) . '"';
        $video = '<video controls' . $autoplay . ' preload="metadata" width="100%" height="100%"'
            . $poster . $src . ' type="video/mp4">Video not supported.</video>';
        if ($align == 'left') $html = $this->showLeft($video, $caption, $width);
        else if ($align == 'right') $html = $this->showRight($video, $caption, $width);
        else $html = $this->showCenter($video, $caption, $width);
        $html = $tp->protect($html);
        return $html;
    }

    private function showCenter($video, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-center" style="background-color: transparent; width: $width;">
$video$caption
</div>
HTML;
    }

    private function showLeft($video, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-left" style="background-color: transparent; width: $width;">
$video$caption
</div>
HTML;
    }

    private function showRight($video, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-right" style="background-color: transparent; width: $width;">
$video$caption
</div>
HTML;
    }
}
?>
