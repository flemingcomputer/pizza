<?php
// Copyright 2022 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'))
    error404();

// Make MySQL report errors in JSON format.
$GLOBALS['pizza']['t']->setErrorMode('json');

header('Content-Type: application/json; charset=utf-8');
if (!isset($_POST['path']))
{
    echo json_encode(array(
        'result' => false,
        'error' => 'path missing'
    ));
    exit();
}
$pagePath = strlen($_POST['path']) > 250 ? '' : $_POST['path'];
beginSession();
if (($pagePath == '') || !isWritable($pagePath))
{
    echo json_encode(array(
        'result' => false,
        'error' => "Unauthorized"
    ));
    exit();
}
$urlRoot = $GLOBALS['pizza']['urlRoot'];

function deleteFile()
{
    global $pagePath;
    $fileName = substr($_POST['deleteFile'], 0, 200);
    $GLOBALS['pizza']['cm']->deleteFile($pagePath, $fileName);
    echo json_encode(array('result' => true));
    exit();
}

function listFiles()
{
    global $urlRoot, $pagePath;
    $fileInfo = $GLOBALS['pizza']['cm']->getFileInfo($pagePath);
    if ($fileInfo === false) $fileInfo = array();
    $json = array(
        'result' => true,
        'files' => array()
    );
    foreach ($fileInfo as $f)
    {
        $fileName = $f['fileName'];
        $fileSize = $f['fileSize'];
        $fileType = $f['mimeType'];
        $fileUrl = $urlRoot . $pagePath . urlencode($fileName);
        $width = 0; $height = 0;
        if ($f['dimensions'] != '')
            list($width, $height) = explode('x', $f['dimensions']);
        $json['files'][] = array(
            'name' => $fileName,
            'size' => $fileSize,
            'type' => $fileType,
            'url' => $fileUrl,
            'width' => $width,
            'height' => $height
        );
    }
    echo json_encode($json);
    exit();
}

if ($_POST['deleteFile'] ?? false) deleteFile();
if ($_POST['listFiles'] ?? false) listFiles();

if (!isset($_FILES['file']['name']) || is_array($_FILES['file']['name']))
{
    echo json_encode(array(
        'result' => false,
        'error' => "One and only one file must be submitted."
    ));
    exit();
}

$maxPost = ini_get('post_max_size') ? ini_get('post_max_size') : 0;
$u = strtoupper(substr($maxPost, -1));
$maxBytes = (int) $maxPost;
if ($u == 'K') $maxBytes = substr($maxPost, 0, -1) * 1024;
else if ($u == 'M') $maxBytes = substr($maxPost, 0, -1) * 1048576;
else if ($u == 'G') $maxBytes = substr($maxPost, 0, -1) * 1073741824;
if (($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes)
{
    echo json_encode(array(
        'result' => false,
        'error' => "Max POST size of $maxPost exceeded."
    ));
    exit();
}

$fileName = $_FILES['file']['name'];
$fileType = $_FILES['file']['type'];
$fileSize = $_FILES['file']['size'];
$tmpName = $_FILES['file']['tmp_name'];
$error = $_FILES['file']['error'];
$e = '';
if ($error == UPLOAD_ERR_NO_FILE) $e = 'no file given';
else if (($error == UPLOAD_ERR_INI_SIZE) || ($error == UPLOAD_ERR_FORM_SIZE))
    $e = "\"$fileName\" is too big (" . ini_get('upload_max_filesize') . ' max)';
else if ($error == UPLOAD_ERR_PARTIAL) $e = "\"$fileName\": partial upload error";
else if ($error != UPLOAD_ERR_OK) $e = "\"$fileName\": upload error";
else if (!is_uploaded_file($tmpName)) $e = "\"$fileName\": upload failed";
else if ($fileSize == 0) $e = "\"$fileName\": does not exist or is empty";
if ($e !== '')
{
    echo json_encode(array(
        'result' => false,
        'error' => $e
    ));
    exit();
}

if ($GLOBALS['pizza']['cm']->hasFile($pagePath, $fileName))
{
    // We'll replace the old file with the new upload.
    $GLOBALS['pizza']['cm']->deleteFile($pagePath, $fileName);
}
else if ($GLOBALS['pizza']['cm']->hasPage($pagePath . $fileName))
{
    echo json_encode(array(
        'result' => false,
        'error' => "\"$fileName\": already exists as page"
    ));
    exit();
}

$contents = file_get_contents($tmpName);
$r = $GLOBALS['pizza']['cm']->addFile($pagePath, $fileName, $fileSize, $fileType, $contents);
$j = array(
    'result' => true,
    'url' => $urlRoot . $pagePath . $fileName,
    'size' => $r['size']
);
if ($r['width'] ?? false) $j['width'] = $r['width'];
if ($r['height'] ?? false) $j['height'] = $r['height'];
echo json_encode($j);
exit();
?>
