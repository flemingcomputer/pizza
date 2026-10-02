<?php
// Copyright 2018 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
beginSession();
$pagePath = gge('pagePath', '');
$pagePath = strlen($pagePath) > 250 ? '' : $pagePath;
$json['success'] = false;
if (!isWritable($pagePath))
{
    echo json_encode($json);
    exit();
}
require_once('Store.php');
$store = new Store();
$items = $store->getItems($pagePath);
// The order is 0-indexed and is hyphen-separated; e.g. 4-0-2
$order = substr(gge('order', ''), 0, 2000);
$order = explode('-', $order);
$sortIds = array();
foreach ($items as $i) $sortIds[] = $i['id'];
// Move ids from sortIds into temp based on order's values.
$temp = array();
foreach ($order as $k)
{
    if (!is_numeric($k) || (round($k, 0) != $k) || !isset($sortIds[$k]))
        continue;
    $temp[] = $sortIds[$k];
    unset($sortIds[$k]);
}
// Move any remaining sortIds into temp.
foreach ($sortIds as $k) $temp[] = $k;
$sortIds = $temp; unset($temp);
// Save the new sort order.
$GLOBALS['pizza']['settings']->set($pagePath, 'Store_sortOrder', $sortIds);
$json['success'] = true;
echo json_encode($json);
?>
