<?php
require __DIR__ . '/../includes/admin.php';

$error = '';

/* ---------- First run: choose a password ---------- */
if (!has_password()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['confirm'] ?? '');
        if (strlen($p1) < 10) $error = 'Please use at least 10 characters.';
        elseif ($p1 !== $p2) $error = 'The two passwords do not match.';
        elseif (!set_password($p1)) $error = 'Could not save the password. Check that the /data folder is writable.';
        else {
            attempt_login($p1);
            flash('Password saved. Welcome to your admin panel.');
            header('Location: index.php');
            exit;
        }
    }
    admin_head('Set up');
    ?>
    <section class="auth">
      <img src="../assets/img/logo.png" alt="Refugehomes" height="56">
      <h1>Set your admin password</h1>
      <p class="hint">This is the first visit to the admin panel. Choose the password you will use to manage properties and rentals. Keep it safe; you can change it later under <em>Site details</em>.</p>
      <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>New password<input type="password" name="password" minlength="10" required autocomplete="new-password" autofocus></label>
        <label>Confirm password<input type="password" name="confirm" minlength="10" required autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Save password</button>
      </form>
    </section>
    <?php
    admin_foot();
    exit;
}

/* ---------- Login ---------- */
if (!is_logged_in()) {
    $locked = login_locked();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
        check_csrf();
        if (attempt_login((string) ($_POST['password'] ?? ''))) {
            header('Location: index.php');
            exit;
        }
        $locked = login_locked();
        $error = $locked ? '' : 'Incorrect password.';
    }
    admin_head('Log in');
    ?>
    <section class="auth">
      <img src="../assets/img/logo.png" alt="Refugehomes" height="56">
      <h1>Admin log in</h1>
      <?php if ($locked): ?><div class="flash flash-error">Too many attempts. Please try again in <?= $locked ?> minute<?= $locked > 1 ? 's' : '' ?>.</div><?php endif; ?>
      <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>Password<input type="password" name="password" required autocomplete="current-password" autofocus></label>
        <button class="btn btn-primary" type="submit">Log in</button>
      </form>
      <p class="hint"><a href="../index.php">← Back to the website</a></p>
    </section>
    <?php
    admin_foot();
    exit;
}

/* ---------- Dashboard ---------- */
$list = valid_list((string) ($_GET['list'] ?? 'properties'));

// Make phone-sized copies of any photos uploaded before that feature existed
// (a few seconds at a time, so the page never hangs).
$deadline = microtime(true) + 5;
foreach (array_keys(LISTS) as $l) {
    foreach (load_json($l, []) as $it) {
        foreach (['images', 'before', 'after'] as $k) {
            foreach ($it[$k] ?? [] as $p) {
                if (microtime(true) > $deadline) break 4;
                ensure_variants($p);
            }
        }
    }
}
$items = load_json($list, []);
$isRentals = $list === 'rentals';

admin_head(LISTS[$list], $list);
?>
<div class="page-head">
  <div>
    <h1><?= h(LISTS[$list]) ?></h1>
    <p class="hint">
      <?= $isRentals
          ? 'Homes shown on the Rentals page. Mark a home as “Let agreed” once it is taken, or unpublish it to hide it.'
          : 'Managed properties and before &amp; after refurbishment projects shown on the Our Properties page.' ?>
      Use the arrows to change the order they appear on the site.
    </p>
  </div>
  <a class="btn btn-primary" href="edit.php?list=<?= $list ?>">+ Add <?= $isRentals ? 'rental' : 'property' ?></a>
</div>

<?php if (!$items): ?>
  <div class="empty">
    <p>Nothing here yet.</p>
    <a class="btn btn-primary" href="edit.php?list=<?= $list ?>">Add your first <?= $isRentals ? 'rental' : 'property' ?></a>
  </div>
<?php else: ?>
  <ul class="rows">
    <?php foreach ($items as $i => $it):
      $id = (string) ($it['id'] ?? '');
      $thumb = cover($it);
      ?>
      <li class="row<?= empty($it['published']) ? ' is-hidden' : '' ?>">
        <img class="row-thumb" src="<?= h(admin_src(img_small($thumb))) ?>" alt="" loading="lazy">
        <div class="row-main">
          <a class="row-title" href="edit.php?list=<?= $list ?>&amp;id=<?= urlencode($id) ?>"><?= h($it['title'] ?? '(untitled)') ?></a>
          <div class="row-meta">
            <?php if ($isRentals): ?>
              <span><?= h(money($it['price_pcm'] ?? 0)) ?> pcm</span>
              <span class="badge <?= ($it['status'] ?? '') === 'let' ? 'badge-dark' : 'badge-green' ?>"><?= ($it['status'] ?? '') === 'let' ? 'Let agreed' : 'Available' ?></span>
            <?php else: ?>
              <span class="badge"><?= ($it['type'] ?? '') === 'flip' ? 'Before &amp; after' : 'Managed' ?></span>
            <?php endif; ?>
            <span><?= h($it['location'] ?? '') ?></span>
            <?php if (empty($it['published'])): ?><span class="badge badge-muted">Hidden</span><?php endif; ?>
          </div>
        </div>
        <div class="row-actions">
          <form method="post" action="action.php">
            <?= csrf_field() ?><input type="hidden" name="list" value="<?= $list ?>"><input type="hidden" name="id" value="<?= h($id) ?>">
            <button class="icon" name="do" value="up" title="Move up" aria-label="Move up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
            <button class="icon" name="do" value="down" title="Move down" aria-label="Move down"<?= $i === count($items) - 1 ? ' disabled' : '' ?>>↓</button>
            <?php if ($isRentals): ?>
              <button class="btn btn-sm" name="do" value="status"><?= ($it['status'] ?? '') === 'let' ? 'Mark available' : 'Mark let' ?></button>
            <?php endif; ?>
            <button class="btn btn-sm" name="do" value="publish"><?= empty($it['published']) ? 'Publish' : 'Hide' ?></button>
          </form>
          <a class="btn btn-sm" href="edit.php?list=<?= $list ?>&amp;id=<?= urlencode($id) ?>">Edit</a>
          <form method="post" action="action.php" data-confirm="Delete “<?= h($it['title'] ?? '') ?>”? This cannot be undone.">
            <?= csrf_field() ?><input type="hidden" name="list" value="<?= $list ?>"><input type="hidden" name="id" value="<?= h($id) ?>">
            <button class="btn btn-sm btn-danger" name="do" value="delete">Delete</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php admin_foot(); ?>
