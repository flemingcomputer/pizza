<?php
// Copyright 2017 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Menu
{
    // Element attributes.

    // Form inputs.
    private $label;
    private $url;

    private $id;

    function __construct()
    {
        $this->id = 'Menu_' . $GLOBALS['pizza']['pagePath'];
    }

    function getHtml()
    {
        if (!isAdministrator()) error404();
        $this->initThis();
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);

        // Show an error when the POST max size for PHP is exceeded.
        if (($_SERVER['REQUEST_METHOD'] == 'POST') && empty($_POST))
        {
            $maxPostSize = ini_get('post_max_size');
            $u = strtoupper(substr($maxPostSize, -1));
            if ($u == 'K') $maxPostSize = substr($maxPostSize, 0, -1) * 1024;
            else if ($u == 'M') $maxPostSize = substr($maxPostSize, 0, -1) * 1048576;
            else if ($u == 'G') $maxPostSize = substr($maxPostSize, 0, -1) * 1073741824;
            if ($_SERVER['CONTENT_LENGTH'] > $maxPostSize)
            {
                $this->files['value'] = '';
                $this->files['errors'] = array();
                $this->files['errors'][0] = 'total upload is too large ('
                    . ini_get('post_max_size') . ' max)';
                ss($this->id, $this);
            }
        }

        return $this->showForm();
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->label = $o ? $o->label : array('value' => '', 'error' => '');
        $this->url = $o ? $o->url : array('value' => '', 'error' => '');
    }

    private function showForm()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $cm = $GLOBALS['pizza']['cm'];
        $menu = $cm->getMenu('main');
        if (
            hg('label')
            && (hg('up') || hg('down') || hg('edit') || hg('delete'))
        ) {
            $label = trim(substr(gge('label', ''), 0, 200));
            if (($label == '') || (!isset($menu[$label]))) relocateNow($urlRoot . $pagePath);
            if (hg('up'))
            {
                $keys = array_keys($menu);
                $l = array_search($label, $keys);
                if ($l == 0) relocateNow($urlRoot . $pagePath);
                $tempKey = $keys[$l - 1];
                $keys[$l - 1] = $keys[$l];
                $keys[$l] = $tempKey;
                $menu2 = array();
                foreach ($keys as $label)
                    $menu2[$label] = $menu[$label];
                $menu = $menu2;
                unset($menu2);
                $cm->saveMenu('main', $menu);
                relocateNow($urlRoot . $pagePath);
            }
            if (hg('down'))
            {
                $keys = array_keys($menu);
                $l = array_search($label, $keys, true);
                if ($l == count($keys) - 1) relocateNow($urlRoot . $pagePath);
                $tempKey = $keys[$l + 1];
                $keys[$l + 1] = $keys[$l];
                $keys[$l] = $tempKey;
                $menu2 = array();
                foreach ($keys as $label)
                    $menu2[$label] = $menu[$label];
                $menu = $menu2;
                unset($menu2);
                $cm->saveMenu('main', $menu);
                relocateNow($urlRoot . $pagePath);
            }
            if (hg('delete'))
            {
                unset($menu[$label]);
                $cm->saveMenu('main', $menu);
                relocateNow($urlRoot . $pagePath);
            }
        }
        ob_start();
        ?>
        <script>
        $(function() {
            window.onbeforeunload = function() {
                sessionStorage.setItem("menu-windowTop", $(window).scrollTop());
            }
            if (sessionStorage.getItem("menu-windowTop") == null)
                sessionStorage.setItem("menu-windowTop", 0);
            $(window).scrollTop(sessionStorage.getItem("menu-windowTop"));
        });
        </script>
        <h1>Menu</h1>
        <div class="menu" id="menu">
            <form id="formMenu" action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <?php
                foreach ($menu as $label => $url)
                {
                    ?>
                    <a href="?label=<?=urlencode($label)?>&amp;up">up</a>&#8593;&nbsp;
                    <a href="?label=<?=urlencode($label)?>&amp;down">down</a>&#8595;&nbsp;
                    <a href="?label=<?=urlencode($label)?>&amp;delete">delete</a>&#9747;&nbsp;&nbsp;
                    <?php
                    if ($url == '---')
                        echo "&#8213;&#8213;&#8213;&#8213;&#8213;";
                    else
                        echo "$label &#9758; $url";
                    echo "<br>\n";
                }
                ?>
                <br>
                <input name="label" placeholder="label" type="text" value="<?=myHtmlEntities($this->label['value'])?>" /> or 3 dashes --- for divider bar
                <?php
                if ($this->label['error'] != '')
                {
                    ?>
                    <span class="error">←<?=myHtmlEntities($this->label['error'])?></span>
                    <?php
                }
                ?>
                <br>
                <input name="url" placeholder="link (url)" type="text" value="<?=myHtmlEntities($this->url['value'])?>" /> e.g. /some-page/ or www.site.com
                <?php
                if ($this->url['error'] != '')
                {
                    ?>
                    <span class="error">←<?=myHtmlEntities($this->url['error'])?></span>
                    <?php
                }
                sc($this->id);
                ?>

                <div>
                    <input class="submit" type="submit" name="save" value="save" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $cm = $GLOBALS['pizza']['cm'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('save'))
        {
            $this->label = $v->checkLength('label', 1, 200);
            $this->url = $v->checkLength('url', 1, 1000);
            if (($v->error) && ($this->label['value'] != '---'))
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . currentPath());
            }
            $menu = $cm->getMenu('main');
            if ($this->label['value'] == '---')
            {
                while (true)
                {
                    $i = mt_rand(111, 999);
                    if (!isset($menu[$i])) break;
                }
                $menu["divider_$i"] = '---';
            }
            else
                $menu[$this->label['value']] = $this->url['value'];
            $cm->saveMenu('main', $menu);
            sc($this->id);
            relocateNow($urlRoot . currentPath());
        }

        sleep(3);
    }
}
?>
