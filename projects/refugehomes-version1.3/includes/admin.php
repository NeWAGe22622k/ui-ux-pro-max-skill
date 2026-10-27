<?php
/**
 * Admin helpers: session auth, CSRF, uploads, layout.
 * Included by every file in /admin.
 */

declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('rh_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => $https]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

const IMAGE_MAX_EDGE = 2000;   // px – longest side after resizing
const IMAGE_QUALITY = 82;      // JPEG quality
const LOGIN_MAX_TRIES = 5;
const LOGIN_LOCK_SECONDS = 900;
const SESSION_IDLE_SECONDS = 43200; // stay logged in for 12 hours of inactivity

/* ---------- Auth ---------- */

function auth_data(): array { return load_json('auth', []); }
function has_password(): bool { return !empty(auth_data()['hash']); }
/**
 * Logged in = a valid session that has been used in the last 12 hours.
 * (Not tied to the visitor's IP address: phones, iPads with iCloud Private
 * Relay and hosting proxies change it between requests.)
 */
function is_logged_in(): bool
{
    if (empty($_SESSION['admin'])) return false;
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
        unset($_SESSION['admin'], $_SESSION['admin_seen']);
        return false;
    }
    $_SESSION['admin_seen'] = time();
    return true;
}
function client_ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function set_password(string $password): bool
{
    return save_json('auth', ['hash' => password_hash($password, PASSWORD_DEFAULT), 'updated' => date('c')]);
}

function login_locked(): int
{
    $tries = load_json('login-attempts', []);
    $t = $tries[client_ip()] ?? null;
    if ($t && ($t['until'] ?? 0) > time()) {
        return (int) ceil(($t['until'] - time()) / 60);
    }
    return 0;
}

function attempt_login(string $password): bool
{
    $tries = load_json('login-attempts', []);
    $ip = client_ip();
    // forget stale entries
    foreach ($tries as $k => $t) {
        if (($t['last'] ?? 0) < time() - 86400) unset($tries[$k]);
    }
    if (password_verify($password, auth_data()['hash'] ?? '')) {
        unset($tries[$ip]);
        save_json('login-attempts', $tries);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['admin_seen'] = time();
        return true;
    }
    $t = $tries[$ip] ?? ['count' => 0, 'until' => 0];
    $t['count']++;
    $t['last'] = time();
    if ($t['count'] >= LOGIN_MAX_TRIES) {
        $t['until'] = time() + LOGIN_LOCK_SECONDS;
        $t['count'] = 0;
    }
    $tries[$ip] = $t;
    save_json('login-attempts', $tries);
    return false;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // PHP drops the whole form when the upload is bigger than post_max_size
        http_response_code(413);
        exit('Those photos are too large to upload in one go (limit ' . ini_get('post_max_size') . '). Please go back and add fewer photos at a time.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session has expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Flash messages ---------- */

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

/* ---------- Listings ---------- */

const LISTS = ['properties' => 'Our Properties', 'rentals' => 'Rentals'];

function valid_list(string $list): string
{
    if (!isset(LISTS[$list])) {
        http_response_code(404);
        exit('Unknown list');
    }
    return $list;
}

function find_index(array $items, string $id): ?int
{
    foreach ($items as $i => $item) {
        if (($item['id'] ?? '') === $id) return $i;
    }
    return null;
}

function make_id(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    return substr($slug ?: 'item', 0, 48) . '-' . bin2hex(random_bytes(3));
}

/** Every image path referenced anywhere in the listings. */
function referenced_images(): array
{
    $refs = [];
    foreach (array_keys(LISTS) as $list) {
        foreach (load_json($list, []) as $item) {
            foreach (['images', 'before', 'after'] as $k) {
                foreach ($item[$k] ?? [] as $p) $refs[$p] = true;
            }
        }
    }
    return $refs;
}

/** Delete uploaded files that are no longer used by any listing. Only touches /uploads. */
function remove_unused(array $paths): void
{
    $refs = referenced_images();
    foreach ($paths as $p) {
        if (isset($refs[$p]) || !preg_match('#^uploads/[a-f0-9]{16}\.(jpg|png|webp)$#', $p)) continue;
        @unlink(ROOT . '/' . $p);
        foreach (IMG_WIDTHS as $w) @unlink(ROOT . '/' . img_variant($p, $w));
    }
}

/* ---------- Image uploads ---------- */

/**
 * Validate, auto-rotate, resize and store one uploaded image.
 * Returns the site-relative path (uploads/xxxx.jpg) or null on failure.
 */
function store_upload(string $tmp, ?string &$error = null): ?string
{
    if (!is_uploaded_file($tmp)) { $error = 'Upload failed.'; return null; }
    $info = @getimagesize($tmp);
    $mime = $info['mime'] ?? '';
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$mime])) { $error = 'Only JPG, PNG or WebP images can be uploaded.'; return null; }

    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    $name = bin2hex(random_bytes(8));

    if (!function_exists('imagecreatefromjpeg')) {
        // No GD available: store the original file as-is.
        $dest = 'uploads/' . $name . '.' . $types[$mime];
        return move_uploaded_file($tmp, ROOT . '/' . $dest) ? $dest : null;
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
    };
    if (!$src) { $error = 'That image could not be read.'; return null; }

    // Respect phone camera orientation
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
        $src = match ($o) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };
    }

    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, IMAGE_MAX_EDGE / max($w, $h));
    $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // flatten transparency onto white
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($out, true);

    $dest = 'uploads/' . $name . '.jpg';
    $ok = imagejpeg($out, ROOT . '/' . $dest, IMAGE_QUALITY);
    if ($ok) make_variants($out, $dest);
    imagedestroy($src); imagedestroy($out);
    if (!$ok) { $error = 'Could not save the image.'; return null; }
    return $dest;
}

/** Save phone/tablet-sized copies of a photo next to it (see img_srcset). */
function make_variants($gd, string $dest): void
{
    $w = imagesx($gd); $h = imagesy($gd);
    foreach (IMG_WIDTHS as $vw) {
        if ($vw >= $w * 0.9) continue;           // not worth a copy this close to the original
        $vh = (int) round($h * $vw / $w);
        $v = imagecreatetruecolor($vw, $vh);
        imagecopyresampled($v, $gd, 0, 0, 0, 0, $vw, $vh, $w, $h);
        imageinterlace($v, true);
        imagejpeg($v, ROOT . '/' . img_variant($dest, $vw), 80);
        imagedestroy($v);
    }
}

/** Create missing copies for a photo uploaded before copies existed. */
function ensure_variants(string $path): void
{
    if (!function_exists('imagecreatefromjpeg') || !preg_match('#^uploads/[a-f0-9]{16}\.(jpg|png|webp)$#', $path)) return;
    if (img_srcset($path) !== '' || !is_file(ROOT . '/' . $path)) return;
    $info = @getimagesize(ROOT . '/' . $path);
    if (!$info || $info[0] <= IMG_WIDTHS[0] / 0.9) return;
    $gd = match ($info['mime'] ?? '') {
        'image/jpeg' => @imagecreatefromjpeg(ROOT . '/' . $path),
        'image/png'  => @imagecreatefrompng(ROOT . '/' . $path),
        'image/webp' => @imagecreatefromwebp(ROOT . '/' . $path),
        default => false,
    };
    if ($gd) { make_variants($gd, $path); imagedestroy($gd); }
}

/** Process a multi-file input (name="field[]"). Returns [paths, errors]. */
function store_uploads(string $field): array
{
    $paths = []; $errors = [];
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) return [$paths, $errors];
    foreach ($f['name'] as $i => $name) {
        $err = $f['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $errors[] = "$name is too large."; continue; }
        if ($err !== UPLOAD_ERR_OK) { $errors[] = "$name failed to upload."; continue; }
        $e = null;
        $p = store_upload($f['tmp_name'][$i], $e);
        if ($p) $paths[] = $p; else $errors[] = "$name: $e";
    }
    return [$paths, $errors];
}

/** Clean an ordered list of existing image paths posted back from the form. */
function posted_paths(string $field): array
{
    $out = [];
    foreach ((array) ($_POST[$field] ?? []) as $p) {
        $p = (string) $p;
        if (preg_match('#^(uploads|assets/img)/[A-Za-z0-9/_\-.]+\.(jpe?g|png|webp)$#i', $p) && !str_contains($p, '..')) {
            $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

/* ---------- Layout ---------- */

function admin_head(string $title, string $section = ''): void
{
    $flash = take_flash();
    ?><!doctype html>
<html lang="en-GB">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($title) ?> · Refugehomes admin</title>
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
<?php if (is_logged_in()): ?>
<header class="bar">
  <a class="bar-brand" href="index.php"><img src="../assets/img/logo.png" alt="Refugehomes" height="34"><span>Admin</span></a>
  <nav class="bar-nav">
    <a href="index.php?list=properties"<?= $section === 'properties' ? ' aria-current="page"' : '' ?>>Our Properties</a>
    <a href="index.php?list=rentals"<?= $section === 'rentals' ? ' aria-current="page"' : '' ?>>Rentals</a>
    <a href="settings.php"<?= $section === 'settings' ? ' aria-current="page"' : '' ?>>Site details</a>
  </nav>
  <div class="bar-end">
    <a href="../index.php" target="_blank" rel="noopener">View site ↗</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="linklike">Log out</button></form>
  </div>
</header>
<?php endif; ?>
<main class="wrap">
<?php if ($flash): ?>
  <div class="flash flash-<?= h($flash[1]) ?>" role="status"><?= h($flash[0]) ?></div>
<?php endif; ?>
<?php
}

function admin_foot(): void
{
    echo "</main>\n<script src=\"admin.js?v=2\" defer></script>\n</body>\n</html>\n";
}

/** Thumbnail URL for an image path stored relative to the site root. */
function admin_src(string $p): string { return '../' . $p; }
