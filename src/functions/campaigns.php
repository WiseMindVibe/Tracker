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

function addCampaign($name, $country, $traffic_source_id) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO campaigns (name, country, traffic_source_id)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$name, $country, $traffic_source_id]);
}
function addExternalCampaignId($campaign_id, $external_id) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO campaign_external_ids (campaign_id, external_campaign_id)
        VALUES (?, ?)
    ");
    return $stmt->execute([$campaign_id, $external_id]);
}
function getExternalCampaignIds($campaign_id) {
    $db = db();
    $stmt = $db->prepare("SELECT external_campaign_id FROM campaign_external_ids WHERE campaign_id = ?");
    $stmt->execute([$campaign_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
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


function updateCampaignOffer($campaign_offer_id, $cap, $current_views) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE campaign_offers
        SET cap = :cap,
            current_views = :current_views
        WHERE id = :id
    ");
    return $stmt->execute([
        ':cap' => $cap,
        ':current_views' => $current_views,
        ':id' => $campaign_offer_id
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


