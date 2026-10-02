<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class ImageFrame
{
    private $css;

    function applyTopping($html)
    {
        $html = preg_replace_callback(
            '/\[(image)([^]]+])/sU',
            array($this, '_applyTopping'),
            $html
        );
        return $html;
    }

    private function _applyTopping($matches)
    {
        $tp = $GLOBALS['pizza']['toppings'];
        $e = substr($matches[0], 1, -1);
        $fileName = $tp->getString($e, 'image', '');
        $width = $tp->getString($e, 'width', '');
        $align = $tp->getString($e, 'align', 'center');
        $border = $tp->getString($e, 'border', '');
        $url = $tp->getString($e, 'url', '');
        if ($fileName == '') return $tp->error($e);
        $caption = '';
        preg_match("/caption\s*=\s*(.+)/", $e, $matches);
        if (count($matches) == 2) $caption = trim($matches[1]);
        $cm = $GLOBALS['pizza']['cm'];
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
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
        // We don't want to load unreasonably large images, so adjust the size
        // of the retrieved image to be a percentage of a 1000px width.
        $srcUrl = $urlRoot . '/images/image_error.png';
        $altText = 'image error for ' . myHtmlEntities($fileName);
        $boundedWidth = substr($width, 0, -1) * 10.00;
        $dims = $cm->imageGetDimensions($pagePath, $fileName, $boundedWidth, 9999);
        if ($dims !== false)
        {
            list($srcWidth, $srcHeight) = $dims;
            $srcUrl = $urlRoot . $pagePath . $fileName . "?width=$srcWidth&amp;height=$srcHeight";
            $altText = myHtmlEntities($fileName);
        }
        if ($caption != '') $altText = myHtmlEntities($caption);
        // Set any border width and create img tag.
        $style = '';
        if (($border != '') && is_numeric($border))
            $style = ' style="border-width: ' . $border . 'px;"';
        $img = '<img' . $style . ' src="' . $srcUrl . '" '
            . 'alt="' . $altText . '">';
        // Link to a given URL, or if no URL then the image if it's bigger.
        if ($url != 'false')
        {
            if ($url != '')
            {
                if (substr($url, 0, 4) == 'http')
                    $img = "<a href=\"$url\" target=\"_blank\">$img</a>";
                else
                    $img = "<a href=\"$url\">$img</a>";
            }
            else if ($dims !== false)
            {
                $dims = $cm->imageGetDimensions($pagePath, $fileName);
                list($actualWidth, $actualHeight) = $dims;
                if (($actualWidth > $srcWidth) && ($actualHeight > $srcHeight))
                {
                    $url = $urlRoot . $pagePath . $fileName;
                    $img = "<a href=\"$url\">$img</a>";
                }
            }
        }
        if ($align == 'left') $html = $this->showLeft($img, $caption, $width);
        else if ($align == 'right') $html = $this->showRight($img, $caption, $width);
        else if ($align == 'row') $html = $this->showRow($img, $caption, $width);
        else $html = $this->showCenter($img, $caption, $width);
        $html = $tp->protect($html);
        return $html;
    }

    private function showCenter($image, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-center" style="width: $width;">
$image$caption
</div>
HTML;
    }

    private function showLeft($image, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-left" style="width: $width;">
$image$caption
</div>
HTML;
    }

    private function showRight($image, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-right" style="width: $width;">
$image$caption
</div>
HTML;
    }

    private function showRow($image, $caption, $width)
    {
        if ($caption != '')
        {
            $mCaption = myHtmlEntities($caption);
            $caption = "\n<div class=\"caption\">$mCaption</div>";
        }
        return <<<HTML
<div class="image-frame image-frame-row" style="width: $width;">
$image$caption
</div>
HTML;
    }
}
?>
