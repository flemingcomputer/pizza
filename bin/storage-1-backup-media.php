<?php
// Copyright 2026 Fleming Computer.
// All Rights Reserved.
?>
<?php
// Backup files table to filesBackup.

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
if ($n == 1)
{
    echo "Table filesBackup already exists.\n";
    exit();
}

$q = "CREATE TABLE filesBackup LIKE `files`";
$t->query($q);
$q = "INSERT INTO filesBackup SELECT * FROM `files`";
$t->query($q);
echo "Table 'files' copied to 'filesBackup'.\n";
?>
