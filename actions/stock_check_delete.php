<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();
require_post_csrf('public/stock_checks.php');

$id = post_id('check_id');
if ($id === null) {
    flash('error', 'Invalid stock check.');
    redirect('public/stock_checks.php');
}

try {
    $stmt = db()->prepare('DELETE FROM stock_check WHERE check_id = :id');
    $stmt->execute(['id' => $id]);
    flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Stock check #' . $id . ' deleted.' : 'Stock check not found.');
} catch (PDOException $ex) {
    error_log('stock_check_delete failed: ' . $ex->getMessage());
    flash('error', 'Could not delete the stock check. Please try again.');
}
redirect('public/stock_checks.php');
