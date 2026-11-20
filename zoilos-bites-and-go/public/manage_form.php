<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crud.php';

require_admin();
$ent = entity_or_redirect((string)($_GET['e'] ?? ''));
$pdo = db();

$id = get_id('id');
$row = null;
if ($id !== null) {
    $row = crud_find($pdo, $ent, $id);
    if ($row === null) {
        flash('error', $ent['label'] . ' not found.');
        redirect('public/manage.php?e=' . $ent['key']);
    }
}

$value = static function (string $name) use ($row): string {
    if (has_old()) {
        $v = old($name);
        return is_string($v) ? $v : '';
    }
    return (string)($row[$name] ?? '');
};

$pageTitle = ($row ? 'Edit ' : 'Add ') . strtolower($ent['label']);
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-ghost" href="<?= e(app_url('public/manage.php?e=' . $ent['key'])) ?>">Back to <?= e(strtolower($ent['plural'])) ?></a>
</div>

<section class="form-card">
    <form method="post" action="<?= e(app_url('actions/crud_save.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="<?= e($ent['key']) ?>">
        <?php if ($row): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

        <?php foreach ($ent['fields'] as $name => $f):
            $req = !empty($f['required']) ? ' required' : '';
            $current = $value($name);
        ?>
            <div class="field">
                <label for="f_<?= e($name) ?>"><?= e($f['label']) ?><?= !empty($f['required']) ? '' : ' (optional)' ?></label>
                <?php if ($f['type'] === 'select'): ?>
                    <select id="f_<?= e($name) ?>" name="<?= e($name) ?>"<?= $req ?>>
                        <option value="">Choose...</option>
                        <?php foreach (fk_options($pdo, $f['fk']) as $opt): ?>
                            <option value="<?= (int)$opt['id'] ?>"<?= (string)$opt['id'] === $current ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($f['type'] === 'photo'): ?>
                    <select id="f_<?= e($name) ?>" name="<?= e($name) ?>" data-photo-select>
                        <option value="">No photo</option>
                        <?php foreach (product_photos() as $file): ?>
                            <option value="<?= e($file) ?>" data-src="<?= e(product_image_url($file)) ?>"<?= $file === $current ? ' selected' : '' ?>><?= e($file) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <img class="photo-preview" id="photo_preview" data-placeholder="<?= e(product_image_url(null)) ?>" src="<?= e(product_image_url($current !== '' ? $current : null)) ?>" alt="Photo preview" width="140" height="140">
                    <small>To add a new photo, put the image file in <code>public/assets/img/products/</code> and reload this page.</small>
                <?php elseif ($f['type'] === 'money'): ?>
                    <input id="f_<?= e($name) ?>" name="<?= e($name) ?>" type="number" inputmode="decimal" min="0" step="0.01" max="99999999.99"<?= $req ?> value="<?= e($current) ?>">
                <?php elseif ($f['type'] === 'int'): ?>
                    <input id="f_<?= e($name) ?>" name="<?= e($name) ?>" type="number" inputmode="numeric" min="0" step="1" max="<?= (int)($f['max'] ?? 1000000) ?>"<?= $req ?> value="<?= e($current) ?>">
                <?php else: ?>
                    <input id="f_<?= e($name) ?>" name="<?= e($name) ?>" type="<?= $f['type'] === 'email' ? 'email' : 'text' ?>" maxlength="<?= (int)$f['max'] ?>"<?= $req ?> value="<?= e($current) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save <?= e(strtolower($ent['label'])) ?></button>
            <a class="btn btn-ghost" href="<?= e(app_url('public/manage.php?e=' . $ent['key'])) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
