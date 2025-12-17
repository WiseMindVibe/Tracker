<?php
//Set this at 04:30 each day
require_once __DIR__ . '/bootstrap.php';

// 1. Get all tester campaigns
$stmt = db()->prepare("SELECT id, name FROM campaigns WHERE is_tester = 1");
$stmt->execute();
$testerCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$today->setTime(5,0); // 5 AM

foreach ($testerCampaigns as $campaign) {
    $campaignId = $campaign['id'];

    // 2. Get all offers for this campaign
    $stmt2 = db()->prepare("SELECT * FROM campaign_offers WHERE campaign_id = :cid");
    $stmt2->execute([':cid' => $campaignId]);
    $offers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($offers as $offer) {
        $offerId = $offer['offer_id'];

        // 3. Check if 3 days passed since updated_at (count in days from 5AM)
        $updatedAt = new DateTimeImmutable($offer['updated_at'], new DateTimeZone('UTC'));
        $daysPassed = ceil(($today->getTimestamp() - $updatedAt->getTimestamp()) / 86400); // 86400 sec = 1 day

        if ($daysPassed >= 3) {

            // 4. Compute ROI
            $startDate = $updatedAt->format('Y-m-d');
            $endDate   = $today->format('Y-m-d');

            $grouped = getGroupedClicks($startDate, $endDate, ['offer', 'campaign']);
            
            $roi = 0;
            if (isset($grouped[$offer['name']][$campaign['name']]['_stats'])) {
                $stats = $grouped[$offer['name']][$campaign['name']]['_stats'];
                $roi = $stats['roi'];
            }

            // 5. If ROI <= 250 → set cap to 0 and log
            if ($roi <= 250) {
                $stmtUpdate = db()->prepare("UPDATE campaign_offers SET cap = 0 WHERE campaign_id = :cid AND offer_id = :oid");
                $stmtUpdate->execute([
                    ':cid' => $campaignId,
                    ':oid' => $offerId
                ]);

                // Log
                $stmtLog = db()->prepare("
                    INSERT INTO log_campaign (campaign_id, offer_id, action, roi)
                    VALUES (:cid, :oid, :action, :roi)
                ");
                $stmtLog->execute([
                    ':cid' => $campaignId,
                    ':oid' => $offerId,
                    ':action' => 'Offer stopped by tester check',
                    ':roi' => $roi
                ]);
            }
        }
    }
}

echo "Tester check complete.\n";
