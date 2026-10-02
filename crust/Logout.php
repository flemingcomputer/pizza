<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Logout
{
    function __construct()
    {
        if (substr($GLOBALS['pizza']['pagePath'], -1) == '/') error404();
    }

    function getHtml()
    {
        logout();
        ob_start();
        ?>
        <p>You are logged out.</p>
        <?php
        return ob_get_clean();
    }
}
?>
