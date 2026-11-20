<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();
require_post_csrf('public/closings.php');

$pdo = db();
$id = post_id('closing_id');
$back = $id ? 'public/closing_view.php?id=' . $id : 'public/closings.php';

$date = post_str('closing_date');
if (!valid_date($date)) {
    flash('error', 'Enter a valid closing date.');
    set_old($_POST);
    redirect($back);
}

if ($id !== null && !row_exists($pdo, 'daily_closing', 'closing_id', $id)) {
    flash('error', 'Closing not found.');
    redirect('public/closings.php');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM daily_closing WHERE closing_date = :d AND closing_id <> :id');
$stmt->execute(['d' => $date, 'id' => $id ?? 0]);
if ((int)$stmt->fetchColumn() > 0) {
    flash('error', 'A closing for ' . $date . ' already exists.');
    set_old($_POST);
    redirect($back);
}

try {
    if ($id !== null) {
        $pdo->prepare('UPDATE daily_closing SET closing_date = :d WHERE closing_id = :id')->execute(['d' => $date, 'id' => $id]);
        flash('success', 'Closing date updated.');
    } else {
        $pdo->prepare('INSERT INTO daily_closing (closing_date) VALUES (:d)')->execute(['d' => $date]);
        $id = (int)$pdo->lastInsertId();
        flash('success', 'Closing for ' . $date . ' created.');
    }
} catch (PDOException $ex) {
    error_log('closing_save failed: ' . $ex->getMessage());
    flash('error', is_constraint_error($ex) ? 'A closing for that date already exists.' : 'Could not save the closing. Please try again.');
    set_old($_POST);
    redirect($back);
}
redirect('public/closing_view.php?id=' . $id);
