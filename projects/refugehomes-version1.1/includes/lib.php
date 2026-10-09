<?php
/**
 * Shared helpers for the public site and the admin panel.
 * Nothing in this folder is reachable from the web (see includes/.htaccess).
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');

/** Escape for HTML output. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read a JSON file from /data. Returns $default when missing or invalid. */
function load_json(string $name, $default = [])
{
    $file = DATA_DIR . '/' . $name . '.json';
    if (!is_file($file)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

/**
 * Write a JSON file to /data atomically (temp file + rename, under a lock)
 * and keep a .bak copy of the previous version.
 */
function save_json(string $name, $data): bool
{
    $file = DATA_DIR . '/' . $name . '.json';
    $lock = fopen(DATA_DIR . '/.' . $name . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        return false;
    }
    try {
        if (is_file($file)) {
            @copy($file, $file . '.bak');
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($tmp, $json . "\n") === false) {
            @unlink($tmp);
            return false;
        }
        return rename($tmp, $file);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Company details (editable in Admin → Site details). */
function settings(): array
{
    static $s = null;
    if ($s === null) {
        $defaults = [
            'company'      => 'Refugehomes Ltd',
            'tagline'      => 'Built on trust',
            'email'        => 'admin@refugehomesuk.com',
            'phone'        => '+44 7426 921201',
            'phone2'       => '+44 7598 541671',
            'address'      => '192 Nicholls Field, Harlow, CM18 6EF',
            'hours'        => 'Monday to Friday, 9:00am – 5:00pm',
            'whatsapp'     => '447426921201',
            'facebook'     => '',
            'instagram'    => '',
            'x'            => '',
            'youtube'      => '',
            'linkedin'     => '',
            'contact_to'   => 'admin@refugehomesuk.com',
        ];
        $s = array_merge($defaults, load_json('settings', []));
    }
    return $s;
}

/** "tel:" href from a display phone number. */
function tel(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}

/** Published items from a listings file, in saved order. */
function published(string $name): array
{
    return array_values(array_filter(load_json($name, []), fn($i) => !empty($i['published'])));
}

/** First usable image of a property / rental (after-photo for flips). */
function cover(array $item): string
{
    foreach (['images', 'after', 'before'] as $k) {
        if (!empty($item[$k][0])) {
            return $item[$k][0];
        }
    }
    return 'assets/img/placeholder/hero.jpg';
}

/** £1,250 */
function money($n): string
{
    return '£' . number_format((float) $n, 0);
}

/** Inline SVG icon (Lucide-style strokes). */
function icon(string $name, int $size = 20, string $class = ''): string
{
    static $paths = [
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-up-right' => '<path d="M7 17 17 7"/><path d="M7 7h10v10"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'bed' => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
        'bath' => '<path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path d="M10 5 8 7"/><path d="M2 12h20"/><path d="M7 19v2"/><path d="M17 19v2"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'images' => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'menu' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'move' => '<path d="M5 9l-3 3 3 3"/><path d="M19 9l3 3-3 3"/><path d="M2 12h20"/>',
        'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
        'youtube' => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
        'x-social' => '<path d="M4 4l11.733 16h4.267l-11.733-16z"/><path d="M4 20l6.768-6.768"/><path d="M13.228 10.772 20 4"/>',
        'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/>',
        'whatsapp' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
    ];
    $p = $paths[$name] ?? '';
    $cls = trim('icon ' . $class);
    return '<svg class="' . $cls . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Social links that have a URL set. */
function social_links(): array
{
    $s = settings();
    $out = [];
    $map = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'];
    foreach ($map as $key => $label) {
        if (!empty($s[$key])) {
            $out[] = ['url' => $s[$key], 'label' => $label, 'icon' => $key === 'x' ? 'x-social' : $key];
        }
    }
    if (!empty($s['whatsapp'])) {
        $out[] = ['url' => 'https://wa.me/' . preg_replace('/\D/', '', $s['whatsapp']), 'label' => 'WhatsApp', 'icon' => 'whatsapp'];
    }
    return $out;
}

/** Data passed to the gallery modal for a property. */
function property_payload(array $p): array
{
    return [
        'id'       => $p['id'] ?? '',
        'type'     => $p['type'] ?? 'managed',
        'title'    => $p['title'] ?? '',
        'location' => $p['location'] ?? '',
        'summary'  => $p['summary'] ?? '',
        'images'   => array_values($p['images'] ?? []),
        'before'   => array_values($p['before'] ?? []),
        'after'    => array_values($p['after'] ?? []),
    ];
}

/** JSON safe to drop inside an HTML attribute. */
function json_attr($data): string
{
    return h(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
