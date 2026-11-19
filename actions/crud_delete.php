<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crud.php';

require_admin();
$ent = entity_or_redirect(post_str('entity'));
$listUrl = 'public/manage.php?e=' . $ent['key'];
require_post_csrf($listUrl);

$id = post_id('id');
if ($id === null) {
    flash('error', 'Invalid record.');
    redirect($listUrl);
}

try {
    $stmt = db()->prepare("DELETE FROM `{$ent['table']}` WHERE `{$ent['pk']}` = :id");
    $stmt->execute(['id' => $id]);
    flash($stmt->rowCount() > 0 ? 'success' : 'error',
          $stmt->rowCount() > 0 ? $ent['label'] . ' deleted.' : $ent['label'] . ' not found.');
} catch (PDOException $ex) {
    if (is_constraint_error($ex)) {
        flash('error', 'This ' . strtolower($ent['label']) . ' is still used by other records, so it cannot be deleted.');
    } else {
        error_log('crud_delete failed: ' . $ex->getMessage());
        flash('error', 'Could not delete the record. Please try again.');
    }
}
redirect($listUrl);
