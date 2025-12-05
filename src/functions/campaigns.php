<?php

function getCampaigns() {
    $db = db();
    $stmt = $db->query("
        SELECT c.*, t.name AS traffic_source_name
        FROM campaigns c
        LEFT JOIN traffic_sources t ON c.traffic_source_id = t.id
        ORDER BY c.id DESC
    ");
    return $stmt->fetchAll();
}

function getCampaign($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addCampaign($name, $external_campaign_id, $country, $traffic_source_id) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO campaigns (name, external_campaign_id, country, traffic_source_id)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$name, $external_campaign_id, $country, $traffic_source_id]);
}

function updateCampaign($id, $name, $external_campaign_id, $country, $traffic_source_id) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE campaigns
        SET name = ?, external_campaign_id = ?, country = ?, traffic_source_id = ?
        WHERE id = ?
    ");
    return $stmt->execute([$name, $external_campaign_id, $country, $traffic_source_id, $id]);
}

function deleteCampaign($id) {
    $db = db();

    // Check if offer(s) exist
    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM campaign_offers WHERE campaign_id = ?");
    $stmtCheck->execute([$id]);
    $exists = $stmtCheck->fetchColumn();

    if ($exists > 0) {
        // Delete offer
        $stmtDel = $db->prepare("DELETE FROM campaign_offers WHERE campaign_id = ?");
        $stmtDel->execute([$id]);
    }

    // Delete campaign
    $stmt2 = $db->prepare("DELETE FROM campaigns WHERE id = ?");
    return $stmt2->execute([$id]);
}

function addCampaignOffer($campaign_id, $offer_id, $cap = 0) {
    $db = db();
    $stmt = $db->prepare('
        INSERT INTO campaign_offers (campaign_id, offer_id, cap)
        VALUES (:campaign_id, :offer_id, :cap)
    ');
    
    return $stmt->execute([
        ':campaign_id' => $campaign_id,
        ':offer_id'    => $offer_id,
        ':cap'         => $cap    ]);
}

function getCampaignOffers($campaign_id) {
    $db = db();
    $stmt = $db->prepare("
        SELECT *
        FROM campaign_offers
        WHERE campaign_id = :campaign_id
        ORDER BY id DESC
    ");
    
    $stmt->execute([':campaign_id' => $campaign_id]);
    return $stmt->fetchAll();
}


function updateCampaignOffer($id, $offer_id, $cap, $views) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE campaign_offers
        SET offer_id = :offer_id,
            cap = :cap,
            views = :views
        WHERE id = :id
    ");
    return $stmt->execute([
        'offer_id' => $offer_id,
        'cap'      => $cap,
        'views'    => $views,
        'id'       => $id
    ]);
}

function deleteCampaignOffer($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM campaign_offers WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}

function getOfferViews(int $campaignId, int $offerId, ?string $date = null): int {
    $db = db();

    // Default to today
    $date = $date ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM clicks
        WHERE offer_id = :offer_id 
          AND campaign_id = :campaign_id
          AND DATE(created_at) = :date
    ");
    $stmt->execute([
        ':offer_id'    => $offerId,
        ':campaign_id' => $campaignId,
        ':date'        => $date
    ]);
    return (int)$stmt->fetchColumn();
}

function getOfferViewsInCampaign($campaign_id) {
    $stmt = db()->prepare("
        SELECT SUM(current_views) 
        FROM campaign_offers 
        WHERE campaign_id = :cid
    ");
    $stmt->execute([
        ':cid' => $campaign_id,
    ]);

    return $stmt->fetchColumn();
}

function getOfferCapInCampaign($campaign_id) {
    $stmt = db()->prepare("
        SELECT SUM(cap) 
        FROM campaign_offers 
        WHERE campaign_id = :cid
    ");
    $stmt->execute([
        ':cid' => $campaign_id,
    ]);

    return $stmt->fetchColumn();
}


