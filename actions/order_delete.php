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

try {
    $stmt = db()->prepare('DELETE FROM customer_order WHERE order_id = :id');
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() > 0) {
        flash('success', 'Order #' . $id . ' deleted.');
    } else {
        flash('error', 'Order not found.');
    }
} catch (PDOException $ex) {
    if (is_constraint_error($ex)) {
        flash('error', 'Order #' . $id . ' has a recorded sale. Delete the sale first, or mark the order as cancelled.');
    } else {
        error_log('order_delete failed: ' . $ex->getMessage());
        flash('error', 'Could not delete the order. Please try again.');
    }
}
redirect('public/orders.php');
