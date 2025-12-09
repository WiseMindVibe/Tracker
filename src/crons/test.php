<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';

$result = stopTrafficSourceCampaign(1, [10194669]);

if ($result) {
    echo "Campaigns stopped successfully!";
} else {
    echo "Failed to stop campaigns.";
}

