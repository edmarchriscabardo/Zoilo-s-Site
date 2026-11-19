<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
require_post_csrf('public/orders.php');

$pdo = db();
$id = post_id('order_id');
$back = 'public/order_form.php' . ($id ? '?id=' . $id : '');
$errors = [];

if ($id !== null && !row_exists($pdo, 'customer_order', 'order_id', $id)) {
    flash('error', 'Order not found.');
    redirect('public/orders.php');
}

$customerId = post_id('customer_id');
if ($customerId === null || !row_exists($pdo, 'customer', 'customer_id', $customerId)) {
    $errors['customer'] = 'Choose a customer.';
}

$date = post_str('order_date');
if (!valid_date($date)) {
    $errors['date'] = 'Enter a valid order date.';
}

$time = normalize_time(post_str('order_time'));
if ($time === null) {
    $errors['time'] = 'Enter a valid order time.';
}

$status = post_str('order_status');
if (!in_array($status, ORDER_STATUSES, true)) {
    $errors['status'] = 'Choose a valid status.';
}

// Items: merge duplicate products, require whole-number quantities of 1 to 1000.
$items = [];
$rawItems = $_POST['items'] ?? [];
if (!is_array($rawItems)) {
    $rawItems = [];
}
foreach ($rawItems as $row) {
    if (!is_array($row)) {
        continue;
    }
    $pid = is_string($row['product_id'] ?? null) ? trim($row['product_id']) : '';
    $qty = is_string($row['quantity'] ?? null) ? trim($row['quantity']) : '';
    if ($pid === '' && $qty === '') {
        continue;
    }
    if (!ctype_digit($pid) || (int)$pid < 1 || !ctype_digit($qty) || (int)$qty < 1 || (int)$qty > 1000) {
        $errors['items'] = 'Each item needs a product and a whole-number quantity from 1 to 1000.';
        continue;
    }
    $items[(int)$pid] = ($items[(int)$pid] ?? 0) + (int)$qty;
}
if (!$items && !isset($errors['items'])) {
    $errors['items'] = 'Add at least one item.';
}
if ($items) {
    $marks = implode(',', array_fill(0, count($items), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE product_id IN ({$marks})");
    $stmt->execute(array_keys($items));
    if ((int)$stmt->fetchColumn() !== count($items)) {
        $errors['items'] = 'One or more selected products no longer exist.';
    }
    foreach ($items as $q) {
        if ($q > 1000) {
            $errors['items'] = 'Combined quantity for a product cannot exceed 1000.';
        }
    }
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
        $stmt = $pdo->prepare(
            'UPDATE customer_order SET order_date = :d, order_time = :t, order_status = :s, customer_id = :c WHERE order_id = :id'
        );
        $stmt->execute(['d' => $date, 't' => $time, 's' => $status, 'c' => $customerId, 'id' => $id]);
        $pdo->prepare('DELETE FROM order_item WHERE order_id = :id')->execute(['id' => $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO customer_order (order_date, order_time, order_status, customer_id) VALUES (:d, :t, :s, :c)'
        );
        $stmt->execute(['d' => $date, 't' => $time, 's' => $status, 'c' => $customerId]);
        $id = (int)$pdo->lastInsertId();
    }
    $ins = $pdo->prepare('INSERT INTO order_item (order_id, product_id, quantity) VALUES (:o, :p, :q)');
    foreach ($items as $pid => $qty) {
        $ins->execute(['o' => $id, 'p' => $pid, 'q' => $qty]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('order_save failed: ' . $ex->getMessage());
    flash('error', 'Could not save the order. Please try again.');
    set_old($_POST);
    redirect($back);
}

flash('success', 'Order #' . $id . ' saved.');
redirect('public/order_view.php?id=' . $id);
