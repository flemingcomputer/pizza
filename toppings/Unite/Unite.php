<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Unite
{
    private $slideShowCounter;

    function __construct()
    {
        $this->slideShowCounter = 0;
    }

    function applyTopping($html)
    {
        $html = preg_replace_callback(
            '/\[(unite)([^]]+])/sU',
            array($this, '_applyTopping'),
            $html
        );
        return $html;
    }

    private function _applyTopping($matches)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $tp = $GLOBALS['pizza']['toppings'];
        $e = substr($matches[0], 1, -1);
        $link = $tp->getString($e, 'link', '');
        $theme = $tp->getString($e, 'theme', '');
        $interval = $tp->getString($e, 'interval', 3000);
        $width = $tp->getString($e, 'width', 900);
        $height = $tp->getString($e, 'height', 500);
        if (!is_numeric($interval) || (intval($interval) != $interval)
            || ($interval < 500) || ($interval > 120000))
            $interval = 3000;
        if ($link != '') $slides = $this->getPicasaAlbum($link);
        else
        {
            $slides = array();
            for ($i = 1; $i <= 99; $i++)
            {
                $slide = $tp->getString($e, "slide$i", '');
                $caption = $tp->getString($e, "caption$i", '');
                if ($slide == '') continue;
                $slides[] = array(
                    'slide' => $urlRoot . $pagePath . $slide,
                    'thumb' => $urlRoot . $pagePath . $slide . '?width=144',
                    'caption' => $caption
                );
            }
        }
        if ($slides === false) return "<p>Error: slideshow isn't configured properly.</p>\n";
        $this->slideShowCounter++;
        $html = $this->uniteGallery($theme, $interval, $width, $height, $slides);
        return $tp->protect($html);
    }

    private function getPicasaAlbum($link)
    {
        $parts = parse_url($link);
        if ($parts === false) return false;
        if (!isset($parts['host'])) return false;
        if (!isset($parts['path'])) return false;
        // Get user and album name.
        if ($parts['host'] == 'picasaweb.google.com')
        {
            $path = explode('/', $parts['path']);
            $user = isset($path[1]) ? $path[1] : '';
            $album = isset($path[2]) ? $path[2] : '';
            if (($user == '') || ($album == '')) return false;
            // Get album id for this album name.
            $url = "https://picasaweb.google.com/data/feed/api/user/$user";
            $xml = @file_get_contents($url);
            preg_match("/<gphoto:id>([^<>]*)<\/gphoto:id><gphoto:name>$album<\/gphoto:name>/sU", $xml, $albums);
            $albumId = count($albums) == 2 ? $albums[1] : '';
            if ($albumId == '') return false;
        }
        // else if ($parts['host'] == 'plus.google.com')
        // {
        //     $path = explode('/', $parts['path']);
        //     $user = isset($path[2]) ? $path[2] : '';
        //     $albumId = isset($path[4]) ? $path[4] : '';
        //     if (($user == '') || ($albumId == '')) return false;
        // }
        else return false;
        // Get images for this albumid.
        $url = "https://picasaweb.google.com/data/feed/api/user/$user/albumid/$albumId?imgmax=800";
        $xml = @file_get_contents($url);
        preg_match_all('/<entry>.*<\/entry>/sU', $xml, $entries);
        if (count($entries) != 1) return false;
        $images = array();
        foreach ($entries[0] as $entry)
        {
            preg_match("/<media:content\surl='(.*)'\sheight='.*'\swidth='.*'\s.*\smedium='image'\/>/sU", $entry, $content);
            if (count($content) != 2) continue;
            $slide = $content[1];
            preg_match('/<media:description.*>(.*)<\/media:description>/sU', $entry, $caption);
            $caption = count($caption) == 2 ? $caption[1] : '';
            $thumb = str_replace("/s800/", '/s144/', $slide);
            $images[] = array(
                'slide' => $slide,
                'thumb' => $thumb,
                'caption' => $caption
            );
        }
        return $images;
    }

    private function uniteGallery($theme, $interval, $width, $height, $slides)
    {
        if ($theme == '') $theme = 'default';
        $uniteDocRoot = $GLOBALS['pizza']['docRoot'] . '/toppings/Unite';
        $uniteUrlRoot = $GLOBALS['pizza']['urlRoot'] . '/toppings/Unite';
        if (is_file("$uniteDocRoot/css/unite-gallery.css"))
            addStyle("$uniteUrlRoot/css/unite-gallery.css");
        if (is_file("$uniteDocRoot/js/unitegallery.min.js"))
            addJavaScript("$uniteUrlRoot/js/unitegallery.min.js");
        if (is_file("$uniteDocRoot/themes/$theme/ug-theme-$theme.css"))
            addStyle("$uniteUrlRoot/themes/$theme/ug-theme-$theme.css");
        if (is_file("$uniteDocRoot/themes/$theme/ug-theme-$theme.js"))
            addJavaScript("$uniteUrlRoot/themes/$theme/ug-theme-$theme.js");
        ob_start();
        ?>
        <div id="gallery<?=$this->slideShowCounter?>" style="display:none;">
        <?php
        foreach ($slides as $s)
        {
            $slide = $s['slide'];
            $thumb = $s['thumb'];
            $caption = $s['caption'];
            $src = $thumb;
            if ($theme == 'tiles') $src = $slide;
            ?>
            <img alt="<?=$caption?>" src="<?=$src?>" data-image="<?=$slide?>" data-description="<?=$caption?>" />
            <?php
        }
        ?>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function(){
                jQuery("#gallery<?=$this->slideShowCounter?>").unitegallery({
<?php
                    if ($theme == 'default')
                    {
?>
                    slider_scale_mode: "down",
<?php
                    }
?>
                    slider_control_zoom: false,
                    tiles_type: "justified",
                    gallery_play_interval: <?=$interval?>,
                    gallery_width: <?=$width?>,
                    gallery_height: <?=$height?>,
                    tile_enable_textpanel: true
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
?>
