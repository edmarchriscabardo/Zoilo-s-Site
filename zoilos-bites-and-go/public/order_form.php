<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$id = get_id('id');
$order = null;
$sale = null;
$items = [];
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT o.*, c.customer_name FROM customer_order o JOIN customer c ON c.customer_id = o.customer_id WHERE o.order_id = :id');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch() ?: null;
    if ($order === null) {
        flash('error', 'Order not found.');
        redirect('public/orders.php');
    }
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM order_item WHERE order_id = :id ORDER BY product_id');
    $stmt->execute(['id' => $id]);
    $items = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT amount, payment_status FROM sale WHERE order_id = :id');
    $stmt->execute(['id' => $id]);
    $sale = $stmt->fetch() ?: null;
}

$customers = $pdo->query('SELECT customer_id, customer_name FROM customer ORDER BY customer_name')->fetchAll();
$products = $pdo->query('SELECT product_id, product_name, price FROM product ORDER BY product_name')->fetchAll();

// Values: re-filled input after a failed save, else the saved order, else defaults.
$v = [
    'customer_name' => has_old() ? (string)old('customer_name') : (string)($order['customer_name'] ?? ''),
    'payment_status' => has_old() ? (string)old('payment_status') : (string)($sale['payment_status'] ?? 'Paid'),
    'amount'       => has_old() ? (string)old('amount') : ($sale ? number_format((float)$sale['amount'], 2, '.', '') : ''),
    'order_date'   => has_old() ? (string)old('order_date') : (string)($order['order_date'] ?? date('Y-m-d')),
    'order_time'   => has_old() ? (string)old('order_time') : substr((string)($order['order_time'] ?? date('H:i:s')), 0, 5),
    'order_status' => has_old() ? (string)old('order_status') : (string)($order['order_status'] ?? 'Completed'),
];
if (has_old() && is_array(old('items'))) {
    $items = [];
    foreach (old('items') as $r) {
        if (is_array($r)) {
            $items[] = ['product_id' => (string)($r['product_id'] ?? ''), 'quantity' => (string)($r['quantity'] ?? '')];
        }
    }
}
if (!$items) {
    $items = [['product_id' => '', 'quantity' => '1']];
}

$renderRow = static function (string $index, string $productId, string $qty) use ($products): string {
    ob_start(); ?>
    <div class="item-row" data-item-row>
        <div class="field">
            <label>Product
                <select name="items[<?= e($index) ?>][product_id]" data-item-product>
                    <option value="" data-price="0">Choose a product...</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['product_id'] ?>" data-price="<?= e($p['price']) ?>"<?= (string)$p['product_id'] === $productId ? ' selected' : '' ?>>
                            <?= e($p['product_name']) ?> (<?= e(money($p['price'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="field field-qty">
            <label>Quantity
                <input type="number" name="items[<?= e($index) ?>][quantity]" min="1" max="1000" step="1" value="<?= e($qty) ?>" data-item-qty>
            </label>
        </div>
        <button type="button" class="btn btn-small btn-danger" data-remove-row>Remove</button>
    </div>
    <?php
    return (string)ob_get_clean();
};

$pageTitle = $order ? 'Edit order #' . $id : 'New order & sale';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-ghost" href="<?= e(app_url($order ? 'public/order_view.php?id=' . $id : 'public/orders.php')) ?>">Cancel</a>
</div>

<?php if (!$products): ?>
    <p class="empty">You need at least one product before you can take an order.
        <a href="<?= e(app_url('public/manage.php?e=product')) ?>">Products</a></p>
<?php else: ?>
<section class="form-card">
    <form method="post" action="<?= e(app_url('actions/order_save.php')) ?>" novalidate data-order-form>
        <?= csrf_field() ?>
        <?php if ($order): ?><input type="hidden" name="order_id" value="<?= (int)$id ?>"><?php endif; ?>

        <div class="field">
            <label for="customer_name">Customer name</label>
            <input id="customer_name" name="customer_name" type="text" maxlength="100" required list="customer-names"
                   autocomplete="off" placeholder="Type the customer's name" value="<?= e($v['customer_name']) ?>"<?= $order ? '' : ' autofocus' ?>>
            <datalist id="customer-names">
                <?php foreach ($customers as $c): ?>
                    <option value="<?= e($c['customer_name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <small>Pick a returning customer from the list, or type a new name to add them.</small>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="order_date">Order date</label>
                <input id="order_date" name="order_date" type="date" required value="<?= e($v['order_date']) ?>">
            </div>
            <div class="field">
                <label for="order_time">Order time</label>
                <input id="order_time" name="order_time" type="time" required value="<?= e($v['order_time']) ?>">
            </div>
            <div class="field">
                <label for="order_status">Status</label>
                <select id="order_status" name="order_status" required>
                    <?php foreach (ORDER_STATUSES as $s): ?>
                        <option value="<?= e($s) ?>"<?= $s === $v['order_status'] ? ' selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <fieldset class="items-box">
            <legend>Items</legend>
            <div data-items>
                <?php foreach (array_values($items) as $i => $it): ?>
                    <?= $renderRow((string)$i, (string)$it['product_id'], (string)$it['quantity']) ?>
                <?php endforeach; ?>
            </div>
            <template data-item-template><?= $renderRow('__INDEX__', '', '1') ?></template>
            <div class="items-footer">
                <button type="button" class="btn btn-small" data-add-row>Add another item</button>
                <p class="items-total">Estimated total: <strong data-items-total>&#8369;0.00</strong></p>
            </div>
        </fieldset>

        <fieldset class="items-box">
            <legend>Payment</legend>
            <div class="field-grid">
                <div class="field">
                    <label for="payment_status">Payment status</label>
                    <select id="payment_status" name="payment_status" required>
                        <?php foreach (PAYMENT_STATUSES as $s): ?>
                            <option value="<?= e($s) ?>"<?= $s === $v['payment_status'] ? ' selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="amount">Sale amount (PHP)</label>
                    <input id="amount" name="amount" type="number" inputmode="decimal" min="0" step="0.01" max="99999999.99"
                           value="<?= e($v['amount']) ?>" data-order-amount<?= $v['amount'] !== '' ? ' data-manual="1"' : '' ?>>
                    <small>Fills in from the items. Change it for a discount, or <button type="button" class="link-button" data-amount-reset>use the order total</button>. Ignored if the order is cancelled.</small>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save order &amp; sale</button>
            <a class="btn btn-ghost" href="<?= e(app_url($order ? 'public/order_view.php?id=' . $id : 'public/orders.php')) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
