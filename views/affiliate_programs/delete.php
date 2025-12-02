<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];

deleteAffiliateProgram($id);

header("Location: list.php");
exit;
