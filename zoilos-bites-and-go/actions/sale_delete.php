<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();
require_post_csrf('public/sales.php');

$id = post_id('sale_id');
if ($id === null) {
    flash('error', 'Invalid sale.');
    redirect('public/sales.php');
}

$pdo = db();
try {
    $stmt = $pdo->prepare('SELECT closing_id FROM sale WHERE sale_id = :id');
    $stmt->execute(['id' => $id]);
    $closingId = $stmt->fetchColumn();
    if ($closingId === false) {
        flash('error', 'Sale not found.');
        redirect('public/sales.php');
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM sale WHERE sale_id = :id')->execute(['id' => $id]);
    recalc_closing($pdo, (int)$closingId);
    $pdo->commit();
    flash('success', 'Sale #' . $id . ' deleted.');
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('sale_delete failed: ' . $ex->getMessage());
    flash('error', 'Could not delete the sale. Please try again.');
}
redirect('public/sales.php');
