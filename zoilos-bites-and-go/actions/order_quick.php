<?php
declare(strict_types=1);

/**
 * Quick update from the dashboard: change an order's status and/or its sale's payment status.
 * - Cancelled: the order has no sale (an existing one is removed from the day's totals).
 * - Any other status: the order must have a sale; one is created at the order total if missing.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$show = post_str('show');
$show = in_array($show, ['attention', 'today', 'all'], true) ? $show : 'attention';
$back = 'public/dashboard.php?show=' . $show . '#orders';
require_post_csrf($back);

$id = post_id('order_id');
$status = post_str('order_status');
$payment = post_str('payment_status');   // '' = leave the payment as it is

if ($id === null || !in_array($status, ORDER_STATUSES, true) || ($payment !== '' && !in_array($payment, PAYMENT_STATUSES, true))) {
    flash('error', 'Invalid order, status or payment.');
    redirect($back);
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT order_date FROM customer_order WHERE order_id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $orderDate = $stmt->fetchColumn();
    if ($orderDate === false) {
        $pdo->rollBack();
        flash('error', 'Order not found.');
        redirect($back);
    }

    $pdo->prepare('UPDATE customer_order SET order_status = :s WHERE order_id = :id')->execute(['s' => $status, 'id' => $id]);

    $stmt = $pdo->prepare('SELECT sale_id, closing_id FROM sale WHERE order_id = :id');
    $stmt->execute(['id' => $id]);
    $sale = $stmt->fetch() ?: null;
    $recalc = [];

    if ($status === 'Cancelled') {
        if ($sale !== null) {
            $pdo->prepare('DELETE FROM sale WHERE sale_id = :s')->execute(['s' => $sale['sale_id']]);
            $recalc[] = (int)$sale['closing_id'];
        }
    } elseif ($sale !== null) {
        if ($payment !== '') {
            $pdo->prepare('UPDATE sale SET payment_status = :p WHERE sale_id = :s')->execute(['p' => $payment, 's' => $sale['sale_id']]);
        }
        $recalc[] = (int)$sale['closing_id'];
    } else {
        // No sale yet (older order, or just un-cancelled): create it at the current order total.
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(oi.quantity * p.price), 0) FROM order_item oi JOIN product p ON p.product_id = oi.product_id WHERE oi.order_id = :id'
        );
        $stmt->execute(['id' => $id]);
        $amount = number_format((float)$stmt->fetchColumn(), 2, '.', '');
        $closingId = get_or_create_closing($pdo, (string)$orderDate);
        $pdo->prepare('INSERT INTO sale (amount, payment_status, order_id, closing_id) VALUES (:a, :p, :o, :c)')
            ->execute(['a' => $amount, 'p' => $payment !== '' ? $payment : 'Unpaid', 'o' => $id, 'c' => $closingId]);
        $recalc[] = $closingId;
    }
    foreach (array_unique($recalc) as $cid) {
        recalc_closing($pdo, $cid);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('order_quick failed: ' . $ex->getMessage());
    flash('error', 'Could not update the order. Please try again.');
    redirect($back);
}

flash('success', 'Order #' . $id . ' is now ' . strtolower($status)
    . ($status === 'Cancelled' ? ' (sale removed).' : ($payment !== '' ? ', payment: ' . strtolower($payment) . '.' : '.')));
redirect($back);
