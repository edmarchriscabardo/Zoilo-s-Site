<?php
declare(strict_types=1);

/** Change one product's price from the dashboard menu. */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();
$back = 'public/dashboard.php#menu';
require_post_csrf($back);

$id = post_id('id');
$price = normalize_money(post_str('price'));

if ($id === null || !row_exists(db(), 'product', 'product_id', $id)) {
    flash('error', 'Product not found.');
    redirect($back);
}
if ($price === null) {
    flash('error', 'Price must be an amount like 65 or 65.50 (up to 2 decimal places).');
    redirect($back);
}

try {
    $stmt = db()->prepare('UPDATE product SET price = :p WHERE product_id = :id');
    $stmt->execute(['p' => $price, 'id' => $id]);
} catch (PDOException $ex) {
    error_log('product_price failed: ' . $ex->getMessage());
    flash('error', 'Could not update the price. Please try again.');
    redirect($back);
}

$name = db()->prepare('SELECT product_name FROM product WHERE product_id = :id');
$name->execute(['id' => $id]);
flash('success', ($name->fetchColumn() ?: 'Product') . ' is now ' . money($price) . '.');
redirect($back);
