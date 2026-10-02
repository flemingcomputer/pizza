<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
require_once('ContentManager.php');
$cm = new ContentManager();
$cm->deleteRevisions('all');
// $cm->deleteRevisions('r1');
// $cm->deleteRevisions('1970-01-31 01:02:03');
?>
