<?php
declare(strict_types=1);

/**
 * Create a new admin account, or reset the password of an existing one.
 *
 *   php scripts/create_admin.php USERNAME EMAIL
 *
 * You will be asked for the password (it is not stored anywhere except as a bcrypt hash).
 * Rules: username 3-30 letters/numbers/underscores; password 8+ chars with upper, lower and a number.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require_once __DIR__ . '/../config/database.php';

[$self, $username, $email] = array_pad($argv, 3, '');
$email = strtolower($email);
$password = getenv('ADMIN_PASSWORD') ?: '';

if ($username === '' || $email === '') {
    fwrite(STDERR, "Usage: php scripts/create_admin.php USERNAME EMAIL\n");
    exit(1);
}
if ($password === '') {
    fwrite(STDOUT, 'Password for the new admin: ');
    $password = rtrim((string)fgets(STDIN), "\r\n");
}

$errors = [];
if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
    $errors[] = 'Username must be 3 to 30 characters: letters, numbers and underscores only.';
}
if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}
if (strlen($password) < 8 || strlen($password) > 200
    || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
    $errors[] = 'Password must be 8+ characters with an uppercase letter, a lowercase letter and a number.';
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

try {
    $pdo = db();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $find = $pdo->prepare('SELECT id FROM users WHERE username = :u');
    $find->execute(['u' => $username]);
    $id = $find->fetchColumn();

    if ($id !== false) {
        $pdo->prepare("UPDATE users SET password_hash = :h, email = :e, role = 'admin' WHERE id = :id")
            ->execute(['h' => $hash, 'e' => $email, 'id' => $id]);
        echo "Admin '{$username}' already existed: password and email were updated.\n";
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :h, 'admin')")
            ->execute(['u' => $username, 'e' => $email, 'h' => $hash]);
        echo "Admin '{$username}' created. You can log in at public/login.php.\n";
    }
} catch (PDOException $ex) {
    fwrite(STDERR, 'Could not save the admin account: ' . $ex->getMessage() . "\n");
    exit(1);
}
