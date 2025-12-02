<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];

deleteWebsite($id);

header("Location: website_list.php");
exit;
