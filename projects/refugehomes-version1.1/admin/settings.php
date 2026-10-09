<?php
require __DIR__ . '/../includes/admin.php';
require_login();

$fields = [
    'Contact details' => [
        'phone'      => ['Main phone', 'tel'],
        'phone2'     => ['Second phone (optional)', 'tel'],
        'email'      => ['Email shown on the website', 'email'],
        'contact_to' => ['Send contact-form messages to', 'email'],
        'address'    => ['Office address', 'text'],
        'hours'      => ['Opening hours', 'text'],
    ],
    'Social media (leave blank to hide)' => [
        'whatsapp'  => ['WhatsApp number (international, e.g. 447426921201)', 'text'],
        'facebook'  => ['Facebook page link', 'url'],
        'instagram' => ['Instagram link', 'url'],
        'x'         => ['X (Twitter) link', 'url'],
        'youtube'   => ['YouTube link', 'url'],
        'linkedin'  => ['LinkedIn link', 'url'],
    ],
];
$s = settings();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['form'] ?? '') === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $p1 = (string) ($_POST['password'] ?? '');
        if (!password_verify($cur, auth_data()['hash'] ?? '')) $errors[] = 'Your current password is incorrect.';
        elseif (strlen($p1) < 10) $errors[] = 'Please use at least 10 characters for the new password.';
        elseif ($p1 !== (string) ($_POST['confirm'] ?? '')) $errors[] = 'The new passwords do not match.';
        elseif (!set_password($p1)) $errors[] = 'Could not save the password.';
        else { flash('Password changed.'); header('Location: settings.php'); exit; }
    } else {
        $saved = load_json('settings', []);
        foreach ($fields as $group) {
            foreach ($group as $key => [$label, $type]) {
                $v = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 200);
                if ($v !== '' && $type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errors[] = "$label is not a valid email."; continue; }
                if ($v !== '' && $type === 'url') {
                    if (!preg_match('#^https?://#i', $v)) $v = 'https://' . $v;
                    if (!filter_var($v, FILTER_VALIDATE_URL)) { $errors[] = "$label is not a valid link."; continue; }
                }
                $saved[$key] = $v;
            }
        }
        if (!$errors) {
            if (save_json('settings', $saved)) { flash('Site details saved.'); header('Location: settings.php'); exit; }
            $errors[] = 'Could not save. Check that the /data folder is writable.';
        }
        $s = array_merge($s, $_POST);
    }
}

admin_head('Site details', 'settings');
?>
<div class="page-head">
  <div>
    <h1>Site details</h1>
    <p class="hint">These details appear in the header, footer and contact page across the whole website.</p>
  </div>
</div>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="post" class="editor">
  <?= csrf_field() ?>
  <div class="editor-main">
    <?php foreach ($fields as $title => $group): ?>
      <section class="panel">
        <h2><?= h($title) ?></h2>
        <div class="cols-2">
          <?php foreach ($group as $key => [$label, $type]): ?>
            <label><?= h($label) ?><input type="<?= $type === 'url' ? 'text' : $type ?>" name="<?= $key ?>" value="<?= h((string) ($s[$key] ?? '')) ?>"></label>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <aside class="editor-side">
    <section class="panel sticky">
      <button class="btn btn-primary btn-block" type="submit">Save details</button>
    </section>
  </aside>
</form>

<form method="post" class="editor" style="margin-top:32px">
  <?= csrf_field() ?><input type="hidden" name="form" value="password">
  <div class="editor-main">
    <section class="panel">
      <h2>Change password</h2>
      <div class="cols-3">
        <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
        <label>New password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
        <label>Confirm new password<input type="password" name="confirm" minlength="10" required autocomplete="new-password"></label>
      </div>
      <button class="btn" type="submit">Change password</button>
    </section>
  </div>
</form>
<?php admin_foot(); ?>
