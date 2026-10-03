<?php
/**
 * Admin panel helpers: login, the content schema, saving listings and handling photo uploads.
 */
declare(strict_types=1);

const ADMIN_IDLE_TIMEOUT = 4 * 3600;   // log out after 4 hours of inactivity
const LOGIN_MAX_ATTEMPTS = 5;          // per IP address...
const LOGIN_WINDOW = 15 * 60;          // ...in 15 minutes
const IMAGE_MAX_EDGE = 2000;           // uploaded photos are resized to fit within this (px)
const IMAGE_MAX_BYTES = 20 * 1024 * 1024;

/* ---------------------------------------------------------------- Schema */

/** Every editable collection and its fields. Field order = form order. */
function collections(): array
{
    $common = [
        'title'         => ['label' => 'Title', 'type' => 'text', 'required' => true, 'group' => 'Basics', 'hint' => 'For example "Two-bed flat, Victoria Road".'],
        'location'      => ['label' => 'Location', 'type' => 'text', 'group' => 'Basics', 'hint' => 'Area and town. Avoid the full street address for privacy.'],
        'property_type' => ['label' => 'Property type', 'type' => 'text', 'group' => 'Basics', 'half' => true, 'hint' => 'e.g. Terraced house, Flat, HMO'],
        'bedrooms'      => ['label' => 'Bedrooms', 'type' => 'text', 'group' => 'Basics', 'quarter' => true],
        'bathrooms'     => ['label' => 'Bathrooms', 'type' => 'text', 'group' => 'Basics', 'quarter' => true],
    ];
    $visibility = ['published' => 'Shown on the website', 'hidden' => 'Hidden (draft)'];

    return [
        'rentals' => [
            'label'    => 'Rentals',
            'singular' => 'rental',
            'intro'    => 'Homes shown on the Rentals page. Mark a home "Let agreed" once it is taken, or delete it.',
            'fields'   => [
                'title'          => $common['title'],
                'price'          => ['label' => 'Rent', 'type' => 'text', 'group' => 'Basics', 'half' => true, 'hint' => 'Shown exactly as typed, e.g. "£1,150 pcm".'],
                'status'         => ['label' => 'Status', 'type' => 'select', 'group' => 'Basics', 'half' => true, 'options' => ['available' => 'Available', 'let_agreed' => 'Let agreed', 'hidden' => 'Hidden (draft)']],
                'location'       => $common['location'],
                'property_type'  => $common['property_type'],
                'bedrooms'       => $common['bedrooms'],
                'bathrooms'      => $common['bathrooms'],
                'available_from' => ['label' => 'Available from', 'type' => 'text', 'group' => 'Details', 'half' => true, 'hint' => 'e.g. "Now" or "1 November"'],
                'furnishing'     => ['label' => 'Furnishing', 'type' => 'select', 'group' => 'Details', 'half' => true, 'options' => ['' => 'Not specified', 'Unfurnished' => 'Unfurnished', 'Part furnished' => 'Part furnished', 'Furnished' => 'Furnished']],
                'deposit'        => ['label' => 'Deposit', 'type' => 'text', 'group' => 'Details', 'half' => true],
                'featured'       => ['label' => 'Show on the home page', 'type' => 'checkbox', 'group' => 'Details', 'half' => true],
                'summary'        => ['label' => 'Short summary', 'type' => 'textarea', 'rows' => 2, 'group' => 'Details', 'hint' => 'One or two sentences, shown on the listing card.'],
                'description'    => ['label' => 'Full description', 'type' => 'textarea', 'rows' => 8, 'group' => 'Details', 'hint' => 'Leave a blank line between paragraphs.'],
                'features'       => ['label' => 'Key features', 'type' => 'lines', 'group' => 'Details', 'hint' => 'One per line, e.g. "Off-street parking".'],
                'images'         => ['label' => 'Photos', 'type' => 'gallery', 'group' => 'Photos', 'hint' => 'The first photo is the cover image.'],
                'links'          => ['label' => 'Where it is advertised', 'type' => 'links', 'group' => 'Listing links', 'hint' => 'Links to the listing on Rightmove, Zoopla, OpenRent, etc. Hidden once a home is let agreed.'],
            ],
        ],
        'managed' => [
            'label'    => 'Managed properties',
            'singular' => 'managed property',
            'intro'    => 'Properties shown in the "Managed portfolio" section of the Our Properties page.',
            'fields'   => $common + [
                'status'      => ['label' => 'Visibility', 'type' => 'select', 'group' => 'Basics', 'half' => true, 'options' => $visibility],
                'summary'     => ['label' => 'Short summary', 'type' => 'textarea', 'rows' => 2, 'group' => 'Details', 'hint' => 'One or two sentences, shown on the card.'],
                'description' => ['label' => 'Full description', 'type' => 'textarea', 'rows' => 7, 'group' => 'Details', 'hint' => 'Leave a blank line between paragraphs.'],
                'images'      => ['label' => 'Photos', 'type' => 'gallery', 'group' => 'Photos', 'hint' => 'The first photo is the cover image.'],
            ],
        ],
        'flips' => [
            'label'    => 'Renovation projects',
            'singular' => 'renovation project',
            'intro'    => 'Before-and-after projects on the Our Properties page. The first project in this list is also shown on the home page.',
            'fields'   => $common + [
                'completed'   => ['label' => 'Completed', 'type' => 'text', 'group' => 'Basics', 'half' => true, 'hint' => 'e.g. "March 2026"'],
                'status'      => ['label' => 'Visibility', 'type' => 'select', 'group' => 'Basics', 'half' => true, 'options' => $visibility],
                'summary'     => ['label' => 'Short summary', 'type' => 'textarea', 'rows' => 2, 'group' => 'Details'],
                'description' => ['label' => 'The story', 'type' => 'textarea', 'rows' => 7, 'group' => 'Details', 'hint' => 'What the property was like, what you changed, and the result.'],
                'works'       => ['label' => 'Work carried out', 'type' => 'lines', 'group' => 'Details', 'hint' => 'One per line, e.g. "New kitchen".'],
                'before'      => ['label' => 'Before photos', 'type' => 'gallery', 'group' => 'Photos', 'hint' => 'Tip: upload before and after photos of the same rooms in the same order, so the comparison slider pairs them up.'],
                'after'       => ['label' => 'After photos', 'type' => 'gallery', 'group' => 'Photos'],
            ],
        ],
    ];
}

/** Text fields in Site settings. */
function settings_fields(): array
{
    return [
        'Company' => [
            'company_name'   => ['label' => 'Company name'],
            'tagline'        => ['label' => 'Tagline'],
            'company_number' => ['label' => 'Company registration number', 'hint' => 'Shown in the footer. Leave empty to hide.'],
        ],
        'Contact details' => [
            'phone'         => ['label' => 'Phone number'],
            'whatsapp'      => ['label' => 'WhatsApp number', 'hint' => 'International format, e.g. 447700900123. Leave empty to hide.'],
            'email'         => ['label' => 'Public email address', 'type' => 'email'],
            'enquiry_email' => ['label' => 'Send contact-form enquiries to', 'type' => 'email', 'hint' => 'Leave empty to use the public email address. Enquiries are also saved under "Enquiries" here.'],
            'address'       => ['label' => 'Office address', 'textarea' => true],
            'office_hours'  => ['label' => 'Opening hours', 'textarea' => true],
        ],
        'Home page' => [
            'hero_heading'    => ['label' => 'Main heading'],
            'hero_subheading' => ['label' => 'Introduction', 'textarea' => true],
        ],
        'Social media (full links, leave empty to hide)' => [
            'facebook'  => ['label' => 'Facebook', 'type' => 'url'],
            'instagram' => ['label' => 'Instagram', 'type' => 'url'],
            'linkedin'  => ['label' => 'LinkedIn', 'type' => 'url'],
        ],
    ];
}

/* ---------------------------------------------------------------- Auth */

function admin_config(): array
{
    $f = PRIVATE_DIR . '/admin.json';
    return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
}

function admin_is_configured(): bool
{
    return !empty(admin_config()['password_hash']);
}

function admin_set_password(string $password): void
{
    save_json('admin', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated' => date('c')], PRIVATE_DIR);
}

function admin_logged_in(): bool
{
    start_session();
    if (empty($_SESSION['admin'])) {
        return false;
    }
    if (time() - ($_SESSION['admin_seen'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_seen'] = time();
    return true;
}

function admin_login(): void
{
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_seen'] = time();
    unset($_SESSION['csrf']);
}

function admin_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function login_attempts(): array
{
    $f = PRIVATE_DIR . '/login-attempts.json';
    $all = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    $cutoff = time() - LOGIN_WINDOW;
    foreach ($all as $ip => $times) {
        $all[$ip] = array_values(array_filter((array) $times, fn($t) => $t > $cutoff));
        if (!$all[$ip]) {
            unset($all[$ip]);
        }
    }
    return $all;
}

function login_blocked(): bool
{
    return count(login_attempts()[client_ip()] ?? []) >= LOGIN_MAX_ATTEMPTS;
}

function record_login_failure(): void
{
    $all = login_attempts();
    $all[client_ip()][] = time();
    save_json('login-attempts', $all, PRIVATE_DIR);
}

function clear_login_failures(): void
{
    $all = login_attempts();
    unset($all[client_ip()]);
    save_json('login-attempts', $all, PRIVATE_DIR);
}

/* ---------------------------------------------------------------- Flash + redirect */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function admin_url(array $query = []): string
{
    return url('admin/') . ($query ? '?' . http_build_query($query) : '');
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

/* ---------------------------------------------------------------- Saving */

function one_line(string $s, int $max = 200): string
{
    return mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', $s)), 0, $max);
}

function multi_line(string $s, int $max = 10000): string
{
    return mb_substr(trim(str_replace(["\r\n", "\r"], "\n", $s)), 0, $max);
}

function new_id(string $title, array $items): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'property';
    $slug = substr($slug, 0, 48);
    $ids = array_column($items, 'id');
    do {
        $id = $slug . '-' . bin2hex(random_bytes(2));
    } while (in_array($id, $ids, true));
    return $id;
}

function valid_image_path(mixed $p): bool
{
    return is_string($p) && !str_contains($p, '..') && (bool) preg_match(
        '#^(uploads/\d{4}/\d{2}/[a-f0-9]{16}\.(jpg|png|webp)|assets/img/[A-Za-z0-9/_.-]+\.(svg|png|jpe?g|webp))$#',
        $p
    );
}

/** Build a listing from the submitted form. Returns [item, warnings]. */
function item_from_post(array $schema, array $existing): array
{
    $item = $existing;
    $warnings = [];
    foreach ($schema['fields'] as $name => $f) {
        $raw = $_POST[$name] ?? null;
        switch ($f['type']) {
            case 'text':
                $item[$name] = one_line((string) $raw);
                break;
            case 'textarea':
                $item[$name] = multi_line((string) $raw);
                break;
            case 'select':
                $item[$name] = array_key_exists((string) $raw, $f['options']) ? (string) $raw : (string) array_key_first($f['options']);
                break;
            case 'checkbox':
                $item[$name] = !empty($raw);
                break;
            case 'lines':
                $lines = array_map(fn($l) => one_line($l), explode("\n", (string) $raw));
                $item[$name] = array_slice(array_values(array_filter($lines, 'strlen')), 0, 50);
                break;
            case 'gallery':
                $kept = array_values(array_filter((array) $raw, 'valid_image_path'));
                [$added, $errors] = handle_uploads($name . '_new');
                $item[$name] = array_slice(array_values(array_unique(array_merge($kept, $added))), 0, 60);
                array_push($warnings, ...$errors);
                break;
            case 'links':
                $links = [];
                $labels = (array) ($_POST['link_label'] ?? []);
                foreach ((array) ($_POST['link_url'] ?? []) as $k => $u) {
                    $u = trim((string) $u);
                    if ($u === '') {
                        continue;
                    }
                    if (!preg_match('#^https?://#i', $u)) {
                        $u = 'https://' . $u;
                    }
                    if (!filter_var($u, FILTER_VALIDATE_URL)) {
                        $warnings[] = 'Skipped an invalid link: ' . $u;
                        continue;
                    }
                    $label = one_line((string) ($labels[$k] ?? ''), 60);
                    if ($label === '') {
                        $label = preg_replace('/^www\./', '', (string) parse_url($u, PHP_URL_HOST));
                    }
                    $links[] = ['label' => $label, 'url' => $u];
                }
                $item[$name] = array_slice($links, 0, 10);
                break;
        }
    }
    if (($item['title'] ?? '') === '') {
        $item['title'] = 'Untitled ' . $schema['singular'];
    }
    $item['placeholder'] = false;   // edited by a person, so no longer sample content
    $item['updated'] = date('c');
    return [$item, $warnings];
}

/** Every image path used anywhere in the site content. */
function referenced_images(): array
{
    $used = [];
    foreach (array_keys(collections()) as $c) {
        foreach (load_json($c) as $item) {
            foreach (['images', 'before', 'after'] as $k) {
                foreach ($item[$k] ?? [] as $p) {
                    $used[$p] = true;
                }
            }
        }
    }
    return $used;
}

/** Delete uploaded files that are no longer used by any listing. */
function delete_unused_uploads(array $paths): void
{
    $used = referenced_images();
    foreach ($paths as $p) {
        if (str_starts_with((string) $p, 'uploads/') && valid_image_path($p) && !isset($used[$p])) {
            @unlink(SITE_ROOT . '/' . $p);
        }
    }
}

function item_images(array $item): array
{
    return array_merge($item['images'] ?? [], $item['before'] ?? [], $item['after'] ?? []);
}

/* ---------------------------------------------------------------- Uploads */

/** Store uploaded photos from <input type="file" name="$field[]" multiple>. Returns [paths, errors]. */
function handle_uploads(string $field): array
{
    $files = $_FILES[$field] ?? null;
    if (!$files || !is_array($files['name'])) {
        return [[], []];
    }
    $saved = [];
    $errors = [];
    foreach ($files['name'] as $k => $name) {
        $err = $files['error'][$k];
        $label = one_line((string) $name, 80);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $files['size'][$k] > IMAGE_MAX_BYTES) {
            $errors[] = "$label is too large. Please use photos under " . (IMAGE_MAX_BYTES / 1048576) . 'MB.';
            continue;
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($files['tmp_name'][$k])) {
            $errors[] = "$label could not be uploaded (error $err).";
            continue;
        }
        $info = @getimagesize($files['tmp_name'][$k]);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $errors[] = "$label is not a JPG, PNG or WebP photo. (iPhone HEIC photos need converting to JPG first.)";
            continue;
        }
        $path = store_image($files['tmp_name'][$k], $info);
        if ($path) {
            $saved[] = $path;
        } else {
            $errors[] = "$label could not be saved. Check that the uploads folder is writable.";
        }
    }
    return [$saved, $errors];
}

/** Resize (max IMAGE_MAX_EDGE px), fix rotation, strip metadata and save as JPEG. Falls back to a plain copy. */
function store_image(string $tmp, array $info): ?string
{
    $sub = date('Y/m');
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return null;
    }
    $name = bin2hex(random_bytes(8));
    [$w, $h, $type] = $info;

    if (extension_loaded('gd') && $w * $h <= 60_000_000) {
        @ini_set('memory_limit', '512M');
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default        => false,
        };
        if ($src) {
            if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
                $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
                if ($angle && ($r = imagerotate($src, $angle, 0))) {
                    imagedestroy($src);
                    $src = $r;
                }
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min(1, IMAGE_MAX_EDGE / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imageinterlace($dst, true);
            $ok = imagejpeg($dst, "$dir/$name.jpg", 82);
            imagedestroy($src);
            imagedestroy($dst);
            if ($ok) {
                return "uploads/$sub/$name.jpg";
            }
        }
    }
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$type];
    return move_uploaded_file($tmp, "$dir/$name.$ext") ? "uploads/$sub/$name.$ext" : null;
}
