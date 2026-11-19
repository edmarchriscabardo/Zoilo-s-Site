<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();
require_post_csrf('public/closings.php');

$id = post_id('closing_id');
if ($id === null) {
    flash('error', 'Invalid closing.');
    redirect('public/closings.php');
}

try {
    $stmt = db()->prepare('DELETE FROM daily_closing WHERE closing_id = :id');
    $stmt->execute(['id' => $id]);
    flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Closing deleted.' : 'Closing not found.');
} catch (PDOException $ex) {
    if (is_constraint_error($ex)) {
        flash('error', 'This closing still has sales or expenses. Move or delete them first.');
    } else {
        error_log('closing_delete failed: ' . $ex->getMessage());
        flash('error', 'Could not delete the closing. Please try again.');
    }
}
redirect('public/closings.php');
