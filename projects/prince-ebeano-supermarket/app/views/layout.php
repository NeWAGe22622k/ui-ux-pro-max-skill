<?php
declare(strict_types=1);

const PUBLIC_NAV = [
    'home'     => ['Home', '', 'home'],
    'shop'     => ['Shop', 'shop.php', 'basket'],
    'stores'   => ['Stores', 'stores.php', 'map-pin'],
    'about'    => ['About', 'about.php', 'heart'],
    'vouchers' => ['Gift Vouchers', 'gift-vouchers.php', 'gift'],
    'careers'  => ['Careers', 'careers.php', 'briefcase'],
    'contact'  => ['Contact', 'contact.php', 'phone'],
];

/** The current page URL with some query parameters changed. */
function current_url_with(array $changes): string
{
    $path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $path . ($params ? '?' . http_build_query($params) : '');
}

function today_hours(): string
{
    $hours = config('store.hours', []);
    $isSunday = date('w') === '0';
    foreach ($hours as $label => $time) {
        if (stripos($label, 'sun') !== false && $isSunday) {
            return $time;
        }
        if (stripos($label, 'sun') === false && !$isSunday) {
            return $time;
        }
    }
    return (string)reset($hours);
}

function branch_last_checked(int $branchId): ?string
{
    return q_val('SELECT MAX(reviewed_at) FROM category_reviews WHERE branch_id = ?', [$branchId]);
}

/**
 * Opens the public page.
 * $opts: active (nav key), description, branch_prompt (open the branch picker on first visit)
 */
function page_start(string $title, array $opts = []): void
{
    $active = $opts['active'] ?? '';
    $storeName = config('store.name', 'Prince Ebeano Supermarket');
    $fullTitle = $title === '' ? $storeName . ' — See what\'s in stock today' : $title . ' · ' . $storeName;
    $description = $opts['description'] ?? 'Check what is on the shelves at your nearest Prince Ebeano Supermarket before you leave home. Live stock, prices in Naira, updated every morning.';
    $branch = current_branch();
    $phones = config('store.phones', []);
    $firstPhone = $phones[0] ?? '';
    $autoOpen = !empty($opts['branch_prompt']) && !$branch;
    ?>
<!doctype html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#0E4A1F">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= e(rtrim((string)config('base_url', ''), '/') . url('assets/img/logo-badge.png')) ?>">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/logo-badge.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($opts['body_class'] ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<div class="topbar">
  <div class="container topbar-inner">
    <span>Open today <?= e(today_hours()) ?></span>
    <?php if ($firstPhone): ?>
      <span>Call <a href="<?= e(phone_href($firstPhone)) ?>"><?= e($firstPhone) ?></a></span>
    <?php endif; ?>
  </div>
</div>

<header class="site-header" data-header>
  <div class="container header-inner">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($storeName) ?> — home">
      <img src="<?= e(url('assets/img/logo-mark.svg')) ?>" width="44" height="44" alt="">
      <span class="brand-text"><strong>Prince Ebeano</strong><small>Supermarket</small></span>
    </a>

    <nav class="main-nav" aria-label="Main">
      <?php foreach (PUBLIC_NAV as $key => [$label, $href]): if ($key === 'home') continue; ?>
        <a href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <button type="button" class="branch-pill" data-open-branch aria-haspopup="dialog" aria-controls="branch-sheet">
      <?= icon('map-pin', 'icon') ?>
      <span class="branch-pill-text">
        <?php if ($branch): $checked = branch_last_checked((int)$branch['id']); ?>
          <strong><?= e($branch['name']) ?></strong>
          <small><?= $checked ? 'Checked ' . e(fresh_label($checked)) : 'Choose to see stock' ?></small>
        <?php else: ?>
          <strong>Choose store</strong>
          <small>to see live stock</small>
        <?php endif; ?>
      </span>
      <?= icon('chevron-down', 'icon icon-sm') ?>
    </button>

    <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="mobile-nav" data-nav-toggle>
      <?= icon('menu', 'icon icon-open') ?><?= icon('x', 'icon icon-close') ?>
      <span class="visually-hidden">Menu</span>
    </button>
  </div>
  <nav id="mobile-nav" class="mobile-nav" aria-label="Mobile" hidden>
    <div class="container">
      <?php foreach (PUBLIC_NAV as $key => [$label, $href, $ic]): ?>
        <a href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

<?php render_flashes(); ?>
<main id="main" tabindex="-1">
<?php
    $GLOBALS['__branch_auto_open'] = $autoOpen;
    $GLOBALS['__active_nav'] = $active;
}

function render_flashes(): void
{
    $flashes = take_flashes();
    if (!$flashes) {
        return;
    }
    echo '<div class="container flash-stack" role="status">';
    foreach ($flashes as $f) {
        $ic = $f['type'] === 'error' ? 'circle-alert' : 'circle-check';
        echo '<div class="flash flash-' . e($f['type']) . '">' . icon($ic) . '<span>' . e($f['message']) . '</span></div>';
    }
    echo '</div>';
}

function page_end(): void
{
    $phones = config('store.phones', []);
    $hours = config('store.hours', []);
    $socials = array_filter(config('store.socials', []));
    $active = $GLOBALS['__active_nav'] ?? '';
    $branch = current_branch();
    ?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand" href="<?= e(url()) ?>">
        <img src="<?= e(url('assets/img/logo-mark.svg')) ?>" width="32" height="32" alt="">
        <span class="brand-text"><strong>Prince Ebeano</strong><small>Supermarket</small></span>
      </a>
      <p><?= e(config('store.tagline', '')) ?></p>
      <?php if ($socials): ?>
        <div class="socials">
          <?php foreach ($socials as $net => $link): ?>
            <a href="<?= e($link) ?>" rel="noopener" target="_blank" aria-label="<?= e(ucfirst($net === 'x' ? 'X (Twitter)' : $net)) ?>"><?= icon($net === 'x' ? 'x-social' : $net) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <h2 class="footer-title">Opening hours</h2>
      <ul class="footer-list">
        <?php foreach ($hours as $label => $time): ?>
          <li><span><?= e($label) ?></span><strong><?= e($time) ?></strong></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2 class="footer-title">Call us</h2>
      <ul class="footer-list">
        <?php foreach ($phones as $p): ?>
          <li><a href="<?= e(phone_href($p)) ?>"><?= e($p) ?></a></li>
        <?php endforeach; ?>
        <?php if (config('store.whatsapp')): ?>
          <li><a href="https://wa.me/<?= e(config('store.whatsapp')) ?>" rel="noopener" target="_blank">Chat on WhatsApp</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div>
      <h2 class="footer-title">Explore</h2>
      <ul class="footer-list footer-links">
        <?php foreach (PUBLIC_NAV as $key => [$label, $href]): if ($key === 'home') continue; ?>
          <li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e(config('store.name')) ?>. All rights reserved.</span>
    <a href="<?= e(url('admin/login.php')) ?>">Staff login</a>
  </div>
</footer>

<nav class="tabbar" aria-label="Quick links">
  <?php foreach (['home', 'shop', 'stores', 'vouchers', 'contact'] as $key): [$label, $href, $ic] = PUBLIC_NAV[$key]; ?>
    <a href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($key === 'vouchers' ? 'Vouchers' : $label) ?></span></a>
  <?php endforeach; ?>
</nav>

<dialog class="branch-sheet" id="branch-sheet" aria-labelledby="branch-sheet-title" <?= !empty($GLOBALS['__branch_auto_open']) ? 'data-auto-open' : '' ?>>
  <div class="sheet-head">
    <div>
      <h2 id="branch-sheet-title">Which store do you shop at?</h2>
    </div>
    <form method="dialog"><button class="icon-btn" aria-label="Close"><?= icon('x') ?></button></form>
  </div>
  <p class="sheet-sub">We'll show you what's on the shelves there right now. You can switch any time.</p>
  <ul class="branch-options">
    <?php foreach (branches() as $b): $checked = branch_last_checked((int)$b['id']); ?>
      <li>
        <a href="<?= e(current_url_with(['branch' => $b['slug']])) ?>" class="branch-option<?= $branch && $branch['id'] === $b['id'] ? ' is-current' : '' ?>">
          <span class="branch-option-text">
            <strong><?= e($b['name']) ?></strong>
            <small><?= e($b['city']) ?><?= $checked ? ' · checked ' . e(fresh_label($checked)) : '' ?></small>
          </span>
          <?= icon('chevron-right', 'icon icon-sm') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</dialog>
</body>
</html>
<?php
}
