<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
require_post_csrf('public/closings.php');

$pdo = db();
$closingId = post_id('closing_id');
if ($closingId === null || !row_exists($pdo, 'daily_closing', 'closing_id', $closingId)) {
    flash('error', 'Closing not found.');
    redirect('public/closings.php');
}

$id = post_id('expense_id');
$back = 'public/closing_view.php?id=' . $closingId . ($id ? '&edit_expense=' . $id : '') . '#expense-form';

if ($id !== null) {
    $stmt = $pdo->prepare('SELECT 1 FROM expense WHERE expense_id = :id AND closing_id = :c');
    $stmt->execute(['id' => $id, 'c' => $closingId]);
    if (!$stmt->fetchColumn()) {
        flash('error', 'Expense not found.');
        redirect('public/closing_view.php?id=' . $closingId);
    }
}

$errors = [];
$description = post_str('expense_description');
if ($description === '' || mb_strlen($description) > 255) {
    $errors[] = 'Description is required and must be 255 characters or fewer.';
}
$amount = normalize_money(post_str('expense_amount'));
if ($amount === null) {
    $errors[] = 'Amount must be a positive number with up to 2 decimal places.';
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
        $stmt = $pdo->prepare('UPDATE expense SET expense_description = :d, expense_amount = :a WHERE expense_id = :id AND closing_id = :c');
        $stmt->execute(['d' => $description, 'a' => $amount, 'id' => $id, 'c' => $closingId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO expense (expense_description, expense_amount, closing_id) VALUES (:d, :a, :c)');
        $stmt->execute(['d' => $description, 'a' => $amount, 'c' => $closingId]);
    }
    recalc_closing($pdo, $closingId);
    $pdo->commit();
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('expense_save failed: ' . $ex->getMessage());
    flash('error', 'Could not save the expense. Please try again.');
    set_old($_POST);
    redirect($back);
}

flash('success', $id ? 'Expense updated.' : 'Expense added.');
redirect('public/closing_view.php?id=' . $closingId);
