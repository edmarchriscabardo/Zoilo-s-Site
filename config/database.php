<?php
declare(strict_types=1);

/**
 * Database connection (PDO).
 *
 * Defaults match a stock XAMPP / Laragon install (root, empty password).
 * To override, copy .env.example to .env in the project root.
 */

function load_env(string $file): array
{
    $vars = [];
    if (!is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim(trim($v), "\"'");
    }
    return $vars;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $env = load_env(dirname(__DIR__) . '/.env');
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $name = $env['DB_NAME'] ?? 'zoilos_bites_and_go';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Could not connect to the database. Check that MySQL is running and that database.sql has been imported.');
    }
    return $pdo;
}
