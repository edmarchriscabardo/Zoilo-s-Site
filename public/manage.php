<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crud.php';

require_login();
$ent = entity_or_redirect((string)($_GET['e'] ?? ''));

$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 100);
$result = crud_list(db(), $ent, $q, max(1, (int)($_GET['page'] ?? 1)));

$pageTitle = $ent['plural'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($ent['plural']) ?></h1>
    <a class="btn btn-primary" href="<?= e(app_url('public/manage_form.php?e=' . $ent['key'])) ?>">Add <?= e(strtolower($ent['label'])) ?></a>
</div>

<form class="search-bar" method="get" action="<?= e(app_url('public/manage.php')) ?>">
    <input type="hidden" name="e" value="<?= e($ent['key']) ?>">
    <label class="sr-only" for="q">Search <?= e(strtolower($ent['plural'])) ?></label>
    <input id="q" name="q" type="search" maxlength="100" placeholder="Search <?= e(strtolower($ent['plural'])) ?>" value="<?= e($q) ?>">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= e(app_url('public/manage.php?e=' . $ent['key'])) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$result['rows']): ?>
    <p class="empty"><?= $q !== '' ? 'No ' . e(strtolower($ent['plural'])) . ' match your search.' : 'No ' . e(strtolower($ent['plural'])) . ' yet. Add the first one.' ?></p>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <?php foreach ($ent['list'] as $name): ?>
                    <th scope="col"<?= $ent['fields'][$name]['type'] === 'money' ? ' class="num"' : '' ?>><?= e($ent['fields'][$name]['label']) ?></th>
                <?php endforeach; ?>
                <th scope="col" class="actions-col">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $row): ?>
            <tr>
                <?php foreach ($ent['list'] as $name): ?>
                    <td<?= $ent['fields'][$name]['type'] === 'money' ? ' class="num"' : '' ?>><?= crud_cell($ent, $row, $name) ?></td>
                <?php endforeach; ?>
                <td class="actions">
                    <a class="btn btn-small" href="<?= e(app_url('public/manage_form.php?e=' . $ent['key'] . '&id=' . (int)$row[$ent['pk']])) ?>">Edit</a>
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?= e(app_url('actions/crud_delete.php')) ?>" data-confirm="Delete this <?= e(strtolower($ent['label'])) ?>? This cannot be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entity" value="<?= e($ent['key']) ?>">
                        <input type="hidden" name="id" value="<?= (int)$row[$ent['pk']] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="table-meta"><?= (int)$result['total'] ?> record(s)</p>
<?= render_pagination($result['page'], $result['pages'], ['e' => $ent['key'], 'q' => $q]) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
