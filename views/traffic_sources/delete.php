<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];

deleteTrafficSource($id);

header("Location: list.php");
exit;
