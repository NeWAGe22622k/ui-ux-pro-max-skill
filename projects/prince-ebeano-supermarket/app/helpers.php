<?php
declare(strict_types=1);

// ---------------------------------------------------------------- output

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function url(string $path = ''): string
{
    $base = rtrim((string)config('base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Asset URL with a cache-busting version so browsers pick up new CSS/JS. */
function asset(string $path): string
{
    $file = PUBLIC_DIR . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [400 => 'Bad request', 403 => 'Not allowed', 404 => 'Page not found', 429 => 'Slow down', 500 => 'Something went wrong'];
    $title = $titles[$code] ?? 'Error';
    page_start($title, ['active' => '']);
    echo '<section class="container error-page"><p class="eyebrow">Error ' . $code . '</p><h1>' . e($title) . '</h1>';
    echo '<p class="lede">' . e($message ?: 'Please go back and try again.') . '</p>';
    echo '<a class="btn btn-primary" href="' . e(url()) . '">Back to home</a></section>';
    page_end();
    exit;
}

// ---------------------------------------------------------------- money & time

function naira(int $kobo): string
{
    return '₦' . number_format($kobo / 100, $kobo % 100 === 0 ? 0 : 2);
}

/** "1,250" or "1250.50" or "₦1,250" → kobo. Returns null when blank or not a number. */
function parse_naira(string $input): ?int
{
    $clean = preg_replace('/[^0-9.]/', '', $input);
    if ($clean === '' || !is_numeric($clean)) {
        return null;
    }
    $kobo = (int)round(((float)$clean) * 100);
    return ($kobo >= 0 && $kobo <= 4000000000) ? $kobo : null;
}

/** "today at 7:42am", "yesterday at 6:10pm", "on Mon 3 Oct". */
function fresh_label(?string $datetime): string
{
    if (!$datetime) {
        return 'not checked yet';
    }
    $t = strtotime($datetime);
    $time = date('g:ia', $t);
    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return "today at $time";
    }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) {
        return "yesterday at $time";
    }
    return 'on ' . date('D j M', $t);
}

function is_today(?string $datetime): bool
{
    return $datetime !== null && date('Y-m-d', strtotime($datetime)) === date('Y-m-d');
}

// ---------------------------------------------------------------- stock status

const STOCK_STATUSES = ['in_stock', 'low_stock', 'out_of_stock'];

function status_meta(string $status): array
{
    return [
        'in_stock'     => ['label' => 'In stock',     'icon' => 'circle-check', 'class' => 'in'],
        'low_stock'    => ['label' => 'Low stock',    'icon' => 'circle-alert', 'class' => 'low'],
        'out_of_stock' => ['label' => 'Out of stock', 'icon' => 'circle-x',     'class' => 'out'],
        'not_carried'  => ['label' => 'Not carried',  'icon' => 'circle-minus', 'class' => 'none'],
    ][$status] ?? ['label' => $status, 'icon' => 'circle-alert', 'class' => 'none'];
}

// ---------------------------------------------------------------- branches & categories

function branches(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = q_all('SELECT * FROM branches WHERE is_active = 1 ORDER BY sort_order, name');
    }
    return $rows;
}

function branch_by_slug(string $slug): ?array
{
    foreach (branches() as $b) {
        if ($b['slug'] === $slug) {
            return $b;
        }
    }
    return null;
}

function branch_by_id(?int $id): ?array
{
    foreach (branches() as $b) {
        if ((int)$b['id'] === $id) {
            return $b;
        }
    }
    return null;
}

/** The shopper's chosen branch: ?branch=slug wins and is remembered in a cookie. */
function current_branch(): ?array
{
    static $resolved = false, $branch = null;
    if ($resolved) {
        return $branch;
    }
    $resolved = true;
    $slug = $_GET['branch'] ?? $_COOKIE['pe_branch'] ?? '';
    $branch = is_string($slug) && $slug !== '' ? branch_by_slug($slug) : null;
    if ($branch && ($_COOKIE['pe_branch'] ?? '') !== $branch['slug']) {
        setcookie('pe_branch', $branch['slug'], [
            'expires'  => time() + 365 * 86400,
            'path'     => url(),
            'secure'   => (bool)config('secure_cookies', true),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    return $branch;
}

function categories(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = q_all('SELECT * FROM categories ORDER BY sort_order, name');
    }
    return $rows;
}

function category_by_slug(string $slug): ?array
{
    foreach (categories() as $c) {
        if ($c['slug'] === $slug) {
            return $c;
        }
    }
    return null;
}

function category_by_id(?int $id): ?array
{
    foreach (categories() as $c) {
        if ((int)$c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $slug !== '' ? $slug : 'item-' . bin2hex(random_bytes(3));
}

// ---------------------------------------------------------------- settings

function setting(string $name, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (q_all('SELECT name, value FROM settings') as $row) {
            $all[$row['name']] = $row['value'];
        }
    }
    return $all[$name] ?? $default;
}

function save_setting(string $name, string $value): void
{
    q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

// ---------------------------------------------------------------- session, flash, csrf

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('pe_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => url(),
        'secure'   => (bool)config('secure_cookies', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(400, 'This form expired. Please reload the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function post_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function get_str(string $key, int $max = 120): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function phone_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}
