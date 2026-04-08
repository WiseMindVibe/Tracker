<?php
//set to 05am everyday
require_once __DIR__ . "/../bootstrap.php";
$__cronStart = microtime(true);

db()->prepare("UPDATE campaign_offers SET current_views = 0")->execute();

markCronRun('reset_views', 'success', 'Reset campaign_offers.current_views', $__cronStart);
echo "OK: current_views reset\n";