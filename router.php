<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in server (php -S localhost:8000 router.php).
 * The built-in server ignores .htaccess, so this blocks private files itself.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (preg_match('#(^|/)(config|includes|scripts|\.vscode|\.git)(/|$)#', $path)
    || preg_match('#(^|/)\.env#', $path)
    || preg_match('#\.(sql|md)$#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

return false; // serve the requested file / run the PHP script normally
