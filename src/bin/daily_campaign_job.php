#!/usr/bin/env php
<?php

/**
 * Schedule at 07:00 server time, e.g. cron:
 *   0 7 * * * php /path/to/src/bin/daily_campaign_job.php
 *
 * 1) Resets campaign_offers.current_views to 0
 * 2) Tester campaigns: yesterday ROI &lt; 200% → cap = -abs(cap); ROI ok and cap &lt; 0 → cap = abs(cap)
 * 3) Campaigns with spare capacity → traffic source API start (PropellerAds when configured)
 */

declare(strict_types=1);

$root = dirname(__DIR__, 1);
require_once $root . '/bootstrap.php';
require_once $root . '/models/ModelBase.php';
require_once $root . '/models/ModelCampaigns.php';

echo date('c') . " daily_campaign_job starting\n";

ModelCampaigns::resetAllCampaignOfferViews();
echo "Reset campaign_offers.current_views\n";

ModelCampaigns::applyTesterCapsFromYesterday();
echo "Applied tester ROI caps (yesterday)\n";

$log = ModelCampaigns::activateHealthyCampaignsTraffic();
foreach ($log as $line) {
    echo 'Start traffic campaign_id=' . ($line['campaign_id'] ?? '?')
        . ' ok=' . (($line['success'] ?? false) ? '1' : '0') . "\n";
}

echo date('c') . " daily_campaign_job done\n";
