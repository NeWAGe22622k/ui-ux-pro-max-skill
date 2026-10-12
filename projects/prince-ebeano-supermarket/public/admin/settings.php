<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

require_owner();

$errors = [];
$qrSlots = ['qr_feedback' => 'Feedback QR code', 'qr_complaint' => 'Complaint QR code'];

if (is_post()) {
    csrf_verify();

    foreach ($qrSlots as $key => $label) {
        if (isset($_POST["remove_$key"])) {
            delete_upload(setting($key));
            save_setting($key, '');
        }
        if (has_upload($key)) {
            try {
                $path = save_uploaded_image($_FILES[$key], 'qr', 'qr');
                delete_upload(setting($key));
                save_setting($key, $path);
            } catch (RuntimeException $ex) {
                $errors[] = "$label: " . $ex->getMessage();
            }
        }
    }

    $careersEmail = post_str('careers_email', 160);
    if ($careersEmail !== '' && !filter_var($careersEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The careers email address doesn\'t look right.';
    } else {
        save_setting('careers_email', $careersEmail);
    }
    save_setting('voucher_denominations', post_str('voucher_denominations', 300));
    save_setting('voucher_note', post_str('voucher_note', 600));

    if (!$errors) {
        flash('success', 'Settings saved.');
        redirect(url('admin/settings.php'));
    }
}

admin_start('Site settings', 'settings');
?>
<div class="admin-head"><div><h1>Site settings</h1><p>Store phone numbers, hours and social links live in <code>app/config.php</code> on the server.</p></div></div>
<?php foreach ($errors as $err): ?><div class="flash flash-error" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" style="max-width:820px">
  <?= csrf_field() ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">

  <section class="card">
    <h2>Contact page QR codes</h2>
    <p class="muted">Upload the QR images customers scan to leave feedback or a complaint.</p>
    <div class="form-grid cols-2">
      <?php foreach ($qrSlots as $key => $label): $current = setting($key); $pid = 'prev-' . $key; ?>
        <div class="field">
          <span class="label"><?= e($label) ?></span>
          <div class="photo-preview tint-sand" id="<?= $pid ?>"><?php if ($current): ?><img src="<?= e(url($current)) ?>" alt=""><?php else: ?><?= icon('qr', 'icon media-icon') ?><?php endif; ?></div>
          <input class="input" type="file" name="<?= $key ?>" accept="image/png,image/jpeg,image/webp" data-preview="<?= $pid ?>" aria-label="Upload <?= e($label) ?>">
          <?php if ($current): ?><label class="check"><input type="checkbox" name="remove_<?= $key ?>" value="1"> Remove</label><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <h2>Gift vouchers</h2>
    <div class="form-grid">
      <div class="field"><label for="s-denoms">Values on sale</label>
        <input class="input" id="s-denoms" name="voucher_denominations" value="<?= e(setting('voucher_denominations', '₦5,000, ₦10,000, ₦20,000, ₦50,000, ₦100,000, ₦200,000')) ?>">
        <span class="hint">Separate with commas.</span></div>
      <div class="field"><label for="s-note">How to buy</label>
        <textarea class="textarea" id="s-note" name="voucher_note" style="min-height:100px"><?= e(setting('voucher_note', 'Gift vouchers are sold at the customer service desk of every Prince Ebeano store. For corporate orders, call us and we\'ll put together a package for you.')) ?></textarea></div>
    </div>
  </section>

  <section class="card">
    <h2>Careers</h2>
    <div class="field"><label for="s-careers">Email for job applications</label>
      <input class="input" id="s-careers" name="careers_email" type="email" value="<?= e(setting('careers_email', (string)config('store.email', ''))) ?>"></div>
  </section>

  <div style="margin-top:1rem"><button class="btn btn-primary" type="submit">Save settings</button></div>
</form>
<?php admin_end();
