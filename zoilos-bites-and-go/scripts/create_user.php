<?php
declare(strict_types=1);

/**
 * Create a login, or reset the password of an existing one.
 *
 *   php scripts/create_user.php USERNAME EMAIL [admin|staff]      (default role: admin)
 *
 *   admin = full access.   staff = can only manage orders and sales.
 *
 * You will be asked for the password (it is not stored anywhere except as a bcrypt hash).
 * Rules: username 3-30 letters/numbers/underscores; password 8+ chars with upper, lower and a number.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require_once __DIR__ . '/../config/database.php';

[$self, $username, $email, $role] = array_pad($argv, 4, '');
$role = $role === '' ? 'admin' : strtolower($role);
$email = strtolower($email);
$password = getenv('ADMIN_PASSWORD') ?: '';

if ($username === '' || $email === '') {
    fwrite(STDERR, "Usage: php scripts/create_user.php USERNAME EMAIL [admin|staff]\n");
    exit(1);
}
if (!in_array($role, ['admin', 'staff'], true)) {
    fwrite(STDERR, "Role must be 'admin' or 'staff'.\n");
    exit(1);
}
if ($password === '') {
    fwrite(STDOUT, 'Password for the account: ');
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
        $pdo->prepare("UPDATE users SET password_hash = :h, email = :e, role = :r WHERE id = :id")
            ->execute(['h' => $hash, 'e' => $email, 'r' => $role, 'id' => $id]);
        echo "Account '{$username}' already existed: password, email and role ({$role}) were updated.\n";
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :h, :r)")
            ->execute(['u' => $username, 'e' => $email, 'h' => $hash, 'r' => $role]);
        echo ucfirst($role) . " account '{$username}' created. You can log in at public/login.php.\n";
    }
} catch (PDOException $ex) {
    fwrite(STDERR, 'Could not save the account: ' . $ex->getMessage() . "\n");
    exit(1);
}
