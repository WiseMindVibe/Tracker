<?php
require_once __DIR__ . '/../bootstrap.php';

$db = db();

// Allow CLI or browser
$action = $argv[1] ?? ($_GET['action'] ?? 'up');

$migrationsPath = __DIR__ . '/files';

// --------------------------------------------------
// Ensure migrations table exists
// --------------------------------------------------
$db->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --------------------------------------------------
// MIGRATE UP (RUN ALL PENDING)
// --------------------------------------------------
if ($action === 'up') {

    echo nl2br("Running migrations...\n");

    $applied = $db->query("
        SELECT migration FROM migrations
    ")->fetchAll(PDO::FETCH_COLUMN);

    $files = glob($migrationsPath . '/*.php');
    sort($files);

    $ranAny = false;

    foreach ($files as $file) {
        $name = basename($file);

        if (in_array($name, $applied)) {
            continue;
        }

        $migration = require $file;

        if (!isset($migration['up']) || !is_callable($migration['up'])) {
            die("Migration $name must return an 'up' callable\n");
        }

        echo nl2br("→ Migrating UP: $name\n");

        try {
    $migration['up']($db);

    $stmt = $db->prepare("
        INSERT INTO migrations (migration) VALUES (?)
    ");
    $stmt->execute([$name]);

    $ranAny = true;

} catch (Throwable $e) {
    die("❌ Migration failed: {$name}\n" . $e->getMessage() . "\n");
}

    }

    if (!$ranAny) {
        echo nl2br("✔ Nothing to migrate.\n");
    } else {
        echo nl2br("✔ All migrations applied successfully.\n");
    }

    exit;
}

// --------------------------------------------------
// ROLLBACK LAST MIGRATION
// --------------------------------------------------
if ($action === 'rollback') {

    $last = $db->query("
        SELECT migration
        FROM migrations
        ORDER BY id DESC
        LIMIT 1
    ")->fetchColumn();

    if (!$last) {
        echo nl2br("Nothing to rollback.\n");
        exit;
    }

    $file = $migrationsPath . '/' . $last;

    if (!file_exists($file)) {
        die("Migration file not found: $last\n");
    }

    $migration = require $file;

    if (!isset($migration['down']) || !is_callable($migration['down'])) {
        die("Migration $last must return a 'down' callable\n");
    }

    echo nl2br("← Rolling BACK: $last\n");

    try {
    $migration['down']($db);

    $stmt = $db->prepare("
        DELETE FROM migrations WHERE migration = ?
    ");
    $stmt->execute([$last]);

    echo nl2br("✔ Rollback completed.\n");
} catch (Throwable $e) {
    die("❌ Rollback failed: {$last}\n" . $e->getMessage() . "\n");
}


    exit;
}

echo "Unknown action: $action\n";
