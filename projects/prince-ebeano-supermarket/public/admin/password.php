<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$account = require_login();
$errors = [];

if (is_post()) {
    csrf_verify();
    $currentPw = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!password_verify($currentPw, $account['password_hash'])) {
        $errors[] = 'Your current password is not right.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if (!$errors) {
        q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $account['id']]);
        session_regenerate_id(true);
        flash('success', 'Password changed. Remember to tell the rest of your team if this login is shared.');
        redirect(url('admin/password.php'));
    }
}

admin_start('Change password', 'password');
?>
<div class="admin-head"><div><h1>Change password</h1><p>For the <strong><?= e($account['username']) ?></strong> login.</p></div></div>

<?php foreach ($errors as $err): ?>
  <div class="flash flash-error" role="alert" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div>
<?php endforeach; ?>

<form class="card form-grid" method="post" style="max-width:480px">
  <?= csrf_field() ?>
  <div class="field"><label for="pw-cur">Current password</label><input class="input" id="pw-cur" type="password" name="current" autocomplete="current-password" required></div>
  <div class="field"><label for="pw-new">New password</label><input class="input" id="pw-new" type="password" name="new" autocomplete="new-password" minlength="8" required><span class="hint">At least 8 characters.</span></div>
  <div class="field"><label for="pw-con">Confirm new password</label><input class="input" id="pw-con" type="password" name="confirm" autocomplete="new-password" required></div>
  <div><button class="btn btn-primary" type="submit">Change password</button></div>
</form>
<?php admin_end();
