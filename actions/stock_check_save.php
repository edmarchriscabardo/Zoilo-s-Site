<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
require_post_csrf('public/stock_checks.php');

$pdo = db();
$id = post_id('check_id');
$back = 'public/stock_check_form.php' . ($id ? '?id=' . $id : '');
$errors = [];

if ($id !== null && !row_exists($pdo, 'stock_check', 'check_id', $id)) {
    flash('error', 'Stock check not found.');
    redirect('public/stock_checks.php');
}

$date = post_str('check_date');
if (!valid_date($date)) {
    $errors[] = 'Enter a valid check date.';
}

$validIds = array_map('intval', $pdo->query('SELECT stock_item_id FROM stock_item')->fetchAll(PDO::FETCH_COLUMN));
$counts = [];
$rawQty = $_POST['qty'] ?? [];
if (!is_array($rawQty)) {
    $rawQty = [];
}
foreach ($rawQty as $itemId => $qty) {
    if (!is_string($qty) || !ctype_digit((string)$itemId) || !in_array((int)$itemId, $validIds, true)) {
        continue;
    }
    $qty = trim($qty);
    if ($qty === '') {
        continue;
    }
    if (!ctype_digit($qty) || (int)$qty > 1000000) {
        $errors['qty'] = 'Quantities must be whole numbers from 0 to 1,000,000.';
        continue;
    }
    $counts[(int)$itemId] = (int)$qty;
}
if (!$counts && !isset($errors['qty'])) {
    $errors['none'] = 'Enter the quantity left for at least one item.';
}

if ($errors) {
    foreach ($errors as $msg) {
        flash('error', $msg);
    }
    set_old($_POST);
    redirect($back);
}

try {
    $pdo->beginTransaction();
    if ($id !== null) {
        $pdo->prepare('UPDATE stock_check SET check_date = :d WHERE check_id = :id')->execute(['d' => $date, 'id' => $id]);
        $pdo->prepare('DELETE FROM stock_check_detail WHERE check_id = :id')->execute(['id' => $id]);
    } else {
        $pdo->prepare('INSERT INTO stock_check (check_date) VALUES (:d)')->execute(['d' => $date]);
        $id = (int)$pdo->lastInsertId();
    }
    $ins = $pdo->prepare('INSERT INTO stock_check_detail (check_id, stock_item_id, quantity_left) VALUES (:c, :s, :q)');
    foreach ($counts as $itemId => $qty) {
        $ins->execute(['c' => $id, 's' => $itemId, 'q' => $qty]);
    }
    $pdo->commit();
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('stock_check_save failed: ' . $ex->getMessage());
    flash('error', 'Could not save the stock check. Please try again.');
    set_old($_POST);
    redirect($back);
}

flash('success', 'Stock check #' . $id . ' saved.');
redirect('public/stock_check_view.php?id=' . $id);
