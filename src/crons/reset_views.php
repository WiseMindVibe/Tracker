<?php

require_once __DIR__ . "/../bootstrap.php";

db()->prepare("UPDATE campaign_offers SET current_views = 0")->execute();

echo "OK: current_views reset\n";