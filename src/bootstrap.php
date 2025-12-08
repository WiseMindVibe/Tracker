<?php
// Load .env values (you can use vlucas/phpdotenv later)
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Database config
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'tracker';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";

try {
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$countries = json_decode(file_get_contents(__DIR__ . '/../data/countries.json'), true); //Add countries & decode JSON


$DOMAIN_START = getenv('base_domain') ?: 'tracker';
$base_url = getenv('BASE_URL') ?: 'localhost/tracker';

// Autoloader for src/
spl_autoload_register(function ($class) {
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

// Load all functions files /
foreach (glob(__DIR__ . '/functions/*.php') as $filename) {
    require_once $filename;
}

// Simple global function to access DB anywhere
function db() {
    global $db;
    return $db;
}
