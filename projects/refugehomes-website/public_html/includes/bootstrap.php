<?php
/**
 * Shared helpers for the public site and the admin panel.
 * Content lives in /content/*.json and is edited through /admin.
 */
declare(strict_types=1);

define('SITE_ROOT', dirname(__DIR__));
define('CONTENT_DIR', SITE_ROOT . '/content');
define('PRIVATE_DIR', SITE_ROOT . '/private');
define('UPLOAD_DIR', SITE_ROOT . '/uploads');

const DEFAULT_SETTINGS = [
    'company_name'    => 'Refugehomes Ltd',
    'tagline'         => 'Built on trust',
    'phone'           => '',
    'whatsapp'        => '',
    'email'           => '',
    'enquiry_email'   => '',
    'address'         => '',
    'office_hours'    => '',
    'company_number'  => '',
    'hero_heading'    => '',
    'hero_subheading' => '',
    'facebook'        => '',
    'instagram'       => '',
    'linkedin'        => '',
];

/** Base URL path of the site (works at the domain root or in a sub-folder). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(SITE_ROOT) ?: SITE_ROOT;
    $base = '';
    if ($docRoot !== '' && str_starts_with($root, $docRoot)) {
        $base = str_replace('\\', '/', substr($root, strlen($docRoot)));
    }
    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Image paths are stored relative to the site root; full URLs are passed through. */
function img(string $path): string
{
    return preg_match('#^https?://#i', $path) ? $path : url($path);
}

/** Cache-busting URL for CSS/JS. */
function asset(string $path): string
{
    $file = SITE_ROOT . '/' . $path;
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_json(string $name, array $default = []): array
{
    $file = CONTENT_DIR . "/$name.json";
    if (!is_file($file)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

/** Atomic write so a half-written file can never break the live site. */
function save_json(string $name, array $data, string $dir = CONTENT_DIR): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = "$dir/$name.json";
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("Could not save $name.json - check folder permissions.");
    }
}

function settings(): array
{
    static $s = null;
    return $s ??= array_merge(DEFAULT_SETTINGS, load_json('settings'));
}

function setting(string $key, string $fallback = ''): string
{
    $v = trim((string) (settings()[$key] ?? ''));
    return $v !== '' ? $v : $fallback;
}

/** Public listings, in the order set in the admin. */
function listings(string $type): array
{
    return array_values(array_filter(load_json($type), fn($i) => ($i['status'] ?? '') !== 'hidden'));
}

function tel_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('rh_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    start_session();
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/** Inline SVG icons (Lucide, MIT) - decorative, so hidden from screen readers. */
function icon(string $name, int $size = 18): string
{
    $paths = [
        'bed'      => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
        'bath'     => '<path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><line x1="10" x2="8" y1="5" y2="7"/><line x1="2" x2="22" y1="12" y2="12"/><line x1="7" x2="7" y1="19" y2="21"/><line x1="17" x2="17" y1="19" y2="21"/>',
        'pin'      => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'home'     => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'images'   => '<path d="M18 22H4a2 2 0 0 1-2-2V6"/><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"/><circle cx="12" cy="8" r="2"/><rect width="16" height="16" x="6" y="2" rx="2"/>',
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'arrow'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail'     => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'key'      => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
        'wrench'   => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'shield'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'trending' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'sofa'     => '<path d="M20 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v3"/><path d="M2 16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z"/><path d="M4 18v2"/><path d="M20 18v2"/>',
        'message'  => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'menu'     => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'x'        => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'left'     => '<path d="m15 18-6-6 6-6"/>',
        'right'    => '<path d="m9 18 6-6-6-6"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Data handed to the gallery/details modal. Only public fields are exposed.
 */
function modal_payload(array $items, string $type): string
{
    $out = [];
    foreach ($items as $i) {
        $row = [
            'id'          => $i['id'] ?? '',
            'title'       => $i['title'] ?? '',
            'location'    => $i['location'] ?? '',
            'summary'     => $i['summary'] ?? '',
            'description' => $i['description'] ?? '',
            'type'        => $i['property_type'] ?? '',
            'bedrooms'    => $i['bedrooms'] ?? '',
            'bathrooms'   => $i['bathrooms'] ?? '',
            'sample'      => !empty($i['placeholder']),
        ];
        if ($type === 'flips') {
            $row['before'] = array_map('img', $i['before'] ?? []);
            $row['after'] = array_map('img', $i['after'] ?? []);
            $row['works'] = $i['works'] ?? [];
            $row['completed'] = $i['completed'] ?? '';
        } else {
            $row['images'] = array_map('img', $i['images'] ?? []);
        }
        if ($type === 'rentals') {
            $row += [
                'price'     => $i['price'] ?? '',
                'available' => $i['available_from'] ?? '',
                'furnishing'=> $i['furnishing'] ?? '',
                'deposit'   => $i['deposit'] ?? '',
                'status'    => $i['status'] ?? 'available',
                'features'  => $i['features'] ?? [],
                'links'     => $i['links'] ?? [],
            ];
        }
        $out[] = $row;
    }
    return json_encode($out, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
