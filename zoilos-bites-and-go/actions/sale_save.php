<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
require_post_csrf('public/sales.php');

$pdo = db();
$id = post_id('sale_id');
$back = 'public/sale_form.php' . ($id ? '?id=' . $id : '');
$errors = [];

$existing = null;
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT sale_id, order_id, closing_id FROM sale WHERE sale_id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch() ?: null;
    if ($existing === null) {
        flash('error', 'Sale not found.');
        redirect('public/sales.php');
    }
}

$orderId = $existing ? (int)$existing['order_id'] : post_id('order_id');
if ($existing === null) {
    if ($orderId === null) {
        $errors['order'] = 'Choose an order.';
    } else {
        $stmt = $pdo->prepare('SELECT order_status FROM customer_order WHERE order_id = :id');
        $stmt->execute(['id' => $orderId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            $errors['order'] = 'That order does not exist.';
        } elseif ($status === 'Cancelled') {
            $errors['order'] = 'A cancelled order cannot have a sale.';
        } elseif (row_exists($pdo, 'sale', 'order_id', $orderId)) {
            $errors['order'] = 'That order already has a sale.';
        }
    }
}

$amount = normalize_money(post_str('amount'));
if ($amount === null) {
    $errors['amount'] = 'Amount must be a positive number with up to 2 decimal places.';
}

$payment = post_str('payment_status');
if (!in_array($payment, PAYMENT_STATUSES, true)) {
    $errors['payment'] = 'Choose a valid payment status.';
}

$closingDate = post_str('closing_date');
if (!valid_date($closingDate)) {
    $errors['date'] = 'Enter a valid closing date.';
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
    $closingId = get_or_create_closing($pdo, $closingDate);

    if ($existing !== null) {
        $stmt = $pdo->prepare(
            'UPDATE sale SET amount = :a, payment_status = :p, closing_id = :c WHERE sale_id = :id'
        );
        $stmt->execute(['a' => $amount, 'p' => $payment, 'c' => $closingId, 'id' => $id]);
        if ((int)$existing['closing_id'] !== $closingId) {
            recalc_closing($pdo, (int)$existing['closing_id']);
        }
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO sale (amount, payment_status, order_id, closing_id) VALUES (:a, :p, :o, :c)'
        );
        $stmt->execute(['a' => $amount, 'p' => $payment, 'o' => $orderId, 'c' => $closingId]);
    }
    recalc_closing($pdo, $closingId);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($ex instanceof PDOException && is_constraint_error($ex)) {
        flash('error', 'That order already has a sale.');
    } else {
        error_log('sale_save failed: ' . $ex->getMessage());
        flash('error', 'Could not save the sale. Please try again.');
    }
    set_old($_POST);
    redirect($back);
}

flash('success', 'Sale saved.');
redirect('public/order_view.php?id=' . $orderId);
