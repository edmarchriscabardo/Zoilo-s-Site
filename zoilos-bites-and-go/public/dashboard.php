<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();
$today = date('Y-m-d');

$one = static function (string $sql, array $params = []) use ($pdo): mixed {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
};

$salesToday = (float)$one(
    "SELECT COALESCE(SUM(s.amount), 0) FROM sale s
       JOIN daily_closing dc ON dc.closing_id = s.closing_id
      WHERE dc.closing_date = :d AND s.payment_status = 'Paid'", ['d' => $today]);
$expensesToday = (float)$one(
    'SELECT COALESCE(SUM(x.expense_amount), 0) FROM expense x
       JOIN daily_closing dc ON dc.closing_id = x.closing_id
      WHERE dc.closing_date = :d', ['d' => $today]);
$ordersToday = (int)$one('SELECT COUNT(*) FROM customer_order WHERE order_date = :d', ['d' => $today]);
$pendingOrders = (int)$one("SELECT COUNT(*) FROM customer_order WHERE order_status = 'Pending'");
$unpaidSales = (int)$one("SELECT COUNT(*) FROM sale WHERE payment_status <> 'Paid'");

// Low stock, from the most recent stock check
$latestCheck = $pdo->query('SELECT check_id, check_date FROM stock_check ORDER BY check_date DESC, check_id DESC LIMIT 1')->fetch();
$lowStock = [];
if ($latestCheck) {
    $stmt = $pdo->prepare(
        'SELECT si.item_name, si.minimum_level, d.quantity_left
           FROM stock_check_detail d
           JOIN stock_item si ON si.stock_item_id = d.stock_item_id
          WHERE d.check_id = :c AND d.quantity_left < si.minimum_level
          ORDER BY si.item_name');
    $stmt->execute(['c' => $latestCheck['check_id']]);
    $lowStock = $stmt->fetchAll();
}

// Orders & sales table (status can be changed right here)
$show = (string)($_GET['show'] ?? 'attention');
$show = in_array($show, ['attention', 'today', 'all'], true) ? $show : 'attention';
$where = match ($show) {
    'today' => 'o.order_date = :today',
    'all'   => '1 = 1',
    default => "(o.order_date = :today OR o.order_status = 'Pending'
                 OR (o.order_status <> 'Cancelled' AND (s.sale_id IS NULL OR s.payment_status <> 'Paid')))",
};
$stmt = $pdo->prepare(
    "SELECT o.order_id, o.order_date, o.order_time, o.order_status, c.customer_name,
            s.sale_id, s.amount, s.payment_status,
            COALESCE(SUM(oi.quantity * p.price), 0) AS total
       FROM customer_order o
       JOIN customer c ON c.customer_id = o.customer_id
       LEFT JOIN sale s ON s.order_id = o.order_id
       LEFT JOIN order_item oi ON oi.order_id = o.order_id
       LEFT JOIN product p ON p.product_id = oi.product_id
      WHERE {$where}
      GROUP BY o.order_id, o.order_date, o.order_time, o.order_status, c.customer_name, s.sale_id, s.amount, s.payment_status
      ORDER BY o.order_date DESC, o.order_time DESC, o.order_id DESC
      LIMIT 40"
);
$stmt->execute($show === 'all' ? [] : ['today' => $today]);
$orderRows = $stmt->fetchAll();

$week = $pdo->query(
    'SELECT closing_date, total_sales, total_expenses FROM daily_closing ORDER BY closing_date DESC LIMIT 7')->fetchAll();
$week = array_reverse($week);
$weekMax = 1.0;
foreach ($week as $w) {
    $weekMax = max($weekMax, (float)$w['total_sales'], (float)$w['total_expenses']);
}

// Menu with editable prices
$menuRows = $pdo->query('SELECT product_id, product_name, product_type, price, image FROM product ORDER BY product_type, price DESC, product_name')->fetchAll();
$menuGroups = [];
foreach ($menuRows as $m) {
    $menuGroups[$m['product_type']][] = $m;
}
$typeOrder = ['Silog', 'Rice Meal', 'Main', 'Snack', 'Add-on', 'Drink'];
uksort($menuGroups, static function (string $a, string $b) use ($typeOrder): int {
    $ia = array_search($a, $typeOrder, true);
    $ib = array_search($b, $typeOrder, true);
    return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a, $b);
});

$customerNames = $pdo->query('SELECT customer_name FROM customer ORDER BY customer_name LIMIT 500')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <div class="head-actions">
        <a class="btn btn-primary" href="<?= e(app_url('public/order_form.php')) ?>">New order &amp; sale</a>
        <a class="btn" href="<?= e(app_url('public/orders.php')) ?>">Orders</a>
        <a class="btn" href="<?= e(app_url('public/sales.php')) ?>">Sales</a>
        <a class="btn" href="<?= e(app_url('public/stock_check_form.php')) ?>">New stock check</a>
    </div>
</div>

<section class="stat-row" aria-label="Today at a glance">
    <div class="stat stat-main">
        <span class="stat-value"><?= e(money($salesToday)) ?></span>
        <span class="stat-label">Paid sales today</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= e(money($expensesToday)) ?></span>
        <span class="stat-label">Expenses today</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $ordersToday ?></span>
        <span class="stat-label">Orders today</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $pendingOrders ?></span>
        <span class="stat-label">Pending orders</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $unpaidSales ?></span>
        <span class="stat-label">Sales not fully paid</span>
    </div>
</section>

<section id="orders" class="dash-orders">
    <div class="page-head">
        <h2>Orders &amp; sales</h2>
        <div class="head-actions">
            <a class="btn btn-small btn-primary" href="<?= e(app_url('public/order_form.php')) ?>">New order &amp; sale</a>
            <a class="btn btn-small" href="<?= e(app_url('public/orders.php')) ?>">All orders</a>
            <a class="btn btn-small" href="<?= e(app_url('public/sales.php')) ?>">All sales</a>
        </div>
    </div>
    <nav class="dash-tabs" aria-label="Which orders to show">
        <?php foreach (['attention' => 'Needs attention', 'today' => 'Today', 'all' => 'Latest 40'] as $key => $label): ?>
            <a href="<?= e(app_url('public/dashboard.php?show=' . $key . '#orders')) ?>"<?= $show === $key ? ' class="is-active" aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <p class="table-meta">Change an order's status or payment in the lists and it saves right away.
        <?= $show === 'attention' ? "Showing today's orders plus any earlier order still pending or not fully paid." : '' ?></p>
    <?php if (!$orderRows): ?>
        <p class="empty"><?= $show === 'attention' ? 'Nothing needs attention.' : 'No orders to show.' ?>
            <a href="<?= e(app_url('public/order_form.php')) ?>">Take an order.</a></p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Order</th><th scope="col">Customer</th><th scope="col" class="num">Amount</th>
                    <th scope="col">Order status</th><th scope="col">Payment</th><th scope="col" class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orderRows as $o):
                $oid = (int)$o['order_id'];
                $cancelled = $o['order_status'] === 'Cancelled';
                $payValue = $o['payment_status'] ?? 'Unpaid';
            ?>
                <tr>
                    <td><a href="<?= e(app_url('public/order_view.php?id=' . $oid)) ?>">#<?= $oid ?></a>
                        <span class="muted"><?= e($o['order_date']) ?> <?= e(substr($o['order_time'], 0, 5)) ?></span></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td class="num"><?= $cancelled ? '<span class="muted">&mdash;</span>' : e(money($o['sale_id'] ? $o['amount'] : $o['total'])) ?></td>
                    <td>
                        <label class="sr-only" for="os_<?= $oid ?>">Status of order #<?= $oid ?></label>
                        <select id="os_<?= $oid ?>" name="order_status" form="oq_<?= $oid ?>" class="status-select" data-v="<?= e($o['order_status']) ?>" data-quick-status>
                            <?php foreach (ORDER_STATUSES as $st): ?>
                                <option value="<?= e($st) ?>"<?= $st === $o['order_status'] ? ' selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <label class="sr-only" for="op_<?= $oid ?>">Payment of order #<?= $oid ?></label>
                        <select id="op_<?= $oid ?>" name="payment_status" form="oq_<?= $oid ?>" class="status-select" data-v="<?= $cancelled ? '' : e($payValue) ?>" data-quick-pay<?= $cancelled ? ' disabled' : '' ?>>
                            <?php if ($cancelled): ?><option value="" selected>No sale</option><?php endif; ?>
                            <?php foreach (PAYMENT_STATUSES as $ps): ?>
                                <option value="<?= e($ps) ?>"<?= (!$cancelled && $ps === $payValue) ? ' selected' : '' ?>><?= e($ps) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="actions">
                        <form id="oq_<?= $oid ?>" method="post" action="<?= e(app_url('actions/order_quick.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $oid ?>">
                            <input type="hidden" name="show" value="<?= e($show) ?>">
                            <noscript><button class="btn btn-small btn-primary" type="submit">Save</button></noscript>
                        </form>
                        <a class="btn btn-small" href="<?= e(app_url('public/order_view.php?id=' . $oid)) ?>">View</a>
                        <a class="btn btn-small" href="<?= e(app_url('public/order_form.php?id=' . $oid)) ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section id="menu" class="dash-menu">
    <div class="page-head">
        <h2>Menu &amp; new order</h2>
        <a class="btn btn-small" href="<?= e(app_url('public/manage.php?e=product')) ?>">Manage products &amp; photos</a>
    </div>
    <p class="table-meta">Tap <strong>Add</strong> on a dish to put it in the current order. To change a price, type the new price and press Save; it applies right away to the public menu and to new orders.</p>
    <?php if (!$menuGroups): ?>
        <p class="empty">No products yet. <a href="<?= e(app_url('public/manage_form.php?e=product')) ?>">Add the first product.</a></p>
    <?php endif; ?>
    <div class="pos-layout">
    <div class="pos-menu">
    <?php foreach ($menuGroups as $type => $items): ?>
        <h3 class="menu-type"><?= e($type) ?></h3>
        <ul class="menu-grid">
            <?php foreach ($items as $item): ?>
                <li class="menu-card" data-pid="<?= (int)$item['product_id'] ?>" data-name="<?= e($item['product_name']) ?>" data-price="<?= e(number_format((float)$item['price'], 2, '.', '')) ?>">
                    <img src="<?= e(product_image_url($item['image'])) ?>" alt="<?= e($item['product_name']) ?>" loading="lazy" width="300" height="300">
                    <div class="menu-card-body">
                        <span class="menu-name"><?= e($item['product_name']) ?></span>
                        <button class="btn btn-small btn-primary add-btn" type="button" data-add aria-label="Add <?= e($item['product_name']) ?> to the order">+ Add <span class="add-count" data-add-count hidden></span></button>
                        <form class="price-form" method="post" action="<?= e(app_url('actions/product_price.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$item['product_id'] ?>">
                            <label class="sr-only" for="price_<?= (int)$item['product_id'] ?>">Price for <?= e($item['product_name']) ?></label>
                            <span class="price-input"><span aria-hidden="true">&#8369;</span>
                                <input id="price_<?= (int)$item['product_id'] ?>" name="price" type="number" inputmode="decimal" min="0" max="99999999.99" step="0.01" required value="<?= e(number_format((float)$item['price'], 2, '.', '')) ?>">
                            </span>
                            <button class="btn btn-small btn-primary" type="submit">Save</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
    </div>

    <aside class="order-panel" id="current-order" aria-label="Current order">
        <h3>Current order</h3>
        <form method="post" action="<?= e(app_url('actions/order_save.php')) ?>" data-pos-form novalidate>
            <?= csrf_field() ?>
            <div data-cart-inputs></div>
            <ul class="cart-lines" data-cart-lines></ul>
            <p class="empty" data-cart-empty>Tap <strong>+ Add</strong> on a dish to start an order.</p>
            <p class="cart-total">Total <strong data-cart-total>&#8369;0.00</strong></p>
            <div class="field">
                <label for="pos_name">Customer name</label>
                <input id="pos_name" name="customer_name" type="text" maxlength="100" required list="pos-customers" autocomplete="off" placeholder="Type the customer's name">
                <datalist id="pos-customers">
                    <?php foreach ($customerNames as $cn): ?><option value="<?= e($cn) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="pos_payment">Payment</label>
                    <select id="pos_payment" name="payment_status">
                        <?php foreach (PAYMENT_STATUSES as $ps): ?><option value="<?= e($ps) ?>"<?= $ps === 'Paid' ? ' selected' : '' ?>><?= e($ps) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="pos_status">Order status</label>
                    <select id="pos_status" name="order_status">
                        <?php foreach (['Completed', 'Pending'] as $st): ?><option value="<?= e($st) ?>"><?= e($st) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="cart-error" role="alert" data-cart-error hidden></p>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit" data-cart-place>Place order</button>
                <button class="btn btn-ghost" type="button" data-cart-clear>Clear</button>
            </div>
        </form>
    </aside>
    </div>
    <a class="cart-bar" href="#current-order" data-cart-bar hidden>View current order <strong data-cart-bar-text></strong></a>
</section>

<section>
        <h2>Running low</h2>
        <?php if (!$latestCheck): ?>
            <p class="empty">No stock check recorded yet. <a href="<?= e(app_url('public/stock_check_form.php')) ?>">Do a stock check.</a></p>
        <?php elseif (!$lowStock): ?>
            <p class="empty">Everything was at or above its minimum level on <?= e($latestCheck['check_date']) ?>.</p>
        <?php else: ?>
            <p class="table-meta">From the stock check on <?= e($latestCheck['check_date']) ?></p>
            <ul class="low-list">
                <?php foreach ($lowStock as $s): ?>
                    <li><span><?= e($s['item_name']) ?></span>
                        <span><strong><?= (int)$s['quantity_left'] ?></strong> left, minimum <?= (int)$s['minimum_level'] ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<section>
    <h2>Last <?= count($week) ?> closings</h2>
    <?php if (!$week): ?>
        <p class="empty">No daily closings yet.</p>
    <?php else: ?>
        <ul class="bars" aria-label="Sales and expenses by day">
            <?php foreach ($week as $w):
                $sp = (int)round((float)$w['total_sales'] / $weekMax * 100);
                $ep = (int)round((float)$w['total_expenses'] / $weekMax * 100);
            ?>
            <li>
                <span class="bar-date"><?= e($w['closing_date']) ?></span>
                <span class="bar-tracks">
                    <span class="bar bar-sales" style="width: <?= $sp ?>%"></span>
                    <span class="bar bar-expenses" style="width: <?= $ep ?>%"></span>
                </span>
                <span class="bar-nums">Sales <?= e(money($w['total_sales'])) ?> / Expenses <?= e(money($w['total_expenses'])) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
