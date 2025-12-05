<?php

function getWebsites() {
    $db = db();
    $stmt = $db->query("SELECT * FROM websites ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getWebsite($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM websites WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addWebsite($domain, $country) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO websites (domain, country) VALUES (?, ?)");
    return $stmt->execute([$domain, $country]);
}

function updateWebsite($id, $domain, $country) {
    $db = db();
    $stmt = $db->prepare("UPDATE websites SET domain = ?, country = ? WHERE id = ?");
    return $stmt->execute([$domain, $country, $id]);
}

function deleteWebsite($id) {
    $db = db();

    // Delete website buffers first
    $stmt1 = $db->prepare("DELETE FROM websites_buffers WHERE website_id = ?");
    $stmt1->execute([$id]);

    // Delete website
    $stmt2 = $db->prepare("DELETE FROM websites WHERE id = ?");
    return $stmt2->execute([$id]);
}

function getWebsiteBuffers($website_id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM websites_buffers WHERE website_id = ?");
    $stmt->execute([$website_id]);
    return $stmt->fetchAll();
}

function getWebsiteBuffer($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM websites_buffers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addWebsiteBuffer($website_id, $type, $buffer_url) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO websites_buffers (website_id, type, buffer_url)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$website_id, $type, $buffer_url]);
}

function updateWebsiteBuffer($id, $type, $buffer_url) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE websites_buffers
        SET type = ?, buffer_url = ?
        WHERE id = ?
    ");
    return $stmt->execute([$type, $buffer_url, $id]);
}

function deleteWebsiteBuffer($buffer_id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM websites_buffers WHERE id = ?");
    return $stmt->execute([$buffer_id]);
}
