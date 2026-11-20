<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();
require_post_csrf('public/closings.php');

$id = post_id('expense_id');
if ($id === null) {
    flash('error', 'Invalid expense.');
    redirect('public/closings.php');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT closing_id FROM expense WHERE expense_id = :id');
$stmt->execute(['id' => $id]);
$closingId = $stmt->fetchColumn();
if ($closingId === false) {
    flash('error', 'Expense not found.');
    redirect('public/closings.php');
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM expense WHERE expense_id = :id')->execute(['id' => $id]);
    recalc_closing($pdo, (int)$closingId);
    $pdo->commit();
    flash('success', 'Expense deleted.');
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('expense_delete failed: ' . $ex->getMessage());
    flash('error', 'Could not delete the expense. Please try again.');
}
redirect('public/closing_view.php?id=' . (int)$closingId);
