<?php
declare(strict_types=1);

/** Kept for convenience: same as  php scripts/create_user.php USERNAME EMAIL admin */
$argv = [$argv[0] ?? 'create_admin.php', $argv[1] ?? '', $argv[2] ?? '', 'admin'];
require __DIR__ . '/create_user.php';
