<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_guest();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf('public/register.php');

    $username = post_str('username');
    $email = mb_strtolower(post_str('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    $errors = [];
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3 to 30 characters: letters, numbers and underscores only.';
    }
    if ($email === '' || mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address (100 characters or fewer).';
    }
    if (strlen($password) < 8 || strlen($password) > 200
        || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter and a number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    $pdo = db();
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :u OR email = :e');
        $stmt->execute(['u' => $username, 'e' => $email]);
        foreach ($stmt->fetchAll() as $row) {
            if (strcasecmp($row['username'], $username) === 0) {
                $errors['username'] = 'That username is already taken.';
            }
            if (strcasecmp($row['email'], $email) === 0) {
                $errors['email'] = 'That email is already registered.';
            }
        }
    }

    if ($errors) {
        foreach ($errors as $msg) {
            flash('error', $msg);
        }
        set_old(['username' => $username, 'email' => $email]);
        redirect('public/register.php');
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :h, :r)');
        $stmt->execute(['u' => $username, 'e' => $email, 'h' => password_hash($password, PASSWORD_DEFAULT), 'r' => 'staff']);
    } catch (PDOException $ex) {
        if (is_constraint_error($ex)) {
            flash('error', 'That username or email is already registered.');
        } else {
            error_log('register failed: ' . $ex->getMessage());
            flash('error', 'Could not create your account. Please try again.');
        }
        set_old(['username' => $username, 'email' => $email]);
        redirect('public/register.php');
    }

    flash('success', 'Account created. You can log in now.');
    redirect('public/login.php');
}

$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>
<section class="form-card narrow">
    <h1>Create a staff account</h1>
    <form method="post" action="<?= e(app_url('public/register.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" maxlength="30" required autocomplete="username"
                   pattern="[A-Za-z0-9_]{3,30}" value="<?= e(old('username')) ?>" autofocus>
            <small>3 to 30 characters: letters, numbers, underscores.</small>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="100" required autocomplete="email" value="<?= e(old('email')) ?>">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="8" maxlength="200" required autocomplete="new-password">
            <small>At least 8 characters with uppercase, lowercase and a number.</small>
        </div>
        <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input id="password_confirm" name="password_confirm" type="password" minlength="8" maxlength="200" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit">Create account</button>
    </form>
    <p class="form-note">Already registered? <a href="<?= e(app_url('public/login.php')) ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
