<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'branch' => '', 'message' => ''];

if (is_post()) {
    csrf_verify();
    foreach ($old as $k => $_) {
        $old[$k] = post_str($k, $k === 'message' ? 3000 : 160);
    }

    // Bots fill the hidden "website" field; people never see it.
    if (post_str('website') !== '') {
        flash('success', 'Thank you! Your message has been sent.');
        redirect(url('contact.php'));
    }

    if ($old['name'] === '') $errors['name'] = 'Please tell us your name.';
    if ($old['email'] === '' && $old['phone'] === '') $errors['email'] = 'Please give us an email or phone number so we can reply.';
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'That email address doesn\'t look right.';
    if (mb_strlen($old['message']) < 5) $errors['message'] = 'Please write a short message.';

    $recent = (int)q_val('SELECT COUNT(*) FROM contact_messages WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)', [client_ip()]);
    if ($recent >= 3) {
        $errors['form'] = 'You\'ve sent a few messages already. Please wait a few minutes, or call us directly.';
    }

    if (!$errors) {
        $branch = $old['branch'] !== '' ? branch_by_slug($old['branch']) : null;
        q('INSERT INTO contact_messages (name, email, phone, subject, branch_id, message, ip) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $old['name'], $old['email'], $old['phone'], $old['subject'], $branch['id'] ?? null, $old['message'], client_ip(),
        ]);

        $to = (string)config('notify_email', '');
        if ($to !== '') {
            $body = "New message from the website\n\nName: {$old['name']}\nEmail: {$old['email']}\nPhone: {$old['phone']}\n"
                . 'Store: ' . ($branch['name'] ?? '—') . "\nSubject: {$old['subject']}\n\n{$old['message']}\n";
            $headers = 'Content-Type: text/plain; charset=UTF-8';
            if (filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
                $headers .= "\r\nReply-To: " . str_replace(["\r", "\n"], '', $old['email']);
            }
            @mail($to, '=?UTF-8?B?' . base64_encode('Website message: ' . ($old['subject'] ?: $old['name'])) . '?=', $body, $headers);
        }

        flash('success', 'Thank you, ' . $old['name'] . '! Your message has been sent. We\'ll get back to you soon.');
        redirect(url('contact.php'));
    }
}

$phones = config('store.phones', []);
$qrFeedback = setting('qr_feedback');
$qrComplaint = setting('qr_complaint');
$current = current_branch();
if ($old['branch'] === '' && $current) {
    $old['branch'] = $current['slug'];
}

page_start('Contact us', [
    'active' => 'contact',
    'description' => 'Questions, feedback or a complaint? Call, WhatsApp or send Prince Ebeano Supermarket a message — our team is always here to help.',
]);
?>

<section class="page-hero page-hero-dark">
  <div class="container">
    <p class="eyebrow">Contact us</p>
    <h1>Ask us anything.</h1>
    <p class="lede">A question, a suggestion or help with anything at all — our team is here to listen and respond, because your experience matters to us.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container contact-grid">
    <div class="form-card">
      <h2 style="font-size:1.6rem">Send us a message</h2>
      <?php if (!empty($errors['form'])): ?><div class="flash flash-error" style="margin-bottom:1rem"><?= icon('circle-alert') ?><span><?= e($errors['form']) ?></span></div><?php endif; ?>
      <form method="post" action="<?= e(url('contact.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid cols-2">
          <div class="field">
            <label for="c-name">Your name *</label>
            <input class="input" id="c-name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="e-name"' : '' ?>>
            <?php if (isset($errors['name'])): ?><span class="field-error" id="e-name"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label for="c-phone">Phone</label>
            <input class="input" id="c-phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" autocomplete="tel" inputmode="tel">
          </div>
          <div class="field span-2">
            <label for="c-email">Email</label>
            <input class="input" id="c-email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="e-email"' : '' ?>>
            <?php if (isset($errors['email'])): ?><span class="field-error" id="e-email"><?= e($errors['email']) ?></span><?php else: ?><span class="hint">Give us an email or a phone number so we can reply.</span><?php endif; ?>
          </div>
          <div class="field">
            <label for="c-subject">Subject</label>
            <input class="input" id="c-subject" name="subject" value="<?= e($old['subject']) ?>">
          </div>
          <div class="field">
            <label for="c-branch">Store</label>
            <select class="select" id="c-branch" name="branch">
              <option value="">— Any / not sure —</option>
              <?php foreach (branches() as $b): ?>
                <option value="<?= e($b['slug']) ?>" <?= $old['branch'] === $b['slug'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field span-2">
            <label for="c-message">Message *</label>
            <textarea class="textarea" id="c-message" name="message" required <?= isset($errors['message']) ? 'aria-invalid="true" aria-describedby="e-message"' : '' ?>><?= e($old['message']) ?></textarea>
            <?php if (isset($errors['message'])): ?><span class="field-error" id="e-message"><?= e($errors['message']) ?></span><?php endif; ?>
          </div>
          <div class="span-2"><button class="btn btn-primary" type="submit"><?= icon('mail', 'icon icon-sm') ?> Send message</button></div>
        </div>
      </form>
    </div>

    <aside class="contact-aside">
      <div class="contact-tiles">
        <?php foreach ($phones as $i => $p): ?>
          <a class="contact-tile" href="<?= e(phone_href($p)) ?>"><span class="tile-icon tint-green"><?= icon('phone') ?></span><span><?= e($p) ?><small><?= $i === 0 ? 'Call us — we\'re here to help' : 'Customer care line' ?></small></span></a>
        <?php endforeach; ?>
        <?php if (config('store.whatsapp')): ?>
          <a class="contact-tile" href="https://wa.me/<?= e(config('store.whatsapp')) ?>" target="_blank" rel="noopener"><span class="tile-icon tint-mint"><?= icon('message') ?></span><span>WhatsApp<small>Chat with our team</small></span></a>
        <?php endif; ?>
        <?php if (config('store.email')): ?>
          <a class="contact-tile" href="mailto:<?= e(config('store.email')) ?>"><span class="tile-icon tint-sky"><?= icon('mail') ?></span><span><?= e(config('store.email')) ?><small>Email us any time</small></span></a>
        <?php endif; ?>
      </div>

      <div class="qr-grid">
        <div class="qr-card">
          <?php if ($qrFeedback): ?><img src="<?= e(url($qrFeedback)) ?>" alt="QR code to leave feedback" width="300" height="300"><?php else: ?><div class="qr-placeholder"><?= icon('qr', 'icon icon-lg') ?></div><?php endif; ?>
          <strong>Scan to leave feedback</strong>
        </div>
        <div class="qr-card">
          <?php if ($qrComplaint): ?><img src="<?= e(url($qrComplaint)) ?>" alt="QR code to leave a complaint" width="300" height="300"><?php else: ?><div class="qr-placeholder"><?= icon('qr', 'icon icon-lg') ?></div><?php endif; ?>
          <strong>Scan to leave a complaint</strong>
        </div>
      </div>
    </aside>
  </div>
</section>

<?php page_end();
