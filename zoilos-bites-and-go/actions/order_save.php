<?php
declare(strict_types=1);

/**
 * One save for the whole sale: customer (by name), order, items and the sale/payment.
 * - A customer name that already exists is reused; a new name creates the customer.
 * - Every order that is not Cancelled gets exactly one sale, closed on the order's date.
 * - A Cancelled order has no sale (an existing one is removed).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
// Orders placed from the dashboard panel come back there (with the cart intact) if something is wrong.
$fromDash = post_str('from') === 'dashboard';
require_post_csrf($fromDash ? 'public/dashboard.php#current-order' : 'public/orders.php');

$pdo = db();
$id = post_id('order_id');
$back = $fromDash ? 'public/dashboard.php#current-order' : 'public/order_form.php' . ($id ? '?id=' . $id : '');
$errors = [];

if ($id !== null && !row_exists($pdo, 'customer_order', 'order_id', $id)) {
    flash('error', 'Order not found.');
    redirect('public/orders.php');
}

// Customer name: tidy spaces, 1-100 characters.
$customerName = trim((string)preg_replace('/\s+/u', ' ', post_str('customer_name')));
if ($customerName === '' || mb_strlen($customerName) > 100) {
    $errors['customer'] = 'Enter the customer\'s name (up to 100 characters).';
}

// The dashboard order panel sends no date/time: use the moment the order is placed.
$date = post_str('order_date') !== '' ? post_str('order_date') : date('Y-m-d');
if (!valid_date($date)) {
    $errors['date'] = 'Enter a valid order date.';
}

$time = normalize_time(post_str('order_time') !== '' ? post_str('order_time') : date('H:i'));
if ($time === null) {
    $errors['time'] = 'Enter a valid order time.';
}

$status = post_str('order_status');
if (!in_array($status, ORDER_STATUSES, true)) {
    $errors['status'] = 'Choose a valid status.';
}

$payment = post_str('payment_status');
if (!in_array($payment, PAYMENT_STATUSES, true)) {
    $errors['payment'] = 'Choose a valid payment status.';
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

// Order total at current prices (also checks the products still exist).
$total = 0.0;
if ($items) {
    $marks = implode(',', array_fill(0, count($items), '?'));
    $stmt = $pdo->prepare("SELECT product_id, price FROM product WHERE product_id IN ({$marks})");
    $stmt->execute(array_keys($items));
    $prices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (count($prices) !== count($items)) {
        $errors['items'] = 'One or more selected products no longer exist.';
    }
    foreach ($items as $pid => $q) {
        if ($q > 1000) {
            $errors['items'] = 'Combined quantity for a product cannot exceed 1000.';
        }
        $total += (float)($prices[$pid] ?? 0) * $q;
    }
}

// Sale amount: blank means "use the order total"; otherwise the typed amount (e.g. after a discount).
$rawAmount = post_str('amount');
$amount = number_format(round($total, 2), 2, '.', '');
if ($rawAmount !== '') {
    $typed = normalize_money($rawAmount);
    if ($typed === null) {
        $errors['amount'] = 'Amount must be a number with up to 2 decimal places.';
    } else {
        $amount = $typed;
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

    // Find the customer by name (case-insensitive), or create one.
    $stmt = $pdo->prepare('SELECT customer_id FROM customer WHERE customer_name = :n ORDER BY customer_id LIMIT 1');
    $stmt->execute(['n' => $customerName]);
    $customerId = $stmt->fetchColumn();
    if ($customerId === false) {
        $pdo->prepare('INSERT INTO customer (customer_name) VALUES (:n)')->execute(['n' => $customerName]);
        $customerId = (int)$pdo->lastInsertId();
    }
    $customerId = (int)$customerId;

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

    // The sale that belongs to this order.
    $stmt = $pdo->prepare('SELECT sale_id, closing_id FROM sale WHERE order_id = :o');
    $stmt->execute(['o' => $id]);
    $sale = $stmt->fetch() ?: null;
    $recalc = [];

    if ($status === 'Cancelled') {
        if ($sale !== null) {
            $pdo->prepare('DELETE FROM sale WHERE sale_id = :s')->execute(['s' => $sale['sale_id']]);
            $recalc[] = (int)$sale['closing_id'];
        }
    } else {
        $closingId = get_or_create_closing($pdo, $date);
        if ($sale !== null) {
            $pdo->prepare('UPDATE sale SET amount = :a, payment_status = :p, closing_id = :c WHERE sale_id = :s')
                ->execute(['a' => $amount, 'p' => $payment, 'c' => $closingId, 's' => $sale['sale_id']]);
            $recalc[] = (int)$sale['closing_id'];
        } else {
            $pdo->prepare('INSERT INTO sale (amount, payment_status, order_id, closing_id) VALUES (:a, :p, :o, :c)')
                ->execute(['a' => $amount, 'p' => $payment, 'o' => $id, 'c' => $closingId]);
        }
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
    error_log('order_save failed: ' . $ex->getMessage());
    flash('error', 'Could not save the order. Please try again.');
    set_old($_POST);
    redirect($back);
}

flash('success', $status === 'Cancelled'
    ? 'Order #' . $id . ' saved as cancelled (no sale recorded).'
    : 'Order #' . $id . ' and its sale saved (' . money($amount) . ', ' . strtolower($payment) . ').');
redirect('public/order_view.php?id=' . $id . ($fromDash ? '&placed=1' : ''));
