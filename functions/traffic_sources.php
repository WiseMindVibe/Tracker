<?php

function getTrafficSources() {
    $db = db();
    $stmt = $db->query("SELECT * FROM traffic_sources ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getTrafficSource($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM traffic_sources WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addTrafficSource($name, $api_key) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO traffic_sources (name, api_key) VALUES (?, ?)");
    return $stmt->execute([$name, $api_key]);
}

function updateTrafficSource($id, $name, $api_key) {
    $db = db();
    $stmt = $db->prepare("UPDATE traffic_sources SET name = ?, api_key = ? WHERE id = ?");
    return $stmt->execute([$name, $api_key, $id]);
}

function deleteTrafficSource($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM traffic_sources WHERE id = ?");
    return $stmt->execute([$id]);
}
