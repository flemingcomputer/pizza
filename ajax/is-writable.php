<?php
// Copyright 2018 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
beginSession();
$pagePath = gge('pagePath', '');
$pagePath = strlen($pagePath) > 250 ? '' : $pagePath;
$json['pagePath'] = $pagePath;
$json['isWritable'] = false;
if (isWritable($pagePath))
    $json['isWritable'] = true;
echo json_encode($json);
?>
