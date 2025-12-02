<?php
require "../../../src/bootstrap.php";

$id = $_GET['id'] ?? null;

deleteOfferArticle($id);
header("Location: list.php?offer_id=$id");
exit;
