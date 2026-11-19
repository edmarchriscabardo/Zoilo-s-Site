<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$status = (string)($_GET['status'] ?? '');
$status = in_array($status, ORDER_STATUSES, true) ? $status : '';
$date = (string)($_GET['date'] ?? '');
$date = valid_date($date) ? $date : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'o.order_status = :status';
    $params['status'] = $status;
}
if ($date !== '') {
    $where[] = 'o.order_date = :date';
    $params['date'] = $date;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_order o {$whereSql}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    "SELECT o.order_id, o.order_date, o.order_time, o.order_status, c.customer_name,
            COALESCE(SUM(oi.quantity * p.price), 0) AS total,
            s.sale_id, s.payment_status
       FROM customer_order o
       JOIN customer c ON c.customer_id = o.customer_id
       LEFT JOIN order_item oi ON oi.order_id = o.order_id
       LEFT JOIN product p ON p.product_id = oi.product_id
       LEFT JOIN sale s ON s.order_id = o.order_id
       {$whereSql}
      GROUP BY o.order_id, o.order_date, o.order_time, o.order_status, c.customer_name, s.sale_id, s.payment_status
      ORDER BY o.order_date DESC, o.order_time DESC, o.order_id DESC
      LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Orders</h1>
    <a class="btn btn-primary" href="<?= e(app_url('public/order_form.php')) ?>">New order</a>
</div>

<form class="search-bar" method="get" action="<?= e(app_url('public/orders.php')) ?>">
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (ORDER_STATUSES as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === $status ? ' selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="date">Order date</label>
    <input id="date" name="date" type="date" value="<?= e($date) ?>">
    <button class="btn" type="submit">Filter</button>
    <?php if ($status !== '' || $date !== ''): ?><a class="btn btn-ghost" href="<?= e(app_url('public/orders.php')) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$orders): ?>
    <p class="empty"><?= ($status !== '' || $date !== '') ? 'No orders match those filters.' : 'No orders yet. Take the first one.' ?></p>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th scope="col">Order</th><th scope="col">Date and time</th><th scope="col">Customer</th>
                <th scope="col">Status</th><th scope="col" class="num">Total</th><th scope="col">Payment</th>
                <th scope="col" class="actions-col">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="<?= e(app_url('public/order_view.php?id=' . (int)$o['order_id'])) ?>">#<?= (int)$o['order_id'] ?></a></td>
                <td><?= e($o['order_date']) ?> <?= e(substr($o['order_time'], 0, 5)) ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= badge($o['order_status']) ?></td>
                <td class="num"><?= e(money($o['total'])) ?></td>
                <td><?= $o['sale_id'] ? badge($o['payment_status']) : '<span class="muted">No sale yet</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-small" href="<?= e(app_url('public/order_view.php?id=' . (int)$o['order_id'])) ?>">View</a>
                    <a class="btn btn-small" href="<?= e(app_url('public/order_form.php?id=' . (int)$o['order_id'])) ?>">Edit</a>
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?= e(app_url('actions/order_delete.php')) ?>" data-confirm="Delete order #<?= (int)$o['order_id'] ?> and its items?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="table-meta"><?= $total ?> order(s). Totals use current product prices.</p>
<?= render_pagination($page, $pages, ['status' => $status, 'date' => $date]) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
