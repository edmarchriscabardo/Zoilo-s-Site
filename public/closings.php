<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
$pdo = db();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$total = (int)$pdo->query('SELECT COUNT(*) FROM daily_closing')->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    'SELECT closing_id, closing_date, total_sales, total_expenses, (total_sales - total_expenses) AS net
       FROM daily_closing ORDER BY closing_date DESC LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$closings = $stmt->fetchAll();

$pageTitle = 'Daily closings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Daily closings</h1>
</div>

<section class="form-card">
    <h2>Open a closing day</h2>
    <form class="inline-form" method="post" action="<?= e(app_url('actions/closing_save.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <label for="closing_date">Date</label>
        <input id="closing_date" name="closing_date" type="date" required value="<?= e(has_old() ? old('closing_date') : date('Y-m-d')) ?>">
        <button class="btn btn-primary" type="submit">Create closing</button>
    </form>
    <p class="form-note">Recording a sale creates its closing day automatically, so you only need this to log expenses on a day with no sales.</p>
</section>

<?php if (!$closings): ?>
    <p class="empty">No daily closings yet.</p>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th scope="col">Date</th><th scope="col" class="num">Paid sales</th><th scope="col" class="num">Expenses</th><th scope="col" class="num">Net</th><th scope="col" class="actions-col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($closings as $c): ?>
            <tr>
                <td><a href="<?= e(app_url('public/closing_view.php?id=' . (int)$c['closing_id'])) ?>"><?= e($c['closing_date']) ?></a></td>
                <td class="num"><?= e(money($c['total_sales'])) ?></td>
                <td class="num"><?= e(money($c['total_expenses'])) ?></td>
                <td class="num <?= (float)$c['net'] < 0 ? 'neg' : 'pos' ?>"><?= e(money($c['net'])) ?></td>
                <td class="actions">
                    <a class="btn btn-small" href="<?= e(app_url('public/closing_view.php?id=' . (int)$c['closing_id'])) ?>">Open</a>
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?= e(app_url('actions/closing_delete.php')) ?>" data-confirm="Delete the closing for <?= e($c['closing_date']) ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="closing_id" value="<?= (int)$c['closing_id'] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="table-meta"><?= $total ?> closing(s)</p>
<?= render_pagination($page, $pages) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
