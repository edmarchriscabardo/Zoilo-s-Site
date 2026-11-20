<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Menu';
$products = db()->query('SELECT product_name, product_type, price, image FROM product ORDER BY product_type, price DESC, product_name')->fetchAll();

$groups = [];
foreach ($products as $p) {
    $groups[$p['product_type']][] = $p;
}
// Show the sisig dishes first, then the rest alphabetically.
$order = ['Silog', 'Rice Meal', 'Main', 'Snack', 'Add-on', 'Drink'];
uksort($groups, function (string $a, string $b) use ($order): int {
    $ia = array_search($a, $order, true);
    $ib = array_search($b, $order, true);
    $ia = $ia === false ? 99 : $ia;
    $ib = $ib === false ? 99 : $ib;
    return $ia <=> $ib ?: strcmp($a, $b);
});

require __DIR__ . '/../includes/header.php';
?>
<section class="hero">
    <div>
    <h1>Chicken sisig, sizzling and ready to go.</h1>
    <p>Chopped, grilled, and finished with onion, chili and calamansi. Eat it at the counter or take a Go Box with you.</p>
    <?php if (!is_logged_in()): ?>
        <p><a class="btn btn-light" href="<?= e(app_url('public/login.php')) ?>">Log in</a></p>
    <?php else: ?>
        <p><a class="btn btn-light" href="<?= e(app_url('public/dashboard.php')) ?>">Open dashboard</a></p>
    <?php endif; ?>
    </div>
    <img class="hero-logo" src="<?= e(asset_url('img/logo.png')) ?>" alt="Zoilo's Bites &amp; Go logo" width="220" height="220">
</section>

<?php if (!$groups): ?>
    <p class="empty">The menu is being updated. Please check back soon.</p>
<?php endif; ?>

<?php foreach ($groups as $type => $items): ?>
    <section class="menu-group">
        <h2><?= e($type) ?></h2>
        <ul class="menu-grid">
            <?php foreach ($items as $item): ?>
                <li class="menu-card">
                    <img src="<?= e(product_image_url($item['image'])) ?>" alt="<?= e($item['product_name']) ?>" loading="lazy" width="300" height="300">
                    <div class="menu-card-body">
                        <span class="menu-name"><?= e($item['product_name']) ?></span>
                        <span class="menu-price"><?= e(money($item['price'])) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
