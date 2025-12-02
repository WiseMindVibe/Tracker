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
