<?php
// Copyright 2026 Fleming Computer.
// All Rights Reserved.
?>
<?php
// Move db-based content to file storage.

require_once('pizza.php');
$db = $GLOBALS['pizza']['config']['dbDatabase'];
$t = $GLOBALS['pizza']['t'];

// Check for storage enabled.
$storageRoot = storageRoot();
if ($storageRoot === false)
{
    echo "storageRoot not defined.\n";
    exit();
}

// Ensure a files backup has been made.
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
    echo "Table filesBackup doesn't exist.\n";
    exit();
}

// Lock the tables.
$q = "SET autocommit = 0";
$t->query($q);
$q = "LOCK TABLES `files` WRITE, `pages` WRITE";
$t->query($q);

// Get all files with non-empty contents.
$q = "
    SELECT id, pageId, fileName FROM `files`
    WHERE contents <> ''
    LIMIT 10000
";
$t->query($q);
$n = $t->num_rows;
$files = array();
for ($i = 0; $i < $n; $i++)
{
    $files[] = $t->getNextRecord();
}

// // Display fileNames and exit.
// for ($i = 0; $i < $n; $i++)
// {
//     echo $files[$i]['fileName'] . "\n";
//     echo safeFileName($files[$i]['fileName']) . "\n";
// }
// exit();

// Augment with pagePath info.
for ($i = 0; $i < $n; $i++)
{
    $pageId = $files[$i]['pageId'];
    $files[$i]['pagePath'] = $GLOBALS['pizza']['cm']->pageIdToPagePath($pageId);
}

// Move contents from db to storage.
for ($i = 0; $i < $n; $i++)
{
    $id = $files[$i]['id'];
    $pageId = $files[$i]['pageId'];
    $pagePath = $files[$i]['pagePath'];
    $fileName = $files[$i]['fileName'];
    $mFileName = $t->escapeString($fileName);
    $q = "
        SELECT contents FROM `files`
        WHERE id = $id AND pageId = $pageId AND fileName = '$mFileName'
    ";
    $t->query($q);
    $r = $t->getNextRecord();
    $contents = $r['contents'];
    $target = $storageRoot . $pagePath . safeFileName($fileName);
    if (!is_dir($storageRoot . $pagePath))
        mkdir($storageRoot . $pagePath, recursive: true);
    file_put_contents($target, $contents);
    $q = "
        UPDATE `files` SET contents = ''
        WHERE id = $id AND pageId = $pageId AND fileName = '$mFileName'
    ";
    $t->query($q);
}

// Set permissions on storage.
$uid = fileowner(dirname($storageRoot));
$gid = filegroup(dirname($storageRoot));
$uPosix = posix_getpwuid($uid);
$gPosix = posix_getgrgid($gid);
$user = $uPosix['name'] ?? $uid;
$group = $gPosix['name'] ?? $gid;
// echo "user: $user\n";
// echo "group: $group\n";
chown($storageRoot, $user);
chgrp($storageRoot, $group);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($storageRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    $subPath = $item->getPathname();
    chown($subPath, $user);
    chgrp($subPath, $group);
}

// Clear db caches.
$q = "SHOW COLUMNS FROM `files` LIKE 'cache%Dimensions'";
$t->query($q);
$numCaches = $t->num_rows;
for ($i = 1; $i <= $numCaches; $i++)
{
    $q = "
        UPDATE `files` SET
        cache{$i} = '', cache{$i}Dimensions = '', cache{$i}Read = '1970-01-01 00:00:00'
    ";
    $t->query($q);
}

// Unlock tables.
$q = "UNLOCK TABLES";
$t->query($q);
?>
