<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$id = get_id('id');
$check = null;
$saved = [];
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM stock_check WHERE check_id = :id');
    $stmt->execute(['id' => $id]);
    $check = $stmt->fetch() ?: null;
    if ($check === null) {
        flash('error', 'Stock check not found.');
        redirect('public/stock_checks.php');
    }
    $stmt = $pdo->prepare('SELECT stock_item_id, quantity_left FROM stock_check_detail WHERE check_id = :id');
    $stmt->execute(['id' => $id]);
    foreach ($stmt->fetchAll() as $r) {
        $saved[(int)$r['stock_item_id']] = (string)$r['quantity_left'];
    }
}

$stockItems = $pdo->query('SELECT stock_item_id, item_name, minimum_level FROM stock_item ORDER BY item_name')->fetchAll();

$date = has_old() ? (string)old('check_date') : (string)($check['check_date'] ?? date('Y-m-d'));
$oldQty = has_old() && is_array(old('qty')) ? old('qty') : null;
$qtyFor = static function (int $itemId) use ($saved, $oldQty): string {
    if ($oldQty !== null) {
        return is_string($oldQty[$itemId] ?? null) ? $oldQty[$itemId] : '';
    }
    return $saved[$itemId] ?? '';
};

$pageTitle = $check ? 'Edit stock check #' . $id : 'New stock check';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-ghost" href="<?= e(app_url('public/stock_checks.php')) ?>">Back to stock checks</a>
</div>

<?php if (!$stockItems): ?>
    <p class="empty">Add stock items first. <a href="<?= e(app_url('public/manage_form.php?e=stock_item')) ?>">Add a stock item</a></p>
<?php else: ?>
<section class="form-card">
    <form method="post" action="<?= e(app_url('actions/stock_check_save.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($check): ?><input type="hidden" name="check_id" value="<?= (int)$id ?>"><?php endif; ?>

        <div class="field">
            <label for="check_date">Check date</label>
            <input id="check_date" name="check_date" type="date" required value="<?= e($date) ?>">
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Item</th><th scope="col" class="num">Minimum</th><th scope="col">Quantity left</th></tr></thead>
                <tbody>
                <?php foreach ($stockItems as $s): $sid = (int)$s['stock_item_id']; ?>
                    <tr>
                        <td><label for="qty_<?= $sid ?>"><?= e($s['item_name']) ?></label></td>
                        <td class="num"><?= (int)$s['minimum_level'] ?></td>
                        <td><input id="qty_<?= $sid ?>" class="qty-input" name="qty[<?= $sid ?>]" type="number" min="0" max="1000000" step="1" inputmode="numeric" value="<?= e($qtyFor($sid)) ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="form-note">Leave a row empty if you did not count that item.</p>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save stock check</button>
            <a class="btn btn-ghost" href="<?= e(app_url('public/stock_checks.php')) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
