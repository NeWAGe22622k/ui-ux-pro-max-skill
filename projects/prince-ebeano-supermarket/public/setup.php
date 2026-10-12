<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

// One-time owner setup. Locks itself as soon as any account exists.
if ((int)q_val('SELECT COUNT(*) FROM accounts') > 0) {
    abort(404, 'Setup is already complete. Staff can log in from the link at the bottom of every page.');
}

$errors = [];
$username = '';

if (is_post()) {
    csrf_verify();
    $username = strtolower(post_str('username', 60));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/', $username)) {
        $errors[] = 'Username must be at least 3 characters: letters, numbers, dots, dashes or underscores.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        db()->beginTransaction();
        // Re-check inside the transaction so two people can't both become owner.
        if ((int)q_val('SELECT COUNT(*) FROM accounts FOR UPDATE') === 0) {
            q("INSERT INTO accounts (username, password_hash, role) VALUES (?, ?, 'owner')", [$username, password_hash($password, PASSWORD_DEFAULT)]);
        }
        db()->commit();
        flash('success', 'Owner account created. Please log in.');
        redirect(url('admin/login.php'));
    }
}

auth_start('First-time setup');
?>
<h1>Welcome! Let's set up.</h1>
<p class="muted">Create the <strong>owner</strong> account. The owner manages stores, aisles and staff logins. This page disappears once it's done.</p>

<?php foreach ($errors as $err): ?>
  <div class="flash flash-error" role="alert" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div>
<?php endforeach; ?>

<form method="post" class="form-grid" action="<?= e(url('setup.php')) ?>">
  <?= csrf_field() ?>
  <div class="field">
    <label for="s-user">Owner username</label>
    <input class="input" id="s-user" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" required>
  </div>
  <div class="field">
    <label for="s-pass">Password</label>
    <input class="input" id="s-pass" name="password" type="password" autocomplete="new-password" minlength="10" required>
    <span class="hint">At least 10 characters. Keep it private.</span>
  </div>
  <div class="field">
    <label for="s-confirm">Confirm password</label>
    <input class="input" id="s-confirm" name="confirm" type="password" autocomplete="new-password" required>
  </div>
  <button class="btn btn-primary btn-block" type="submit">Create owner account</button>
</form>
<?php auth_end();
