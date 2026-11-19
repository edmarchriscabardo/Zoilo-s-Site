<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_guest();

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 300;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf('public/login.php');

    if (($_SESSION['login_locked_until'] ?? 0) > time()) {
        $wait = (int)ceil(($_SESSION['login_locked_until'] - time()) / 60);
        flash('error', "Too many failed attempts. Try again in about {$wait} minute(s).");
        redirect('public/login.php');
    }

    $identifier = post_str('identifier');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if ($identifier === '' || $password === '' || mb_strlen($identifier) > 100 || strlen($password) > 200) {
        flash('error', 'Enter your username (or email) and password.');
        set_old(['identifier' => $identifier]);
        redirect('public/login.php');
    }

    $stmt = db()->prepare('SELECT id, username, email, password_hash, role FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute(['u' => $identifier, 'e' => $identifier]);
    $user = $stmt->fetch();

    // Verify against a dummy hash when the user does not exist, so timing does not reveal valid usernames.
    $dummy = '$2y$12$jJMx2rCvEqgivfC1vk2fqO9Wl1WKaH9M/POdjZB2IsGtwh4rea5kG'; // a real bcrypt hash, used only for timing
    $ok = password_verify($password, $user['password_hash'] ?? $dummy) && $user !== false;

    if (!$ok) {
        $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
            $_SESSION['login_attempts'] = 0;
        }
        flash('error', 'Incorrect username or password.');
        set_old(['identifier' => $identifier]);
        redirect('public/login.php');
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $upd = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $upd->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
    }

    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    login_user($user);
    flash('success', 'Welcome back, ' . $user['username'] . '.');
    redirect('public/dashboard.php');
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<section class="form-card narrow">
    <h1>Log in</h1>
    <form method="post" action="<?= e(app_url('public/login.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="identifier">Username or email</label>
            <input id="identifier" name="identifier" type="text" maxlength="100" required autocomplete="username"
                   value="<?= e(old('identifier')) ?>" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="200" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary" type="submit">Log in</button>
    </form>
    <p class="form-note">No account yet? <a href="<?= e(app_url('public/register.php')) ?>">Register</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
