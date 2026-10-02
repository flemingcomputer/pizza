<?php
// Copyright 2026 Fleming Computer.
// All Rights Reserved.
?>
<?php
// Recover filesBackup table to files.

require_once('pizza.php');
$db = $GLOBALS['pizza']['config']['dbDatabase'];
$t = $GLOBALS['pizza']['t'];

$q = "
    SELECT COUNT(*) AS c
    FROM information_schema.tables
    WHERE table_schema = '$db'
    AND table_name = 'filesBackup'
";
$t->query($q);
$r = $t->getNextRecord();
$n = $r['c'];
if ($n != 1)
{
    echo "Table 'filesBackup' doesn't exist.\n";
    exit();
}

$q = "DROP TABLE IF EXISTS `files`";
$t->query($q);
$q = "CREATE TABLE `files` LIKE filesBackup";
$t->query($q);
$q = "INSERT INTO `files` SELECT * FROM filesBackup";
$t->query($q);
echo "Table 'filesBackup recovered to 'files'.\n";
?>
