<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
require_post_csrf('public/orders.php');

$id = post_id('order_id');
$status = post_str('order_status');
if ($id === null || !in_array($status, ORDER_STATUSES, true)) {
    flash('error', 'Invalid order or status.');
    redirect('public/orders.php');
}

$stmt = db()->prepare('UPDATE customer_order SET order_status = :s WHERE order_id = :id');
$stmt->execute(['s' => $status, 'id' => $id]);

flash('success', 'Order #' . $id . ' is now ' . strtolower($status) . '.');
redirect('public/order_view.php?id=' . $id);
