<?php
// Copyright 2019 Fleming Computer.
// All Rights Reserved.
?>
<?php
class SiteSettings
{
    function __construct()
    {
    }

    function getHtml()
    {
        if (!isAdministrator()) error404();
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        ob_start();
        ?>
        <h1>Site Settings</h1>
        <ul>
            <li><a href="users/">Users</a></li>
            <li><a href="groups/">Groups</a></li>
            <li><a href="themes/">Themes</a></li>
            <li><a href="menu/">Menu</a></li>
        </ul>

        <h2>E-Commerce</h2>
        <ul>
            <li><a href="orders/">Orders</a></li>
            <li><a href="promos/">Promos</a></li>
        </ul>

        <?php
        if ($GLOBALS['pizza']['config']['sandbox'] ?? false)
        {
            ?>
            <h2>System</h2>
            <ul>
                <li><a href="/?sandboxPull">Reset Sandbox</a></li>
            </ul>
            <?php
        }
        return ob_get_clean();
    }
}
?>
