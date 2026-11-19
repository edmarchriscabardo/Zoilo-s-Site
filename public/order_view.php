<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$id = get_id('id');
if ($id === null) {
    redirect('public/orders.php');
}

$stmt = $pdo->prepare(
    'SELECT o.*, c.customer_name, c.contact_number, c.address
       FROM customer_order o JOIN customer c ON c.customer_id = o.customer_id
      WHERE o.order_id = :id'
);
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();
if (!$order) {
    flash('error', 'Order not found.');
    redirect('public/orders.php');
}

$stmt = $pdo->prepare(
    'SELECT p.product_name, p.product_type, p.price, oi.quantity, (p.price * oi.quantity) AS line_total
       FROM order_item oi JOIN product p ON p.product_id = oi.product_id
      WHERE oi.order_id = :id ORDER BY p.product_name'
);
$stmt->execute(['id' => $id]);
$items = $stmt->fetchAll();
$total = array_sum(array_map(static fn(array $r): float => (float)$r['line_total'], $items));

$stmt = $pdo->prepare(
    'SELECT s.sale_id, s.amount, s.payment_status, dc.closing_date, dc.closing_id
       FROM sale s JOIN daily_closing dc ON dc.closing_id = s.closing_id
      WHERE s.order_id = :id'
);
$stmt->execute(['id' => $id]);
$sale = $stmt->fetch() ?: null;

$pageTitle = 'Order #' . $id;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Order #<?= (int)$id ?> <?= badge($order['order_status']) ?></h1>
    <div class="head-actions">
        <a class="btn" href="<?= e(app_url('public/order_form.php?id=' . $id)) ?>">Edit order</a>
        <a class="btn btn-ghost" href="<?= e(app_url('public/orders.php')) ?>">All orders</a>
    </div>
</div>

<div class="two-col">
    <section class="info-card">
        <h2>Customer</h2>
        <dl class="details">
            <dt>Name</dt><dd><?= e($order['customer_name']) ?></dd>
            <dt>Contact</dt><dd><?= $order['contact_number'] ? e($order['contact_number']) : '<span class="muted">&mdash;</span>' ?></dd>
            <dt>Address</dt><dd><?= $order['address'] ? e($order['address']) : '<span class="muted">&mdash;</span>' ?></dd>
            <dt>Placed</dt><dd><?= e($order['order_date']) ?> at <?= e(substr($order['order_time'], 0, 5)) ?></dd>
        </dl>

        <form class="inline-form" method="post" action="<?= e(app_url('actions/order_status.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int)$id ?>">
            <label for="order_status">Change status</label>
            <select id="order_status" name="order_status">
                <?php foreach (ORDER_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>"<?= $s === $order['order_status'] ? ' selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small" type="submit">Update status</button>
        </form>
    </section>

    <section class="info-card">
        <h2>Sale</h2>
        <?php if ($sale): ?>
            <dl class="details">
                <dt>Amount</dt><dd><?= e(money($sale['amount'])) ?></dd>
                <dt>Payment</dt><dd><?= badge($sale['payment_status']) ?></dd>
                <dt>Closing</dt><dd><a href="<?= e(app_url('public/closing_view.php?id=' . (int)$sale['closing_id'])) ?>"><?= e($sale['closing_date']) ?></a></dd>
            </dl>
            <a class="btn btn-small" href="<?= e(app_url('public/sale_form.php?id=' . (int)$sale['sale_id'])) ?>">Edit sale</a>
        <?php elseif ($order['order_status'] === 'Cancelled'): ?>
            <p class="empty">This order is cancelled, so no sale can be recorded.</p>
        <?php else: ?>
            <p class="empty">No sale recorded for this order yet.</p>
            <a class="btn btn-primary" href="<?= e(app_url('public/sale_form.php?order_id=' . $id)) ?>">Record sale</a>
        <?php endif; ?>
    </section>
</div>

<section>
    <h2>Items</h2>
    <?php if (!$items): ?>
        <p class="empty">This order has no items.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Product</th><th scope="col">Type</th><th scope="col" class="num">Price</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Line total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['product_name']) ?></td>
                    <td><?= e($it['product_type']) ?></td>
                    <td class="num"><?= e(money($it['price'])) ?></td>
                    <td class="num"><?= (int)$it['quantity'] ?></td>
                    <td class="num"><?= e(money($it['line_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th scope="row" colspan="4" class="num">Order total (current prices)</th><td class="num"><strong><?= e(money($total)) ?></strong></td></tr></tfoot>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
