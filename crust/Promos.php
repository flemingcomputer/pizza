<?php
// Copyright 2017 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Promos
{
    // Element attributes.

    // Form inputs.
    private $amount;
    private $begins;
    private $ends;
    private $promoCode;
    private $uses;

    private $id;
    private $cm;
    private $t;

    function __construct()
    {
        $this->id = 'Promos_' . $GLOBALS['pizza']['pagePath'];
        $this->cm = $GLOBALS['pizza']['cm'];
        $this->t = $GLOBALS['pizza']['t'];
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

    function getPromos()
    {
        $children = $this->cm->getChildren('/settings/promos', 'promo-code');
        $currentDate = date('Y-m-d');
        $promos = array();
        $promos['active'] = array();
        $promos['expired'] = array();
        foreach ($children as $promo)
        {
            $lines = explode("\n", $promo['body']);
            foreach ($lines as $l)
            {
                if ($l == "") continue;
                $pair = explode(': ', $l);
                $promos[0][$pair[0]] = $pair[1];
            }
            $promoCode = $promos[0]['Promo Code'];
            $ends = date('Y-m-d', strtotime($promos[0]['Ends']));
            preg_match("/(\d+)\/(\d+)/", $promos[0]['Uses'], $matches);
            $used = $matches[1];
            $maxUses = $matches[2];
            if (($ends >= $currentDate) && ($used < $maxUses))
                $promos['active'][$promoCode] = $promos[0];
            else
                $promos['expired'][$promoCode] = $promos[0];
            unset($promos[0]);
        }
        ksort($promos['active']);
        ksort($promos['expired']);
        return $promos;
    }

    function checkPromo($promoCode)
    {
        $promoPath = '/settings/promos/' . $this->cm->pageNameToPageUri($promoCode);
        $promo = $this->cm->getPage($promoPath);
        if ($promo === false) return false;
        $body = $promo['body'];
        preg_match("/^Uses:\s(\d+)\/(\d+)$/m", $body, $matches);
        $used = $matches[1];
        $maxUses = $matches[2];
        preg_match("/^Begins:\s(\d{2}\/\d{2}\/\d{4})$/m", $body, $matches);
        $begins = date('Y-m-d', strtotime($matches[1]));
        preg_match("/^Ends:\s(\d{2}\/\d{2}\/\d{4})$/m", $body, $matches);
        $ends = date('Y-m-d', strtotime($matches[1]));
        $currentDate = date('Y-m-d');
        if (($used >= $maxUses)
            || ($currentDate < $begins)
            || ($currentDate > $ends))
            return false;
        preg_match("/^Promo Code:\s(.+)$/m", $body, $matches);
        $promoCode = $matches[1];
        preg_match("/^Amount:\s(.+)$/m", $body, $matches);
        $amount = $matches[1];
        return array($promoCode, $amount);
    }

    function redeemPromo($promoCode)
    {
        $promoPath = '/settings/promos/' . $this->cm->pageNameToPageUri($promoCode);
        $promo = $this->cm->getPage($promoPath);
        if ($promo === false) return false;
        $body = $promo['body'];
        preg_match("/^Uses:\s(\d+)\/(\d+)$/m", $body, $matches);
        $used = $matches[1];
        $maxUses = $matches[2];
        preg_match("/^Begins:\s(\d{2}\/\d{2}\/\d{4})$/m", $body, $matches);
        $begins = date('Y-m-d', strtotime($matches[1]));
        preg_match("/^Ends:\s(\d{2}\/\d{2}\/\d{4})$/m", $body, $matches);
        $ends = date('Y-m-d', strtotime($matches[1]));
        $currentDate = date('Y-m-d');
        if (($used >= $maxUses)
            || ($currentDate < $begins)
            || ($currentDate > $ends))
            return false;
        $body = preg_replace('/^Uses: ' . $used . '\/' . $maxUses . '$/m',
            'Uses: ' . ($used + 1) . '/' . $maxUses, $body);
        $this->cm->editPage($promoPath, $body, false);
        return true;
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->amount = $o ? $o->amount : array('value' => '', 'error' => '');
        $this->begins = $o ? $o->begins : array('value' => '', 'error' => '');
        $this->ends = $o ? $o->ends : array('value' => '', 'error' => '');
        $this->promoCode = $o ? $o->promoCode : array('value' => '', 'error' => '');
        $this->uses = $o ? $o->uses : array('value' => '', 'error' => '');
    }

    private function showForm()
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        if (hg('delete'))
        {
            $promoPath = $pagePath . $this->cm->pageNameToPageUri(substr(gg('delete'), 0, 200));
            if ($this->cm->hasPage($promoPath))
                $this->cm->deletePage($promoPath, false);
            relocateNow($urlRoot . currentPath());
        }
        ob_start();
        ?>
        <script>
        $(function() {
            var beginsSelected = false;
            var currentDate = new Date();
            var minBegins = currentDate.getMonth() + 1
                + "/" + currentDate.getDate()
                + "/" + currentDate.getFullYear();
            $("#begins").datepicker({
                defaultDate: "+0d",
                changeMonth: false,
                minDate: minBegins,
                numberOfMonths: 1,
                onSelect: function(selectedDate) {
                    beginsSelected = true;
                    var minEnds = new Date(selectedDate);
                    minEnds.setDate(minEnds.getDate());
                    $("#ends").datepicker("option", "minDate", minEnds);
                }
            });
            $("#ends").datepicker({
                defaultDate: "+0d",
                changeMonth: false,
                numberOfMonths: 1,
                beforeShowDay: function(date) {
                    return [beginsSelected];
                },
            });
        });

        $(function() {
            window.onbeforeunload = function() {
                sessionStorage.setItem("promos-windowTop", $(window).scrollTop());
            }
            if (sessionStorage.getItem("promos-windowTop") == null)
                sessionStorage.setItem("promos-windowTop", 0);
            $(window).scrollTop(sessionStorage.getItem("promos-windowTop"));
        });
        </script>
        <h1>Promos</h1>
        <div class="promos" id="promos">
            <form id="formPromos" action="<?=$urlRoot?><?=$pagePath?>" enctype="multipart/form-data" method="post" name="<?=myHtmlEntities($this->id)?>">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />

                <?php
                $class = '';
                $placeHolder = 'Promo Name';
                if ($this->promoCode['error'] != '')
                {
                    $class = 'class="error-placeholder"';
                    $this->promoCode['value'] = '';
                }
                ?>
                <input <?=$class?> name="promoCode" placeholder="<?=myHtmlEntities($placeHolder)?>" type="text" value="<?=myHtmlEntities($this->promoCode['value'])?>" />

                <?php
                $class = '';
                $placeHolder = '#Uses';
                if ($this->uses['error'] != '')
                {
                    $class = 'class="error-placeholder"';
                    $placeHolder = $placeHolder . ': 1-999';
                    $this->uses['value'] = '';
                }
                ?>
                <input <?=$class?> name="uses" placeholder="<?=myHtmlEntities($placeHolder)?>" type="text" value="<?=myHtmlEntities($this->uses['value'])?>" />

                <?php
                $class = '';
                $placeHolder = 'Amount (e.g. $5, 10%)';
                if ($this->amount['error'] != '')
                {
                    $class = 'class="error-placeholder"';
                    $this->amount['value'] = '';
                }
                ?>
                <input <?=$class?> name="amount" placeholder="<?=myHtmlEntities($placeHolder)?>" type="text" value="<?=myHtmlEntities($this->amount['value'])?>" />

                <br>

                <?php
                $class = '';
                if (($this->begins['error'] != '') || ($this->ends['error'] != ''))
                {
                    $class = 'class="error-placeholder"';
                    $this->begins['value'] = '';
                    $this->ends['value'] = '';
                }
                ?>
                <input <?=$class?> id="begins" name="begins" placeholder="Begins" type="text" value="<?=myHtmlEntities($this->begins['value'])?>" />

                <input <?=$class?> id="ends" name="ends" placeholder="Ends" type="text" value="<?=myHtmlEntities($this->ends['value'])?>" />

                <div>
                    <input class="submit" type="submit" name="save" value="Save" />
                </div>
                <?php
                sc($this->id);
                ?>
            </form>

            <?php
            $promos = $this->getPromos();
            if (!empty($promos['active']))
            {
                ?>
                <div>
                <p>Active Promos:</p>
                <?php
                foreach ($promos['active'] as $p)
                {
                    $promoCode = $p['Promo Code'];
                    $uses = $p['Uses'];
                    $amount = $p['Amount'];
                    $begins = $p['Begins'];
                    $ends = $p['Ends'];
                    $uPromo = urlencode($promoCode);
                    ?>
                    <p>
                        <?=myHtmlEntities($promoCode)?>: <?=$amount?> off, <?=$uses?> uses, valid from <?=$begins?> to <?=$ends?> <a href="<?=$urlRoot?><?=$pagePath?>?delete=<?=$uPromo?>">delete</a>
                    </p>
                    <?php
                }
                ?>
                </div>
                <?php
            }
            if (!empty($promos['expired']))
            {
                ?>
                <div>
                <p>Expired Promos:</p>
                <?php
                foreach ($promos['expired'] as $p)
                {
                    $promoCode = $p['Promo Code'];
                    $uses = $p['Uses'];
                    $amount = $p['Amount'];
                    $begins = $p['Begins'];
                    $ends = $p['Ends'];
                    $uPromo = urlencode($promoCode);
                    ?>
                    <p>
                        <?=myHtmlEntities($promoCode)?>: <?=$amount?> off, <?=$uses?> uses, valid from <?=$begins?> to <?=$ends?> <a href="<?=$urlRoot?><?=$pagePath?>?delete=<?=$uPromo?>">delete</a>
                    </p>
                    <?php
                }
                ?>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        if ($v->submitted('save'))
        {
            $this->promoCode = $v->checkLength('promoCode', 1, 200);
            if ($this->promoCode['error'] == '')
            {
                $promoPath = $pagePath . $this->cm->pageNameToPageUri($this->promoCode['value']);
                if ($this->cm->hasPage($promoPath))
                {
                    // If expired, delete and re-add.
                    $promos = $this->getPromos();
                    if (!empty($promos['expired'])
                        && array_key_exists(strtoupper($this->promoCode['value']), $promos['expired']))
                    {
                        $this->cm->deletePage($promoPath, false);
                    }
                    else
                    {
                        $this->promoCode['error'] = 'already exists';
                        $v->error = true;
                    }
                }
                $this->promoCode['value'] = strtoupper($this->promoCode['value']);
            }
            $this->uses = $v->checkInteger('uses', 1, 999);
            $this->amount = $v->checkLength('amount', 1, 8);
            if ($this->amount['error'] == '')
            {
                $this->amount['value'] = str_replace(' ', '', $this->amount['value']);
                $amount = $this->amount['value'];
                if (substr($amount, 0, 1) == '$')
                    $amount = substr($amount, 1);
                else if (substr($amount, -1) == '%')
                    $amount = substr($amount, 0, -1);
                else
                {
                    $this->amount['error'] = 'specify $ or %';
                    $v->error = true;
                }
                if (!is_numeric($amount)
                    || (round($amount, 2) != $amount)
                    || ($amount < 0)
                ) {
                    $this->amount['error'] = 'invalid amount';
                    $v->error = true;
                }
            }
            $this->begins = $v->checkDate('begins');
            $this->ends = $v->checkDate('ends');
            if (($this->begins['error'] == '') && ($this->ends['error'] == ''))
            {
                $currentDate = date('Y-m-d');
                $begins = date('Y-m-d', strtotime($this->begins['value']));
                $ends = date('Y-m-d', strtotime($this->ends['value']));
                if (($begins < $currentDate) || ($ends < $begins))
                {
                    $this->begins['error'] = 'invalid dates';
                    $this->ends['error'] = 'invalid dates';
                    $v->error = true;
                }
            }
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . currentPath());
            }
            // Format to 2-digit date; e.g. 03/09/2042
            $this->begins['value'] = date('m/d/Y', strtotime($this->begins['value']));
            $this->ends['value'] = date('m/d/Y', strtotime($this->ends['value']));
            $body = <<<PROMO
Promo Code: {$this->promoCode['value']}
Uses: 0/{$this->uses['value']}
Amount: {$this->amount['value']}
Begins: {$this->begins['value']}
Ends: {$this->ends['value']}
PROMO;
            $this->cm->addPage($pagePath, $this->promoCode['value'], $body, 'promo-code', 'override');
            sc($this->id);
            relocateNow($urlRoot . currentPath());
        }

        sleep(3);
        relocateNow($urlRoot . currentPath());
    }
}
?>
