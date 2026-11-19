<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$id = get_id('id');
if ($id === null) {
    redirect('public/stock_checks.php');
}

$stmt = $pdo->prepare('SELECT * FROM stock_check WHERE check_id = :id');
$stmt->execute(['id' => $id]);
$check = $stmt->fetch();
if (!$check) {
    flash('error', 'Stock check not found.');
    redirect('public/stock_checks.php');
}

$stmt = $pdo->prepare(
    'SELECT si.item_name, si.minimum_level, d.quantity_left
       FROM stock_check_detail d JOIN stock_item si ON si.stock_item_id = d.stock_item_id
      WHERE d.check_id = :id ORDER BY si.item_name'
);
$stmt->execute(['id' => $id]);
$rows = $stmt->fetchAll();

$pageTitle = 'Stock check #' . $id;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Stock check #<?= (int)$id ?> <span class="muted"><?= e($check['check_date']) ?></span></h1>
    <div class="head-actions">
        <a class="btn" href="<?= e(app_url('public/stock_check_form.php?id=' . $id)) ?>">Edit</a>
        <a class="btn btn-ghost" href="<?= e(app_url('public/stock_checks.php')) ?>">All stock checks</a>
    </div>
</div>

<?php if (!$rows): ?>
    <p class="empty">No items were counted in this check.</p>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th scope="col">Item</th><th scope="col" class="num">Left</th><th scope="col" class="num">Minimum</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $low = (int)$r['quantity_left'] < (int)$r['minimum_level']; ?>
            <tr>
                <td><?= e($r['item_name']) ?></td>
                <td class="num"><?= (int)$r['quantity_left'] ?></td>
                <td class="num"><?= (int)$r['minimum_level'] ?></td>
                <td><?= $low ? badge('Low') : badge('OK') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
