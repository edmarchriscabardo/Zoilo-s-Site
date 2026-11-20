<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

const ORDER_STATUSES   = ['Pending', 'Completed', 'Cancelled'];
const PAYMENT_STATUSES = ['Unpaid', 'Paid', 'Partial'];

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL inside the project folder, e.g. app_url('public/login.php').
 * Works for any script one level below the project root (public/ or actions/).
 */
function app_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(dirname(dirname($script)), '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return app_url('public/assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function money(mixed $value): string
{
    return '₱' . number_format((float)$value, 2);
}

/* ---------- flash messages & old input (Post-Redirect-Get) ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Remember submitted form values so the form can be re-filled after a redirect. */
function set_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

function has_old(): bool
{
    return !empty($GLOBALS['__old']);
}

function old(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['__old'][$key] ?? $default;
}

/* ---------- input helpers ---------- */

function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Positive integer from POST, or null. */
function post_id(string $key): ?int
{
    $v = $_POST[$key] ?? '';
    return (is_string($v) && ctype_digit($v) && (int)$v > 0) ? (int)$v : null;
}

/** Positive integer from GET, or null. */
function get_id(string $key): ?int
{
    $v = $_GET[$key] ?? '';
    return (is_string($v) && ctype_digit($v) && (int)$v > 0) ? (int)$v : null;
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

/** Returns "HH:MM:SS" or null if invalid. */
function normalize_time(string $t): ?string
{
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t)) {
        return null;
    }
    return strlen($t) === 5 ? $t . ':00' : $t;
}

/** Valid money string "123.45" (up to DECIMAL(10,2)); returns normalized or null. */
function normalize_money(string $v): ?string
{
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) {
        return null;
    }
    return number_format((float)$v, 2, '.', '');
}

/** Existence check. $table and $col must be trusted constants, never user input. */
function row_exists(PDO $pdo, string $table, string $col, int $id): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$col}` = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    return (bool)$stmt->fetchColumn();
}

/* ---------- presentation helpers ---------- */

function badge(string $text): string
{
    $class = strtolower(preg_replace('/[^a-z]/i', '', $text));
    return '<span class="badge badge-' . e($class) . '">' . e($text) . '</span>';
}

function render_pagination(int $page, int $pages, array $params = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = function (int $p, string $label, bool $active = false) use ($params): string {
        $qs = http_build_query(array_merge($params, ['page' => $p]));
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        return '<a class="page-link' . ($active ? ' is-current' : '') . '" href="'
            . e($script . '?' . $qs) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    };
    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) {
        $html .= $link($page - 1, 'Previous');
    }
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $html .= $link($p, (string)$p, $p === $page);
    }
    if ($page < $pages) {
        $html .= $link($page + 1, 'Next');
    }
    return $html . '</nav>';
}

/**
 * Recompute a daily closing's totals from its sales and expenses.
 * total_sales counts only sales marked "Paid".
 */
function recalc_closing(PDO $pdo, int $closingId): void
{
    $stmt = $pdo->prepare(
        "UPDATE daily_closing dc
            SET dc.total_sales = (SELECT COALESCE(SUM(s.amount), 0) FROM sale s
                                   WHERE s.closing_id = :c1 AND s.payment_status = 'Paid'),
                dc.total_expenses = (SELECT COALESCE(SUM(x.expense_amount), 0) FROM expense x
                                      WHERE x.closing_id = :c2)
          WHERE dc.closing_id = :c3"
    );
    $stmt->execute(['c1' => $closingId, 'c2' => $closingId, 'c3' => $closingId]);
}

/** Find the closing row for a date, creating it if needed. Returns closing_id. */
function get_or_create_closing(PDO $pdo, string $date): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO daily_closing (closing_date) VALUES (:d)
         ON DUPLICATE KEY UPDATE closing_id = LAST_INSERT_ID(closing_id)'
    );
    $stmt->execute(['d' => $date]);
    return (int)$pdo->lastInsertId();
}

/** True when the PDOException is a foreign-key / duplicate constraint violation. */
function is_constraint_error(PDOException $e): bool
{
    return ($e->errorInfo[0] ?? '') === '23000';
}

/* ---------- product photos (files live in public/assets/img/products/) ---------- */

/** @return string[] File names of the uploaded product photos (placeholder excluded). */
function product_photos(): array
{
    $dir = dirname(__DIR__) . '/public/assets/img/products';
    $files = is_dir($dir) ? (scandir($dir) ?: []) : [];
    $files = array_filter($files, static fn(string $f): bool => (bool)preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp|gif)$/i', $f));
    sort($files);
    return array_values($files);
}

/** URL of a product photo, or the "No photo yet" placeholder. */
function product_image_url(?string $image): string
{
    $name = $image !== null ? basename($image) : '';
    if ($name === '' || !in_array($name, product_photos(), true)) {
        $name = 'placeholder.svg';
    }
    return asset_url('img/products/' . rawurlencode($name));
}
