<?php
declare(strict_types=1);

function admin_start(string $title, string $active = ''): void
{
    $account = current_account();
    $owner = is_owner();
    $nav = [
        'update'   => ['Morning update', 'admin/index.php', 'grid', false],
        'activity' => ['Activity log', 'admin/activity.php', 'history', false],
        'branches' => ['Branches', 'admin/branches.php', 'store', true],
        'categories' => ['Categories', 'admin/categories.php', 'tag', true],
        'accounts' => ['Staff logins', 'admin/accounts.php', 'users', true],
        'jobs'     => ['Job openings', 'admin/jobs.php', 'briefcase', true],
        'messages' => ['Messages', 'admin/messages.php', 'inbox', true],
        'settings' => ['Site settings', 'admin/settings.php', 'sliders', true],
    ];
    $unread = $owner ? (int)q_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0') : 0;
    ?>
<!doctype html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Staff · Prince Ebeano</title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to content</a>
<div class="admin-shell">
  <?php if ($account): ?>
  <aside class="admin-side">
    <a class="brand brand-light" href="<?= e(url('admin/index.php')) ?>">
      <img src="<?= e(url('assets/img/logo-mark.svg')) ?>" width="40" height="40" alt="">
      <span class="brand-text"><strong>Prince Ebeano</strong><small>Staff area</small></span>
    </a>
    <div class="admin-who">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr(staff_name() ?: 'S', 0, 1))) ?></span>
      <span><strong><?= e(staff_name()) ?></strong><small><?= e(account_scope_label($account)) ?></small></span>
    </div>
    <nav class="admin-nav" aria-label="Staff">
      <?php foreach ($nav as $key => [$label, $href, $ic, $ownerOnly]): if ($ownerOnly && !$owner) continue; ?>
        <a href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
          <?= icon($ic) ?><span><?= e($label) ?></span>
          <?php if ($key === 'messages' && $unread > 0): ?><span class="badge-count"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <hr>
      <a href="<?= e(url('admin/password.php')) ?>"<?= $active === 'password' ? ' aria-current="page"' : '' ?>><?= icon('lock') ?><span>Change password</span></a>
      <a href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= icon('external') ?><span>View website</span></a>
      <form method="post" action="<?= e(url('admin/logout.php')) ?>">
        <?= csrf_field() ?>
        <button type="submit"><?= icon('log-out') ?><span>Log out</span></button>
      </form>
    </nav>
  </aside>
  <?php endif; ?>
  <main id="main" class="admin-main" tabindex="-1">
    <?php render_flashes(); ?>
<?php
}

function admin_end(): void
{
    ?>
  </main>
</div>
</body>
</html>
<?php
}
