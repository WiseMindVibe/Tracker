<?php
require_once __DIR__ . "/../../../src/bootstrap.php";

$id = $_GET['id'];
$website_id = $_GET['website_id'];

deleteWebsiteBuffer($id);

header("Location: list.php?id=$website_id");
exit;
