<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MediTrack\Core\Database;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->safeLoad();
}

$dbConfig = require $basePath . '/config/database.php';
// Ensure database exists (for MySQL)
$driver = $dbConfig['driver'] ?? 'mysql';
if ($driver === 'mysql') {
    $host = $dbConfig['host'] ?? '127.0.0.1';
    $port = $dbConfig['port'] ?? 3306;
    $database = $dbConfig['database'] ?? 'meditrack';
    $user = $dbConfig['username'] ?? 'root';
    $pass = $dbConfig['password'] ?? '';
    try {
        $tempPdo = new PDO("mysql:host={$host};port={$port}", $user, $pass);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    } catch (\Throwable $e) {
        // Continue and let main connection attempt handle error
    }
}

$db = new Database($dbConfig);
$pdo = $db->pdo();

echo "=== MediTrack Database Migration Runner ===\n";

// 1. Ensure schema_migrations table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(100) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. Fetch already applied migrations
$stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY version ASC");
$appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 3. Scan migration files
$migrationsDir = $basePath . '/database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$pendingCount = 0;

foreach ($files as $file) {
    $version = basename($file);

    if (in_array($version, $appliedMigrations, true)) {
        continue;
    }

    $pendingCount++;
    echo "Applying migration: {$version}... ";
    $startTime = microtime(true);

    $sql = file_get_contents($file);

    try {
        // Execute migration
        $pdo->exec($sql);

        // Record as applied
        $insertStmt = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $insertStmt->execute([$version]);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "OK ({$duration} ms)\n";
    } catch (\Throwable $e) {
        echo "FAILED!\n";
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($pendingCount === 0) {
    echo "No new migrations to apply. Database is up to date.\n";
} else {
    echo "Successfully applied {$pendingCount} migration(s).\n";
}
