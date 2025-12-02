<?php

function getAffiliatePrograms() {
    $db = db();
    $stmt = $db->query("SELECT * FROM affiliate_programs ORDER BY id DESC");
    return $stmt->fetchAll();
}

function getAffiliateProgram($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM affiliate_programs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addAffiliateProgram($name, $api_key = null, $api_secret = null) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO affiliate_programs (name, api_key, api_secret)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$name, $api_key, $api_secret]);
}

function updateAffiliateProgram($id, $name, $api_key, $api_secret) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE affiliate_programs
        SET name = ?, api_key = ?, api_secret = ?
        WHERE id = ?
    ");
    return $stmt->execute([$name, $api_key, $api_secret, $id]);
}

function deleteAffiliateProgram($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM affiliate_programs WHERE id = ?");
    return $stmt->execute([$id]);
}
