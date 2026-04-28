<?php
require_once __DIR__ . '/../../../src/bootstrap.php';

header('Content-Type: application/json');
date_default_timezone_set('UTC');

echo "Starting Oponia sync...\n";
echo "========================================\n";

// -------------------------
// DB + API KEYS
// -------------------------
$db = db();

// get affiliate account
$stmt = $db->prepare("SELECT id FROM affiliate_accounts WHERE affiliate_program = 'oponia'");
$stmt->execute();
$affiliate_account_id = $stmt->fetchColumn();

// API KEY
$stmt = $db->prepare("
    SELECT field_value 
    FROM affiliate_account_credentials 
    WHERE affiliate_account_id = :id AND field_key = 'API-Key'
");
$stmt->execute(['id' => $affiliate_account_id]);
$api_key = $stmt->fetchColumn();

// -------------------------
// DATE RANGE (FULL YEAR)
// -------------------------
$global_start = new DateTime('2025-01-01');
$global_end   = new DateTime('2025-04-18');

$markets = ['AT', 'DE'];

while ($global_start < $global_end) {
    $chunk_start = clone $global_start;
    $chunk_end = clone $global_start;
    $chunk_end->modify('+30 days');
