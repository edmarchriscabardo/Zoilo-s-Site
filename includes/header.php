<?php
declare(strict_types=1);
/** Expects: $pageTitle (string, optional). Auth must already be loaded. */

$pageTitle = $pageTitle ?? 'Zoilo\'s Bites & Go';
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$entity = (string)($_GET['e'] ?? '');
$activeKeys = [$script, $script . ':' . $entity];

$navMain = [
    ['Dashboard',  'public/dashboard.php',   ['dashboard.php']],
    ['Orders',     'public/orders.php',      ['orders.php', 'order_form.php', 'order_view.php']],
    ['Sales',      'public/sales.php',       ['sales.php', 'sale_form.php']],
    ['Closings',   'public/closings.php',    ['closings.php', 'closing_view.php']],
    ['Stock checks', 'public/stock_checks.php', ['stock_checks.php', 'stock_check_form.php', 'stock_check_view.php']],
];
$navManage = [
    ['Products',    'public/manage.php?e=product',    ['manage.php:product', 'manage_form.php:product']],
    ['Customers',   'public/manage.php?e=customer',   ['manage.php:customer', 'manage_form.php:customer']],
    ['Stock items', 'public/manage.php?e=stock_item', ['manage.php:stock_item', 'manage_form.php:stock_item']],
    ['Staff',       'public/manage.php?e=staff',      ['manage.php:staff', 'manage_form.php:staff']],
    ['Owners',      'public/manage.php?e=owner',      ['manage.php:owner', 'manage_form.php:owner']],
];
$isActive = static fn(array $keys): bool => (bool)array_intersect($keys, $activeKeys);
$manageActive = false;
foreach ($navManage as $item) {
    $manageActive = $manageActive || $isActive($item[2]);
}
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Zoilo's Bites &amp; Go</title>
    <link rel="icon" type="image/png" href="<?= e(asset_url('img/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="<?= e(app_url($user ? 'public/dashboard.php' : 'public/index.php')) ?>">
            <img class="brand-logo" src="<?= e(asset_url('img/logo.png')) ?>" alt="" width="44" height="44">
            <span class="brand-text">Zoilo's <span>Bites &amp; Go</span></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <?php if ($user): ?>
                <?php foreach ($navMain as [$label, $href, $keys]): ?>
                    <a href="<?= e(app_url($href)) ?>"<?= $isActive($keys) ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
                <details class="nav-more"<?= $manageActive ? ' open' : '' ?>>
                    <summary<?= $manageActive ? ' class="is-active"' : '' ?>>Manage</summary>
                    <div class="nav-more-list">
                        <?php foreach ($navManage as [$label, $href, $keys]): ?>
                            <a href="<?= e(app_url($href)) ?>"<?= $isActive($keys) ? ' class="is-active"' : '' ?>><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>
                <form class="nav-user" method="post" action="<?= e(app_url('actions/logout.php')) ?>">
                    <?= csrf_field() ?>
                    <span class="nav-username"><?= e($user['username']) ?> (<?= e($user['role']) ?>)</span>
                    <button type="submit" class="btn btn-small btn-ghost">Log out</button>
                </form>
            <?php else: ?>
                <a href="<?= e(app_url('public/index.php')) ?>"<?= $script === 'index.php' ? ' class="is-active"' : '' ?>>Menu</a>
                <a href="<?= e(app_url('public/login.php')) ?>"<?= $script === 'login.php' ? ' class="is-active"' : '' ?>>Log in</a>
                <a href="<?= e(app_url('public/register.php')) ?>"<?= $script === 'register.php' ? ' class="is-active"' : '' ?>>Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main id="main" class="container main">
<?php foreach (flash_pull() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
        <?= e($f['message']) ?>
    </div>
<?php endforeach; ?>
