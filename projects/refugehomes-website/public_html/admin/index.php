<?php
/**
 * Refugehomes website admin: /admin
 * Manage rentals, managed properties, renovation projects, site details and enquiries.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/admin-lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
start_session();

$COLLECTIONS = collections();
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

// A request bigger than post_max_size arrives with an empty $_POST and $_FILES.
if ($post && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'That upload was too large for the server (limit ' . ini_get('post_max_size') . '). Try adding fewer photos at a time.');
    redirect($_SERVER['REQUEST_URI'] ?? admin_url());
}

/* ------------------------------------------------------------ First run: create password */
if (!admin_is_configured()) {
    $error = '';
    if ($post) {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password_confirm'] ?? '');
        if (!csrf_valid()) {
            $error = 'Your session expired. Please try again.';
        } elseif (mb_strlen($p1) < 10) {
            $error = 'Please use at least 10 characters.';
        } elseif ($p1 !== $p2) {
            $error = 'The two passwords do not match.';
        } else {
            try {
                admin_set_password($p1);
                admin_login();
                flash('success', 'Password created. Welcome to your website admin.');
                redirect(admin_url());
            } catch (RuntimeException $ex) {
                $error = $ex->getMessage();
            }
        }
    }
    auth_page('Set up your admin password', 'Choose the password you will use to manage the website. Keep it somewhere safe.', $error, true);
    exit;
}

/* ------------------------------------------------------------ Login */
if (!admin_logged_in()) {
    $error = '';
    if ($post) {
        if (login_blocked()) {
            $error = 'Too many attempts. Please wait 15 minutes and try again.';
        } elseif (!csrf_valid()) {
            $error = 'Your session expired. Please try again.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), admin_config()['password_hash'])) {
            clear_login_failures();
            admin_login();
            redirect(admin_url());
        } else {
            record_login_failure();
            $error = 'That password is not correct.';
        }
    }
    auth_page('Website admin', 'Enter your password to manage the website.', $error, false);
    exit;
}

/* ------------------------------------------------------------ Actions (POST, then redirect) */
if ($post) {
    if (!csrf_valid()) {
        flash('error', 'Your session expired, so nothing was saved. Please try again.');
        redirect(admin_url());
    }
    $action = (string) ($_POST['action'] ?? '');
    $c = (string) ($_POST['c'] ?? '');
    $schema = $COLLECTIONS[$c] ?? null;
    $id = (string) ($_POST['id'] ?? '');

    try {
        switch ($action) {
            case 'logout':
                admin_logout();
                redirect(admin_url());

            case 'save_item':
                if (!$schema) break;
                $items = load_json($c);
                $idx = null;
                foreach ($items as $k => $it) {
                    if (($it['id'] ?? '') === $id) { $idx = $k; break; }
                }
                $before = $idx !== null ? $items[$idx] : ['id' => new_id((string) ($_POST['title'] ?? ''), $items)];
                [$item, $warnings] = item_from_post($schema, $before);
                if ($idx === null) {
                    array_unshift($items, $item);   // new listings go to the top
                } else {
                    $items[$idx] = $item;
                }
                save_json($c, array_values($items));
                delete_unused_uploads(array_diff(item_images($before), item_images($item)));
                flash('success', '"' . $item['title'] . '" saved.' . ($idx === null ? ' It is now at the top of the list.' : ''));
                foreach ($warnings as $w) flash('warning', $w);
                redirect(isset($_POST['save_and_close'])
                    ? admin_url(['view' => 'list', 'c' => $c])
                    : admin_url(['view' => 'edit', 'c' => $c, 'id' => $item['id']]));

            case 'delete_item':
                if (!$schema) break;
                $items = load_json($c);
                $gone = array_values(array_filter($items, fn($it) => ($it['id'] ?? '') === $id));
                save_json($c, array_values(array_filter($items, fn($it) => ($it['id'] ?? '') !== $id)));
                if ($gone) {
                    delete_unused_uploads(item_images($gone[0]));
                    flash('success', '"' . $gone[0]['title'] . '" deleted.');
                }
                redirect(admin_url(['view' => 'list', 'c' => $c]));

            case 'move_item':
                if (!$schema) break;
                $items = load_json($c);
                $dir = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
                foreach ($items as $k => $it) {
                    if (($it['id'] ?? '') === $id && isset($items[$k + $dir])) {
                        [$items[$k], $items[$k + $dir]] = [$items[$k + $dir], $items[$k]];
                        save_json($c, $items);
                        break;
                    }
                }
                redirect(admin_url(['view' => 'list', 'c' => $c]) . '#row-' . rawurlencode($id));

            case 'save_settings':
                $s = settings();
                $bad = [];
                foreach (settings_fields() as $fields) {
                    foreach ($fields as $key => $f) {
                        $v = !empty($f['textarea']) ? multi_line((string) ($_POST[$key] ?? ''), 2000) : one_line((string) ($_POST[$key] ?? ''));
                        if ($v !== '' && ($f['type'] ?? '') === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                            $bad[] = $f['label'];
                            continue;
                        }
                        if ($v !== '' && ($f['type'] ?? '') === 'url' && !preg_match('#^https?://#i', $v)) {
                            $v = 'https://' . $v;
                        }
                        $s[$key] = $v;
                    }
                }
                save_json('settings', $s);
                flash('success', 'Site settings saved.');
                foreach ($bad as $label) flash('warning', "$label was not changed: that doesn't look like a valid email address.");
                redirect(admin_url(['view' => 'settings']));

            case 'delete_enquiry':
                $all = json_decode((string) @file_get_contents(PRIVATE_DIR . '/enquiries.json'), true) ?: [];
                save_json('enquiries', array_values(array_filter($all, fn($q) => ($q['id'] ?? '') !== $id)), PRIVATE_DIR);
                flash('success', 'Enquiry deleted.');
                redirect(admin_url(['view' => 'enquiries']));

            case 'change_password':
                $cur = (string) ($_POST['current'] ?? '');
                $p1 = (string) ($_POST['password'] ?? '');
                if (!password_verify($cur, admin_config()['password_hash'])) {
                    flash('error', 'Your current password is not correct.');
                } elseif (mb_strlen($p1) < 10) {
                    flash('error', 'Please use at least 10 characters for the new password.');
                } elseif ($p1 !== ($_POST['password_confirm'] ?? '')) {
                    flash('error', 'The new passwords do not match.');
                } else {
                    admin_set_password($p1);
                    flash('success', 'Password changed.');
                }
                redirect(admin_url(['view' => 'password']));
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(admin_url());
}

/* ------------------------------------------------------------ Views */
$view = (string) ($_GET['view'] ?? 'dashboard');
$c = (string) ($_GET['c'] ?? '');
$schema = $COLLECTIONS[$c] ?? null;

switch ($view) {
    case 'list':
        if (!$schema) redirect(admin_url());
        layout_start($schema['label'], $c);
        view_list($c, $schema);
        break;
    case 'edit':
        if (!$schema) redirect(admin_url());
        $item = null;
        foreach (load_json($c) as $it) {
            if (($it['id'] ?? '') === ($_GET['id'] ?? '')) { $item = $it; break; }
        }
        layout_start(($item ? 'Edit ' : 'Add ') . $schema['singular'], $c);
        view_edit($c, $schema, $item);
        break;
    case 'settings':
        layout_start('Site settings', 'settings');
        view_settings();
        break;
    case 'enquiries':
        layout_start('Enquiries', 'enquiries');
        view_enquiries();
        break;
    case 'password':
        layout_start('Change password', 'password');
        view_password();
        break;
    default:
        layout_start('Dashboard', 'dashboard');
        view_dashboard($COLLECTIONS);
}
layout_end();


/* ============================================================ Templates */

function admin_head(string $title): void
{
    ?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Refugehomes admin</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon-32.png')) ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
</head>
<?php
}

function auth_page(string $title, string $intro, string $error, bool $setup): void
{
    admin_head($title);
    ?>
<body class="auth">
  <main class="auth-card">
    <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Refugehomes" width="406" height="155" class="auth-logo">
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($intro) ?></p>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label for="password"><?= $setup ? 'New password' : 'Password' ?></label>
        <input id="password" name="password" type="password" required autofocus autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"<?= $setup ? ' minlength="10"' : '' ?>>
        <?php if ($setup): ?><p class="hint">At least 10 characters. A short phrase works well.</p><?php endif; ?>
      </div>
      <?php if ($setup): ?>
        <div class="field">
          <label for="password_confirm">Confirm password</label>
          <input id="password_confirm" name="password_confirm" type="password" required minlength="10" autocomplete="new-password">
        </div>
      <?php endif; ?>
      <button class="btn btn-primary btn-block" type="submit"><?= $setup ? 'Create password and continue' : 'Log in' ?></button>
    </form>
    <p class="auth-back"><a href="<?= e(url()) ?>">&larr; Back to website</a></p>
  </main>
</body>
</html>
<?php
}

function layout_start(string $title, string $active): void
{
    global $COLLECTIONS;
    admin_head($title);
    $enquiries = count(json_decode((string) @file_get_contents(PRIVATE_DIR . '/enquiries.json'), true) ?: []);
    $nav = ['dashboard' => ['Dashboard', admin_url()]];
    foreach ($COLLECTIONS as $key => $s) {
        $nav[$key] = [$s['label'], admin_url(['view' => 'list', 'c' => $key])];
    }
    $nav['enquiries'] = ['Enquiries' . ($enquiries ? " ($enquiries)" : ''), admin_url(['view' => 'enquiries'])];
    $nav['settings'] = ['Site settings', admin_url(['view' => 'settings'])];
    ?>
<body>
<a class="skip-link" href="#content">Skip to content</a>
<div class="shell">
  <aside class="sidebar">
    <a class="sidebar-brand" href="<?= e(admin_url()) ?>"><img src="<?= e(url('assets/img/logo-light.png')) ?>" alt="Refugehomes admin" width="406" height="155"></a>
    <nav aria-label="Admin">
      <ul>
        <?php foreach ($nav as $key => [$label, $href]): ?>
          <li><a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <ul class="sidebar-secondary">
        <li><a href="<?= e(url()) ?>" target="_blank" rel="noopener">View website &nearr;</a></li>
        <li><a href="<?= e(admin_url(['view' => 'password'])) ?>"<?= $active === 'password' ? ' aria-current="page"' : '' ?>>Change password</a></li>
        <li>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit" class="linklike">Log out</button></form>
        </li>
      </ul>
    </nav>
  </aside>
  <main class="content" id="content">
    <?php foreach (take_flashes() as [$type, $msg]): ?>
      <div class="flash flash-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
    <?php endforeach; ?>
<?php
}

function layout_end(): void
{
    echo "  </main>\n</div>\n</body>\n</html>\n";
}

function thumb(?string $path): string
{
    return $path
        ? '<img src="' . e(img($path)) . '" alt="" loading="lazy">'
        : '<span class="thumb-empty">No photo</span>';
}

function status_pill(array $item): string
{
    $map = [
        'available'  => ['Available', 'green'],
        'let_agreed' => ['Let agreed', 'dark'],
        'hidden'     => ['Hidden', 'grey'],
        'published'  => ['Shown', 'green'],
    ];
    [$label, $tone] = $map[$item['status'] ?? 'published'] ?? $map['published'];
    $out = '<span class="pill pill-' . $tone . '">' . $label . '</span>';
    if (!empty($item['placeholder'])) {
        $out .= ' <span class="pill pill-amber">Sample</span>';
    }
    return $out;
}

function view_dashboard(array $collections): void
{
    $samples = 0;
    $enquiries = json_decode((string) @file_get_contents(PRIVATE_DIR . '/enquiries.json'), true) ?: [];
    ?>
    <header class="page-head">
      <h1>Welcome back</h1>
      <p class="muted">What would you like to update today?</p>
    </header>
    <div class="tiles">
      <?php foreach ($collections as $key => $s):
          $items = load_json($key);
          $samples += count(array_filter($items, fn($i) => !empty($i['placeholder'])));
          ?>
        <section class="tile">
          <h2><?= e($s['label']) ?></h2>
          <p class="tile-num"><?= count($items) ?></p>
          <div class="tile-actions">
            <a class="btn btn-primary btn-sm" href="<?= e(admin_url(['view' => 'edit', 'c' => $key])) ?>">+ Add</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url(['view' => 'list', 'c' => $key])) ?>">Manage</a>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <?php if ($samples): ?>
      <div class="callout">
        <strong><?= $samples ?> sample listing<?= $samples === 1 ? ' is' : 's are' ?> still on the website.</strong>
        These are placeholders, marked "Sample listing" on the site. Edit them with your real details or delete them.
        Also check <a href="<?= e(admin_url(['view' => 'settings'])) ?>">Site settings</a> for your phone number, email and address.
      </div>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head">
        <h2>Latest enquiries</h2>
        <a href="<?= e(admin_url(['view' => 'enquiries'])) ?>">View all</a>
      </div>
      <?php if (!$enquiries): ?>
        <p class="muted">No enquiries yet. Messages sent from the contact page will appear here.</p>
      <?php else: ?>
        <ul class="enquiry-mini">
          <?php foreach (array_slice($enquiries, 0, 5) as $q): ?>
            <li><strong><?= e($q['name']) ?></strong> <span class="muted">&middot; <?= e($q['type']) ?> &middot; <?= e(date('j M Y, H:i', strtotime($q['date']))) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php
}

function view_list(string $c, array $schema): void
{
    $items = load_json($c);
    $cover = $c === 'flips' ? 'after' : 'images';
    ?>
    <header class="page-head page-head-row">
      <div>
        <h1><?= e($schema['label']) ?></h1>
        <p class="muted"><?= e($schema['intro']) ?> The order here is the order on the website.</p>
      </div>
      <a class="btn btn-primary" href="<?= e(admin_url(['view' => 'edit', 'c' => $c])) ?>">+ Add <?= e($schema['singular']) ?></a>
    </header>

    <?php if (!$items): ?>
      <div class="empty">Nothing here yet. <a href="<?= e(admin_url(['view' => 'edit', 'c' => $c])) ?>">Add the first <?= e($schema['singular']) ?></a>.</div>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($items as $k => $it): $edit = admin_url(['view' => 'edit', 'c' => $c, 'id' => $it['id']]); ?>
          <li class="row" id="row-<?= e($it['id']) ?>">
            <a class="row-thumb" href="<?= e($edit) ?>" tabindex="-1" aria-hidden="true"><?= thumb($it[$cover][0] ?? null) ?></a>
            <div class="row-main">
              <a class="row-title" href="<?= e($edit) ?>"><?= e($it['title']) ?></a>
              <div class="row-meta">
                <?= status_pill($it) ?>
                <?php if (!empty($it['price'])): ?><span><?= e($it['price']) ?></span><?php endif; ?>
                <?php if (!empty($it['location'])): ?><span class="muted"><?= e($it['location']) ?></span><?php endif; ?>
                <?php if (!empty($it['featured']) && $c === 'rentals'): ?><span class="muted">&middot; On home page</span><?php endif; ?>
              </div>
            </div>
            <div class="row-actions">
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="move_item"><input type="hidden" name="c" value="<?= e($c) ?>"><input type="hidden" name="id" value="<?= e($it['id']) ?>">
                <button class="icon-btn" name="dir" value="up" aria-label="Move up"<?= $k === 0 ? ' disabled' : '' ?>>&uarr;</button>
                <button class="icon-btn" name="dir" value="down" aria-label="Move down"<?= $k === count($items) - 1 ? ' disabled' : '' ?>>&darr;</button>
              </form>
              <a class="btn btn-ghost btn-sm" href="<?= e($edit) ?>">Edit</a>
              <form method="post" data-confirm="Delete &quot;<?= e($it['title']) ?>&quot;? Its photos will be deleted too. This cannot be undone.">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="c" value="<?= e($c) ?>"><input type="hidden" name="id" value="<?= e($it['id']) ?>">
                <button class="btn btn-danger-ghost btn-sm" type="submit">Delete</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif;
}

function view_edit(string $c, array $schema, ?array $item): void
{
    $item ??= ['status' => array_key_first($schema['fields']['status']['options'] ?? ['' => ''])];
    $groups = [];
    foreach ($schema['fields'] as $name => $f) {
        $groups[$f['group']][$name] = $f;
    }
    $upload_limit = ini_get('upload_max_filesize');
    $max_files = (int) ini_get('max_file_uploads');
    ?>
    <header class="page-head page-head-row">
      <div>
        <p class="crumbs"><a href="<?= e(admin_url(['view' => 'list', 'c' => $c])) ?>">&larr; <?= e($schema['label']) ?></a></p>
        <h1><?= isset($item['id']) ? e($item['title']) : 'Add ' . e($schema['singular']) ?></h1>
        <?php if (!empty($item['placeholder'])): ?><p class="callout callout-inline">This is sample content. Saving it with your changes removes the "Sample listing" badge.</p><?php endif; ?>
      </div>
    </header>

    <form method="post" enctype="multipart/form-data" class="edit-form" data-edit-form>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="c" value="<?= e($c) ?>">
      <input type="hidden" name="id" value="<?= e($item['id'] ?? '') ?>">

      <?php foreach ($groups as $group => $fields): ?>
        <fieldset class="panel">
          <legend><?= e($group) ?></legend>
          <div class="grid">
            <?php foreach ($fields as $name => $f) render_field($name, $f, $item, $upload_limit, $max_files); ?>
          </div>
        </fieldset>
      <?php endforeach; ?>

      <div class="savebar">
        <button class="btn btn-primary" type="submit" data-submit>Save</button>
        <button class="btn btn-ghost" type="submit" name="save_and_close" value="1" data-submit>Save and back to list</button>
        <a class="btn btn-link" href="<?= e(admin_url(['view' => 'list', 'c' => $c])) ?>">Cancel</a>
      </div>
    </form>
    <?php
}

function render_field(string $name, array $f, array $item, string $upload_limit, int $max_files): void
{
    $id = 'f-' . $name;
    $val = $item[$name] ?? '';
    $cls = 'field' . (!empty($f['half']) ? ' span-half' : '') . (!empty($f['quarter']) ? ' span-quarter' : '');
    $hint = !empty($f['hint']) ? '<p class="hint" id="' . $id . '-hint">' . e($f['hint']) . '</p>' : '';
    $described = !empty($f['hint']) ? ' aria-describedby="' . $id . '-hint"' : '';

    echo '<div class="' . $cls . ($f['type'] === 'gallery' || $f['type'] === 'links' ? ' span-full' : '') . '">';
    switch ($f['type']) {
        case 'text':
            echo '<label for="' . $id . '">' . e($f['label']) . '</label>';
            echo '<input id="' . $id . '" name="' . e($name) . '" type="text" maxlength="200" value="' . e($val) . '"' . (!empty($f['required']) ? ' required' : '') . $described . '>';
            echo $hint;
            break;
        case 'textarea':
            echo '<label for="' . $id . '">' . e($f['label']) . '</label>';
            echo '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($f['rows'] ?? 4) . '"' . $described . '>' . e($val) . '</textarea>';
            echo $hint;
            break;
        case 'lines':
            echo '<label for="' . $id . '">' . e($f['label']) . '</label>';
            echo '<textarea id="' . $id . '" name="' . e($name) . '" rows="5"' . $described . '>' . e(implode("\n", (array) $val)) . '</textarea>';
            echo $hint;
            break;
        case 'select':
            echo '<label for="' . $id . '">' . e($f['label']) . '</label><select id="' . $id . '" name="' . e($name) . '">';
            foreach ($f['options'] as $k => $label) {
                echo '<option value="' . e($k) . '"' . ((string) $val === (string) $k ? ' selected' : '') . '>' . e($label) . '</option>';
            }
            echo '</select>' . $hint;
            break;
        case 'checkbox':
            echo '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . (!empty($val) ? ' checked' : '') . '> ' . e($f['label']) . '</label>' . $hint;
            break;
        case 'gallery':
            echo '<div class="gallery-field" data-gallery>';
            echo '<div class="gallery-head"><span class="label">' . e($f['label']) . ' <span class="muted" data-gallery-count></span></span></div>';
            echo $hint;
            echo '<ul class="gallery-list" data-gallery-list>';
            foreach ((array) $val as $p) {
                echo '<li class="gallery-item"><img src="' . e(img($p)) . '" alt="" loading="lazy">'
                    . '<input type="hidden" name="' . e($name) . '[]" value="' . e($p) . '">'
                    . '<span class="gi-cover">Cover</span>'
                    . '<div class="gi-actions">'
                    . '<button type="button" class="icon-btn" data-move="-1" aria-label="Move photo earlier">&larr;</button>'
                    . '<button type="button" class="icon-btn" data-move="1" aria-label="Move photo later">&rarr;</button>'
                    . '<button type="button" class="icon-btn icon-btn-danger" data-remove aria-label="Remove photo">&times;</button>'
                    . '</div></li>';
            }
            echo '</ul>';
            echo '<label class="dropzone" data-dropzone><input type="file" name="' . e($name) . '_new[]" multiple accept="image/jpeg,image/png,image/webp" data-gallery-input>'
                . '<span class="dropzone-text"><strong>Add photos</strong> &mdash; click to choose, or drag photos here</span>'
                . '<span class="hint">JPG, PNG or WebP. Up to ' . e($upload_limit) . ' each, ' . $max_files . ' at a time. Large photos are resized automatically.</span></label>';
            echo '<ul class="gallery-list gallery-pending" data-gallery-pending aria-live="polite"></ul>';
            echo '</div>';
            break;
        case 'links':
            $links = (array) $val ?: [];
            echo '<div class="links-field" data-links><span class="label">' . e($f['label']) . '</span>' . $hint;
            echo '<div class="link-rows" data-link-rows>';
            foreach (array_merge($links, [['label' => '', 'url' => '']]) as $i => $l) {
                echo link_row($l['label'] ?? '', $l['url'] ?? '', $i);
            }
            echo '</div><template data-link-template>' . link_row('', '', 0) . '</template>';
            echo '<button type="button" class="btn btn-ghost btn-sm" data-add-link>+ Add another link</button></div>';
            break;
    }
    echo '</div>';
}

function link_row(string $label, string $url, int $i): string
{
    return '<div class="link-row">'
        . '<label><span class="sr-only">Website name</span><input type="text" name="link_label[]" value="' . e($label) . '" placeholder="Website, e.g. Rightmove" maxlength="60"></label>'
        . '<label><span class="sr-only">Link to the listing</span><input type="url" name="link_url[]" value="' . e($url) . '" placeholder="https://www.rightmove.co.uk/properties/..."></label>'
        . '<button type="button" class="icon-btn icon-btn-danger" data-remove-link aria-label="Remove link">&times;</button>'
        . '</div>';
}

function view_settings(): void
{
    $s = settings();
    ?>
    <header class="page-head">
      <h1>Site settings</h1>
      <p class="muted">Contact details and text used across the website.</p>
    </header>
    <form method="post" class="edit-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_settings">
      <?php foreach (settings_fields() as $group => $fields): ?>
        <fieldset class="panel">
          <legend><?= e($group) ?></legend>
          <div class="grid">
            <?php foreach ($fields as $key => $f): $id = 's-' . $key; ?>
              <div class="field<?= empty($f['textarea']) ? ' span-half' : ' span-full' ?>">
                <label for="<?= e($id) ?>"><?= e($f['label']) ?></label>
                <?php if (!empty($f['textarea'])): ?>
                  <textarea id="<?= e($id) ?>" name="<?= e($key) ?>" rows="3"><?= e($s[$key] ?? '') ?></textarea>
                <?php else: ?>
                  <input id="<?= e($id) ?>" name="<?= e($key) ?>" type="<?= e($f['type'] ?? 'text') ?>" value="<?= e($s[$key] ?? '') ?>">
                <?php endif; ?>
                <?php if (!empty($f['hint'])): ?><p class="hint"><?= e($f['hint']) ?></p><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <div class="savebar"><button class="btn btn-primary" type="submit" data-submit>Save settings</button></div>
    </form>
    <?php
}

function view_enquiries(): void
{
    $all = json_decode((string) @file_get_contents(PRIVATE_DIR . '/enquiries.json'), true) ?: [];
    ?>
    <header class="page-head">
      <h1>Enquiries</h1>
      <p class="muted">Messages sent through the contact page, newest first. They are also emailed to the address in Site settings.</p>
    </header>
    <?php if (!$all): ?>
      <div class="empty">No enquiries yet.</div>
    <?php endif; ?>
    <?php foreach ($all as $q): ?>
      <article class="panel enquiry">
        <div class="panel-head">
          <div>
            <h2><?= e($q['name']) ?></h2>
            <p class="muted"><?= e($q['type']) ?> &middot; <?= e(date('j M Y, H:i', strtotime($q['date']))) ?></p>
          </div>
          <form method="post" data-confirm="Delete this enquiry?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_enquiry"><input type="hidden" name="id" value="<?= e($q['id']) ?>">
            <button class="btn btn-danger-ghost btn-sm" type="submit">Delete</button>
          </form>
        </div>
        <dl class="enquiry-facts">
          <div><dt>Email</dt><dd><a href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a></dd></div>
          <?php if (!empty($q['phone'])): ?><div><dt>Phone</dt><dd><a href="<?= e(tel_href($q['phone'])) ?>"><?= e($q['phone']) ?></a></dd></div><?php endif; ?>
          <?php if (!empty($q['property'])): ?><div><dt>Property</dt><dd><?= e($q['property']) ?></dd></div><?php endif; ?>
        </dl>
        <p class="enquiry-msg"><?= nl2br(e($q['message'])) ?></p>
      </article>
    <?php endforeach;
}

function view_password(): void
{
    ?>
    <header class="page-head"><h1>Change password</h1></header>
    <form method="post" class="panel stack narrow">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="field"><label for="cur">Current password</label><input id="cur" name="current" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="np">New password</label><input id="np" name="password" type="password" required minlength="10" autocomplete="new-password"><p class="hint">At least 10 characters.</p></div>
      <div class="field"><label for="np2">Confirm new password</label><input id="np2" name="password_confirm" type="password" required minlength="10" autocomplete="new-password"></div>
      <div><button class="btn btn-primary" type="submit">Change password</button></div>
    </form>
    <?php
}
