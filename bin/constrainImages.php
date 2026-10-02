<?php
// Copyright 2026 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');

$cropBox = $GLOBALS['pizza']['config']['cropBox'] ?? 0;
if ($cropBox == 0)
{
    echo "cropBox not set, exiting.\n";
    exit();
}

$t = $GLOBALS['pizza']['t'];
$cm = $GLOBALS['pizza']['cm'];

// Get a list of all images having dimensions exceeding the crop box.
$q = "
    SELECT id, pageId, revision, fileName, fileSize, mimeType, dimensions FROM `files`
    WHERE dimensions != ''
";
$t->query($q);
$n = $t->num_rows;
$images = array();
for ($i = 0; $i < $n; $i++)
{
    $r = $t->getNextRecord();
    $dims = explode('x', $r['dimensions']);
    $x = $dims[0];
    $y = $dims[1];
    if (($x <= $cropBox) && ($y <= $cropBox)) continue;
    $images[] = array(
        'id' => $r['id'],
        'pageId' => $r['pageId'],
        'revision' => $r['revision'],
        'fileName' => $r['fileName'],
        'fileSize' => $r['fileSize'],
        'mimeType' => $r['mimeType']
    );
}

$n = count($images);
echo "Number of images to constrain: $n\n";
echo "You'll have to review this code and remove the exit in order to do anything.\n";
exit();

foreach ($images as $k => $i)
{
    $pageId = $i['pageId'];
    $pagePath = $GLOBALS['pizza']['cm']->pageIdToPagePath($pageId);
    $fileName = $i['fileName'];
    $fileSize = $i['fileSize'];
    $mimeType = $i['mimeType'];
    $kk = $k + 1;
    echo "$kk of $n: Constraining '$fileName' to $cropBox...\n";
    $contents = $cm->getFileContents($pagePath, $fileName);
    $cm->deleteFile($pagePath, $fileName);
    $cm->addFile($pagePath, $fileName, $fileSize, $mimeType, $contents);
}
?>
