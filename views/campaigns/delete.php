<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$campaign_id = $_GET['cid'];
deleteCampaign($campaign_id);

header("Location: list.php");
exit;
