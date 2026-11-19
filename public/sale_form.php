<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$id = get_id('id');
$sale = null;
if ($id !== null) {
    $stmt = $pdo->prepare(
        'SELECT s.*, dc.closing_date, o.order_status, c.customer_name
           FROM sale s
           JOIN daily_closing dc ON dc.closing_id = s.closing_id
           JOIN customer_order o ON o.order_id = s.order_id
           JOIN customer c ON c.customer_id = o.customer_id
          WHERE s.sale_id = :id'
    );
    $stmt->execute(['id' => $id]);
    $sale = $stmt->fetch() ?: null;
    if ($sale === null) {
        flash('error', 'Sale not found.');
        redirect('public/sales.php');
    }
}

// Orders that can still receive a sale (not cancelled, no sale yet), with their current total.
$orders = [];
if ($sale === null) {
    $orders = $pdo->query(
        "SELECT o.order_id, o.order_date, c.customer_name, COALESCE(SUM(oi.quantity * p.price), 0) AS total
           FROM customer_order o
           JOIN customer c ON c.customer_id = o.customer_id
           LEFT JOIN order_item oi ON oi.order_id = o.order_id
           LEFT JOIN product p ON p.product_id = oi.product_id
          WHERE o.order_status <> 'Cancelled'
            AND NOT EXISTS (SELECT 1 FROM sale s WHERE s.order_id = o.order_id)
          GROUP BY o.order_id, o.order_date, c.customer_name
          ORDER BY o.order_id DESC"
    )->fetchAll();
}

$preOrder = (string)(get_id('order_id') ?? '');
$v = [
    'order_id'       => has_old() ? (string)old('order_id') : $preOrder,
    'amount'         => has_old() ? (string)old('amount') : (string)($sale['amount'] ?? ''),
    'payment_status' => has_old() ? (string)old('payment_status') : (string)($sale['payment_status'] ?? 'Paid'),
    'closing_date'   => has_old() ? (string)old('closing_date') : (string)($sale['closing_date'] ?? date('Y-m-d')),
];

$pageTitle = $sale ? 'Edit sale #' . $id : 'Record sale';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-ghost" href="<?= e(app_url('public/sales.php')) ?>">Back to sales</a>
</div>

<?php if ($sale === null && !$orders): ?>
    <p class="empty">Every open order already has a sale. <a href="<?= e(app_url('public/order_form.php')) ?>">Take a new order</a> first.</p>
<?php else: ?>
<section class="form-card">
    <form method="post" action="<?= e(app_url('actions/sale_save.php')) ?>" novalidate data-sale-form>
        <?= csrf_field() ?>
        <?php if ($sale): ?>
            <input type="hidden" name="sale_id" value="<?= (int)$id ?>">
            <div class="field">
                <span class="label">Order</span>
                <p class="static-value"><a href="<?= e(app_url('public/order_view.php?id=' . (int)$sale['order_id'])) ?>">#<?= (int)$sale['order_id'] ?></a>
                    for <?= e($sale['customer_name']) ?> (<?= e($sale['order_status']) ?>)</p>
            </div>
        <?php else: ?>
            <div class="field">
                <label for="order_id">Order</label>
                <select id="order_id" name="order_id" required data-sale-order>
                    <option value="" data-total="">Choose an order...</option>
                    <?php foreach ($orders as $o): ?>
                        <option value="<?= (int)$o['order_id'] ?>" data-total="<?= e(number_format((float)$o['total'], 2, '.', '')) ?>"<?= (string)$o['order_id'] === $v['order_id'] ? ' selected' : '' ?>>
                            #<?= (int)$o['order_id'] ?> | <?= e($o['customer_name']) ?> | <?= e($o['order_date']) ?> | <?= e(money($o['total'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="field-grid">
            <div class="field">
                <label for="amount">Amount (PHP)</label>
                <input id="amount" name="amount" type="number" inputmode="decimal" min="0" step="0.01" max="99999999.99" required value="<?= e($v['amount']) ?>" data-sale-amount>
                <small>Filled in from the order total when you pick an order. You can change it.</small>
            </div>
            <div class="field">
                <label for="payment_status">Payment status</label>
                <select id="payment_status" name="payment_status" required>
                    <?php foreach (PAYMENT_STATUSES as $s): ?>
                        <option value="<?= e($s) ?>"<?= $s === $v['payment_status'] ? ' selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="closing_date">Daily closing date</label>
                <input id="closing_date" name="closing_date" type="date" required value="<?= e($v['closing_date']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save sale</button>
            <a class="btn btn-ghost" href="<?= e(app_url('public/sales.php')) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
