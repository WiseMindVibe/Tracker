<?php
require "../../../src/bootstrap.php";

$id = $_GET['id'] ?? null;
$campaign_id = $_GET['cid'] ?? null;

if (!$id || !$campaign_id) die("Missing parameters.");

deleteCampaignOffer($id);

header("Location: list.php?cid=" . $campaign_id);
exit;
