<?php
require_once __DIR__ . '/affiliate_programs.php';
require_once __DIR__ . '/websites.php';

function getOffers() {
    $db = db();
    $stmt = $db->query("
        SELECT o.*, a.name AS affiliate_name, w.domain AS website_domain
        FROM offers o
        LEFT JOIN affiliate_programs a ON o.affiliate_program_id = a.id
        LEFT JOIN websites w ON o.website_id = w.id
        ORDER BY o.id DESC
    ");
    return $stmt->fetchAll();
}

function getOffer($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM offers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addOffer($name, $affiliate_program_id, $affiliate_link, $country, $website_id) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO offers (name, affiliate_program_id, affiliate_link, country, website_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$name, $affiliate_program_id, $affiliate_link, $country, $website_id]);
}

function updateOffer($id, $name, $affiliate_program_id, $affiliate_link, $country, $website_id) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE offers
        SET name = ?, affiliate_program_id = ?, affiliate_link = ?, country = ?, website_id = ?
        WHERE id = ?
    ");
    return $stmt->execute([$name, $affiliate_program_id, $affiliate_link, $country, $website_id, $id]);
}

function deleteOffer($id) {
    $db = db();

    // Check if article(s) exist
    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM offers_artlcle WHERE offer_id = ?");
    $stmtCheck->execute([$id]);
    $exists = $stmtCheck->fetchColumn();

    if ($exists > 0) {
        // Delete articles
        $stmtDel = $db->prepare("DELETE FROM offers_artlcle WHERE offer_id = ?");
        $stmtDel->execute([$id]);
    }

    $stmtCheck2 = $db->prepare("SELECT COUNT(*) FROM campaign_offers WHERE offer_id = ? ");
    $stmtCheck2->execute([$id]);
    $exists2 = $stmtCheck2->fetchColumn();

    if( $exists2 > 0) {
        $stmtDel2 = $db->prepare("DELETE FROM campaign_offers WHERE offer_id = ?");
        $stmtDel2->execute([$id]);
    }

    // Delete offer
    $stmt2 = $db->prepare("DELETE FROM offers WHERE id = ?");
    return $stmt2->execute([$id]);
}

function getOfferArticles($offer_id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM offers_artlcle WHERE offer_id = ?");
    $stmt->execute([$offer_id]);
    return $stmt->fetchAll();
}

function addOfferArticle($offer_id, $article_url) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO offers_artlcle (offer_id, article_url) VALUES (?, ?)");
    return $stmt->execute([$offer_id, $article_url]);
}

function updateOfferArticle($id, $article_url) {
    $db = db();
    $stmt = $db->prepare("UPDATE offers_artlcle SET article_url = ? WHERE id = ?");
    return $stmt->execute([$article_url, $id]);
}

function deleteOfferArticle($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM offers_artlcle WHERE id = ?");
    return $stmt->execute([$id]);
}
