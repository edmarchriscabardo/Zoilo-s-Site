<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();
require_post_csrf('public/orders.php');

$id = post_id('order_id');
if ($id === null) {
    flash('error', 'Invalid order.');
    redirect('public/orders.php');
}

$pdo = db();
try {
    $pdo->beginTransaction();
    // The sale is part of the order now: remove it too and fix that day's closing totals.
    $stmt = $pdo->prepare('SELECT closing_id FROM sale WHERE order_id = :id');
    $stmt->execute(['id' => $id]);
    $closingId = $stmt->fetchColumn();
    $pdo->prepare('DELETE FROM sale WHERE order_id = :id')->execute(['id' => $id]);

    $stmt = $pdo->prepare('DELETE FROM customer_order WHERE order_id = :id');
    $stmt->execute(['id' => $id]);
    $deleted = $stmt->rowCount() > 0;

    if ($closingId !== false) {
        recalc_closing($pdo, (int)$closingId);
    }
    $pdo->commit();
    flash($deleted ? 'success' : 'error', $deleted ? 'Order #' . $id . ' and its sale were deleted.' : 'Order not found.');
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('order_delete failed: ' . $ex->getMessage());
    flash('error', 'Could not delete the order. Please try again.');
}
redirect('public/orders.php');
