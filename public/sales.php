<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$status = (string)($_GET['status'] ?? '');
$status = in_array($status, PAYMENT_STATUSES, true) ? $status : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$whereSql = $status !== '' ? 'WHERE s.payment_status = :status' : '';
$params = $status !== '' ? ['status' => $status] : [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sale s {$whereSql}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    "SELECT s.sale_id, s.amount, s.payment_status, s.order_id, s.closing_id, dc.closing_date, c.customer_name
       FROM sale s
       JOIN daily_closing dc ON dc.closing_id = s.closing_id
       JOIN customer_order o ON o.order_id = s.order_id
       JOIN customer c ON c.customer_id = o.customer_id
       {$whereSql}
      ORDER BY dc.closing_date DESC, s.sale_id DESC
      LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$sales = $stmt->fetchAll();

$pageTitle = 'Sales';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Sales</h1>
    <a class="btn btn-primary" href="<?= e(app_url('public/sale_form.php')) ?>">Record sale</a>
</div>

<form class="search-bar" method="get" action="<?= e(app_url('public/sales.php')) ?>">
    <label class="sr-only" for="status">Payment status</label>
    <select id="status" name="status">
        <option value="">All payment statuses</option>
        <?php foreach (PAYMENT_STATUSES as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === $status ? ' selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
    <?php if ($status !== ''): ?><a class="btn btn-ghost" href="<?= e(app_url('public/sales.php')) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$sales): ?>
    <p class="empty"><?= $status !== '' ? 'No sales with that payment status.' : 'No sales recorded yet.' ?></p>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th scope="col">Sale</th><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Closing date</th>
                <th scope="col" class="num">Amount</th><th scope="col">Payment</th><th scope="col" class="actions-col">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
            <tr>
                <td>#<?= (int)$s['sale_id'] ?></td>
                <td><a href="<?= e(app_url('public/order_view.php?id=' . (int)$s['order_id'])) ?>">#<?= (int)$s['order_id'] ?></a></td>
                <td><?= e($s['customer_name']) ?></td>
                <td><a href="<?= e(app_url('public/closing_view.php?id=' . (int)$s['closing_id'])) ?>"><?= e($s['closing_date']) ?></a></td>
                <td class="num"><?= e(money($s['amount'])) ?></td>
                <td><?= badge($s['payment_status']) ?></td>
                <td class="actions">
                    <a class="btn btn-small" href="<?= e(app_url('public/sale_form.php?id=' . (int)$s['sale_id'])) ?>">Edit</a>
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?= e(app_url('actions/sale_delete.php')) ?>" data-confirm="Delete sale #<?= (int)$s['sale_id'] ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sale_id" value="<?= (int)$s['sale_id'] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="table-meta"><?= $total ?> sale(s). Daily closing totals count only sales marked Paid.</p>
<?= render_pagination($page, $pages, ['status' => $status]) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
