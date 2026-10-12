<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

if (current_account()) {
    redirect(url('admin/index.php'));
}
if ((int)q_val('SELECT COUNT(*) FROM accounts') === 0) {
    redirect(url('setup.php'));
}

$error = '';
$username = '';
$staffName = '';

if (is_post()) {
    csrf_verify();
    $username = strtolower(post_str('username', 60));
    $staffName = post_str('staff_name', 80);
    $result = attempt_login($username, (string)($_POST['password'] ?? ''), $staffName);
    if (is_array($result)) {
        flash('success', 'Welcome, ' . staff_name() . '!');
        redirect(url('admin/index.php'));
    }
    $error = $result;
}

auth_start('Staff login');
?>
<h1>Good morning!</h1>
<p class="muted">Log in to update today's stock for your store and aisle.</p>

<?php if ($error): ?>
  <div class="flash flash-error" role="alert" style="margin-bottom:1rem"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/login.php')) ?>" class="form-grid">
  <?= csrf_field() ?>
  <div class="field">
    <label for="l-user">Username</label>
    <input class="input" id="l-user" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
    <span class="hint">For example <strong>ikeja-drinks</strong></span>
  </div>
  <div class="field">
    <label for="l-pass">Password</label>
    <input class="input" id="l-pass" name="password" type="password" autocomplete="current-password" required>
  </div>
  <div class="field">
    <label for="l-name">Your name</label>
    <input class="input" id="l-name" name="staff_name" value="<?= e($staffName) ?>" autocomplete="name" placeholder="e.g. Chioma">
    <span class="hint">Logins are shared, so we note who made each update. (Owner can leave this blank.)</span>
  </div>
  <button class="btn btn-primary btn-block" type="submit"><?= icon('lock', 'icon icon-sm') ?> Log in</button>
</form>
<p style="margin:1.25rem 0 0;font-size:.9rem"><a href="<?= e(url()) ?>">← Back to the website</a></p>
<?php auth_end();
