<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$total = (int)$pdo->query('SELECT COUNT(*) FROM stock_check')->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    'SELECT sc.check_id, sc.check_date,
            COUNT(d.stock_item_id) AS items_counted,
            COALESCE(SUM(CASE WHEN d.quantity_left < si.minimum_level THEN 1 ELSE 0 END), 0) AS low_items
       FROM stock_check sc
       LEFT JOIN stock_check_detail d ON d.check_id = sc.check_id
       LEFT JOIN stock_item si ON si.stock_item_id = d.stock_item_id
      GROUP BY sc.check_id, sc.check_date
      ORDER BY sc.check_date DESC, sc.check_id DESC
      LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$checks = $stmt->fetchAll();

$pageTitle = 'Stock checks';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Stock checks</h1>
    <a class="btn btn-primary" href="<?= e(app_url('public/stock_check_form.php')) ?>">New stock check</a>
</div>

<?php if (!$checks): ?>
    <p class="empty">No stock checks yet. Count what is left in the kitchen and record it.</p>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th scope="col">Check</th><th scope="col">Date</th><th scope="col" class="num">Items counted</th><th scope="col">Result</th><th scope="col" class="actions-col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): ?>
            <tr>
                <td><a href="<?= e(app_url('public/stock_check_view.php?id=' . (int)$c['check_id'])) ?>">#<?= (int)$c['check_id'] ?></a></td>
                <td><?= e($c['check_date']) ?></td>
                <td class="num"><?= (int)$c['items_counted'] ?></td>
                <td><?= (int)$c['low_items'] > 0 ? badge('Low') . ' <span class="muted">' . (int)$c['low_items'] . ' item(s) below minimum</span>' : badge('OK') ?></td>
                <td class="actions">
                    <a class="btn btn-small" href="<?= e(app_url('public/stock_check_view.php?id=' . (int)$c['check_id'])) ?>">View</a>
                    <a class="btn btn-small" href="<?= e(app_url('public/stock_check_form.php?id=' . (int)$c['check_id'])) ?>">Edit</a>
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?= e(app_url('actions/stock_check_delete.php')) ?>" data-confirm="Delete stock check #<?= (int)$c['check_id'] ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="check_id" value="<?= (int)$c['check_id'] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="table-meta"><?= $total ?> stock check(s)</p>
<?= render_pagination($page, $pages) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
