<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$id = get_id('id');
if ($id === null) {
    redirect('public/closings.php');
}

$stmt = $pdo->prepare('SELECT * FROM daily_closing WHERE closing_id = :id');
$stmt->execute(['id' => $id]);
$closing = $stmt->fetch();
if (!$closing) {
    flash('error', 'Closing not found.');
    redirect('public/closings.php');
}

$stmt = $pdo->prepare(
    'SELECT s.sale_id, s.amount, s.payment_status, s.order_id, c.customer_name
       FROM sale s
       JOIN customer_order o ON o.order_id = s.order_id
       JOIN customer c ON c.customer_id = o.customer_id
      WHERE s.closing_id = :id ORDER BY s.sale_id'
);
$stmt->execute(['id' => $id]);
$sales = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT expense_id, expense_description, expense_amount FROM expense WHERE closing_id = :id ORDER BY expense_id');
$stmt->execute(['id' => $id]);
$expenses = $stmt->fetchAll();

// Expense being edited, if any (?edit_expense=ID)
$editing = null;
$editId = get_id('edit_expense');
foreach ($expenses as $x) {
    if ($editId !== null && (int)$x['expense_id'] === $editId) {
        $editing = $x;
    }
}
$exp = [
    'expense_description' => has_old() ? (string)old('expense_description') : (string)($editing['expense_description'] ?? ''),
    'expense_amount'      => has_old() ? (string)old('expense_amount') : (string)($editing['expense_amount'] ?? ''),
];

$net = (float)$closing['total_sales'] - (float)$closing['total_expenses'];

$pageTitle = 'Closing ' . $closing['closing_date'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Closing for <?= e($closing['closing_date']) ?></h1>
    <a class="btn btn-ghost" href="<?= e(app_url('public/closings.php')) ?>">All closings</a>
</div>

<section class="stat-row" aria-label="Closing totals">
    <div class="stat stat-main"><span class="stat-value"><?= e(money($closing['total_sales'])) ?></span><span class="stat-label">Paid sales</span></div>
    <div class="stat"><span class="stat-value"><?= e(money($closing['total_expenses'])) ?></span><span class="stat-label">Expenses</span></div>
    <div class="stat"><span class="stat-value <?= $net < 0 ? 'neg' : 'pos' ?>"><?= e(money($net)) ?></span><span class="stat-label">Net for the day</span></div>
</section>

<section class="form-card">
    <h2>Change closing date</h2>
    <form class="inline-form" method="post" action="<?= e(app_url('actions/closing_save.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="closing_id" value="<?= (int)$id ?>">
        <label for="closing_date">Date</label>
        <input id="closing_date" name="closing_date" type="date" required value="<?= e($closing['closing_date']) ?>">
        <button class="btn btn-small" type="submit">Save date</button>
    </form>
</section>

<div class="two-col">
    <section>
        <h2>Sales</h2>
        <?php if (!$sales): ?>
            <p class="empty">No sales on this day.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Payment</th><th scope="col" class="num">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><a href="<?= e(app_url('public/order_view.php?id=' . (int)$s['order_id'])) ?>">#<?= (int)$s['order_id'] ?></a></td>
                        <td><?= e($s['customer_name']) ?></td>
                        <td><?= badge($s['payment_status']) ?></td>
                        <td class="num"><?= e(money($s['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section>
        <h2>Expenses</h2>
        <?php if (!$expenses): ?>
            <p class="empty">No expenses logged for this day.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Description</th><th scope="col" class="num">Amount</th><th scope="col" class="actions-col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $x): ?>
                    <tr>
                        <td><?= e($x['expense_description']) ?></td>
                        <td class="num"><?= e(money($x['expense_amount'])) ?></td>
                        <td class="actions">
                            <a class="btn btn-small" href="<?= e(app_url('public/closing_view.php?id=' . $id . '&edit_expense=' . (int)$x['expense_id'])) ?>#expense-form">Edit</a>
                            <?php if (is_admin()): ?>
                            <form method="post" action="<?= e(app_url('actions/expense_delete.php')) ?>" data-confirm="Delete this expense?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="expense_id" value="<?= (int)$x['expense_id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <form id="expense-form" class="form-card" method="post" action="<?= e(app_url('actions/expense_save.php')) ?>" novalidate>
            <h3><?= $editing ? 'Edit expense' : 'Add expense' ?></h3>
            <?= csrf_field() ?>
            <input type="hidden" name="closing_id" value="<?= (int)$id ?>">
            <?php if ($editing): ?><input type="hidden" name="expense_id" value="<?= (int)$editing['expense_id'] ?>"><?php endif; ?>
            <div class="field">
                <label for="expense_description">Description</label>
                <input id="expense_description" name="expense_description" type="text" maxlength="255" required value="<?= e($exp['expense_description']) ?>">
            </div>
            <div class="field">
                <label for="expense_amount">Amount (PHP)</label>
                <input id="expense_amount" name="expense_amount" type="number" inputmode="decimal" min="0" step="0.01" max="99999999.99" required value="<?= e($exp['expense_amount']) ?>">
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $editing ? 'Save expense' : 'Add expense' ?></button>
                <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(app_url('public/closing_view.php?id=' . $id)) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
