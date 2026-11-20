<?php
declare(strict_types=1);

/**
 * Imports database.sql using PDO (no mysql command-line client needed).
 * Run:  php scripts/install.php
 * WARNING: database.sql drops and recreates the zoilos_bites_and_go database.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require_once __DIR__ . '/../config/database.php';

$env = load_env(dirname(__DIR__) . '/.env');
$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306');

try {
    $pdo = new PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to MySQL: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Make sure MySQL/MariaDB is running and the PHP pdo_mysql extension is enabled." . PHP_EOL);
    exit(1);
}

$sql = file_get_contents(dirname(__DIR__) . '/database.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read database.sql" . PHP_EOL);
    exit(1);
}

// Drop comment-only lines, then split into statements at ";" at end of line.
$lines = array_filter(explode("\n", str_replace("\r\n", "\n", $sql)), static fn(string $l): bool => !preg_match('/^\s*--/', $l));
$statements = preg_split('/;\s*\n/', implode("\n", $lines)) ?: [];

$count = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $count++;
    } catch (PDOException $e) {
        fwrite(STDERR, "Failed on statement #" . ($count + 1) . ": " . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, substr($stmt, 0, 200) . PHP_EOL);
        exit(1);
    }
}

echo "Database installed ({$count} statements). Log in with admin / Admin@123." . PHP_EOL;
