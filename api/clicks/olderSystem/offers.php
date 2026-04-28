<?php 

require_once __DIR__ . '/../../../src/bootstrap.php';

$api_url = 'https://wisemindvibe.com/tracker/API/offers.php';

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

if ($response === false) {
    die('cURL Error: ' . curl_error($ch));
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($http_code !== 200) {
    die('HTTP Error: ' . $http_code . ' Response: ' . $response);
}

$data = json_decode($response, true);

if ($data === null) {
    die('JSON Decode Error: ' . json_last_error_msg() . ' Raw: ' . $response);
}

print_r($data);
exit;
$response = json_decode($response, true);

