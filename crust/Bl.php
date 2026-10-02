<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Bl
{
    function getHtml()
    {
        $ip = myHtmlEntities(gge('ip', '0.0.0.0'));
        ob_start();
        ?>
        <h1>Mail Blocked from IP <?=$ip?></h1>
        <p>
        The IP <?=$ip?> was blocked from sending email due to its spam-sending behavior.  If you feel this is in error, use this site's contact link to inquire with the administrator.
        </p>
        <?php
        return ob_get_clean();
    }
}
?>
