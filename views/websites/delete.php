<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];

deleteWebsite($id);

header("Location: list.php");
exit;
