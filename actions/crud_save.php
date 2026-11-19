<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crud.php';

require_login();
$ent = entity_or_redirect(post_str('entity'));
$listUrl = 'public/manage.php?e=' . $ent['key'];
require_post_csrf($listUrl);

$pdo = db();
$id = post_id('id');
$formUrl = 'public/manage_form.php?e=' . $ent['key'] . ($id ? '&id=' . $id : '');

if ($id !== null && crud_find($pdo, $ent, $id) === null) {
    flash('error', $ent['label'] . ' not found.');
    redirect($listUrl);
}

[$clean, $errors] = crud_validate($pdo, $ent, $_POST, $id);
if ($errors) {
    foreach ($errors as $msg) {
        flash('error', $msg);
    }
    set_old($_POST);
    redirect($formUrl);
}

try {
    crud_save($pdo, $ent, $clean, $id);
} catch (PDOException $ex) {
    error_log('crud_save failed: ' . $ex->getMessage());
    flash('error', 'Could not save the record. Please try again.');
    set_old($_POST);
    redirect($formUrl);
}

flash('success', $ent['label'] . ($id ? ' updated.' : ' added.'));
redirect($listUrl);
