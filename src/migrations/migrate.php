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

        $migration = require_once $file;

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
    while (true) {
        $last = $db->query("
            SELECT id, migration
            FROM migrations
            ORDER BY id DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$last || !isset($last['id'], $last['migration'])) {
            echo nl2br("Nothing to rollback.\n");
            exit;
        }

        $migrationId = (int) $last['id'];
        $migrationName = (string) $last['migration'];
        $file = $migrationsPath . '/' . $migrationName;

        if (!file_exists($file)) {
            echo nl2br("⚠ Skipping missing migration file entry: {$migrationName}\n");
            $stmt = $db->prepare("DELETE FROM migrations WHERE id = ?");
            $stmt->execute([$migrationId]);
            continue;
        }

        $migration = require_once $file;

        if (!isset($migration['down']) || !is_callable($migration['down'])) {
            die("Migration {$migrationName} must return a 'down' callable\n");
        }

        echo nl2br("← Rolling BACK: {$migrationName}\n");

        try {
            $migration['down']($db);

            $stmt = $db->prepare("DELETE FROM migrations WHERE id = ?");
            $stmt->execute([$migrationId]);

            echo nl2br("✔ Rollback completed.\n");
        } catch (Throwable $e) {
            die("❌ Rollback failed: {$migrationName}\n" . $e->getMessage() . "\n");
        }

        exit;
    }
}

echo "Unknown action: $action\n";
