<?php
require __DIR__ . '/includes/lib.php';

session_start();
$s = settings();
$active = 'contact';
$title = 'Contact';
$desc = 'Contact Refugehomes Ltd in Harlow. Call, email or send us a message about letting, selling, renting or investing.';

$roles = ['Landlord', 'Tenant / looking to rent', 'Homeowner selling', 'Investor', 'Other'];
$values = ['name' => '', 'email' => '', 'phone' => '', 'role' => '', 'message' => ''];
$errors = [];
$sent = false;

if (!empty($_GET['enquiry'])) {
    $values['message'] = 'Enquiry: ' . mb_substr(trim((string) $_GET['enquiry']), 0, 160) . "\n\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $k => $_) {
        $values[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $tooFast = time() - (int) ($_POST['t'] ?? 0) < 3;              // bots submit instantly
    $honeypot = !empty($_POST['website']);                          // hidden field humans never fill
    $recent = time() - (int) ($_SESSION['last_contact'] ?? 0) < 30; // one message per 30s

    if ($values['name'] === '' || mb_strlen($values['name']) > 100) $errors['name'] = 'Please enter your name.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    if (mb_strlen($values['phone']) > 30) $errors['phone'] = 'Please check your phone number.';
    if (!in_array($values['role'], $roles, true)) $values['role'] = 'Other';
    if (mb_strlen($values['message']) < 5 || mb_strlen($values['message']) > 5000) $errors['message'] = 'Please enter a message.';

    if ($honeypot || $tooFast) {
        $sent = true; // pretend success for bots
    } elseif ($recent) {
        $errors['form'] = 'You have just sent a message. Please wait a moment before sending another.';
    } elseif (!$errors) {
        $clean = fn($v) => str_replace(["\r", "\n"], ' ', $v);
        $subject = 'Website enquiry from ' . $clean($values['name']) . ' (' . $values['role'] . ')';
        $body = "New enquiry from the Refugehomes website\n\n"
            . 'Name:  ' . $values['name'] . "\n"
            . 'Email: ' . $values['email'] . "\n"
            . 'Phone: ' . ($values['phone'] ?: '-') . "\n"
            . 'I am:  ' . $values['role'] . "\n\n"
            . $values['message'] . "\n";
        $headers = [
            'From: ' . $s['company'] . ' Website <' . $s['email'] . '>',
            'Reply-To: ' . $clean($values['name']) . ' <' . $values['email'] . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $ok = @mail($s['contact_to'], '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
        if (!$ok) {
            // Keep a copy so no enquiry is lost if the mail server is unavailable.
            @file_put_contents(DATA_DIR . '/unsent-enquiries.log', date('c') . "\n" . $body . "\n-----\n", FILE_APPEND | LOCK_EX);
        }
        $_SESSION['last_contact'] = time();
        $sent = true;
        $values = array_map(fn() => '', $values);
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container page-hero-split">
    <div class="reveal">
      <p class="eyebrow">Contact</p>
      <h1 class="h1">Let's talk about your <em>property.</em></h1>
    </div>
    <p class="lead reveal">Whether you are letting, selling, renting or investing, we would welcome the chance to help. Send a message and we will reply within one working day.</p>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container contact-grid">
    <div class="reveal">
      <h2 class="h3">Get in touch</h2>
      <ul class="contact-list">
        <li><?= icon('phone', 20) ?><div><strong>Phone</strong><a href="<?= h(tel($s['phone'])) ?>"><?= h($s['phone']) ?></a><?php if ($s['phone2']): ?><br><a href="<?= h(tel($s['phone2'])) ?>"><?= h($s['phone2']) ?></a><?php endif; ?></div></li>
        <li><?= icon('mail', 20) ?><div><strong>Email</strong><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a></div></li>
        <li><?= icon('map-pin', 20) ?><div><strong>Office</strong><?= h($s['address']) ?></div></li>
        <li><?= icon('clock', 20) ?><div><strong>Opening hours</strong><?= h($s['hours']) ?></div></li>
        <?php if (!empty($s['whatsapp'])): ?>
          <li><?= icon('whatsapp', 20) ?><div><strong>WhatsApp</strong><a href="https://wa.me/<?= h(preg_replace('/\D/', '', $s['whatsapp'])) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></div></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="form-card reveal" id="form">
      <?php if ($sent): ?>
        <div class="alert" role="status"><strong>Thank you — your message has been sent.</strong> We will be in touch within one working day.</div>
      <?php elseif ($errors): ?>
        <div class="alert alert-error" role="alert"><?= h($errors['form'] ?? 'Please check the highlighted fields and try again.') ?></div>
      <?php endif; ?>

      <form method="post" action="contact.php#form" novalidate>
        <input type="hidden" name="t" value="<?= time() ?>">
        <div class="field-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid">
          <div class="field">
            <label for="f-name">Full name</label>
            <input id="f-name" name="name" type="text" autocomplete="name" required value="<?= h($values['name']) ?>"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="e-name"' : '' ?>>
            <?php if (isset($errors['name'])): ?><p class="form-note" id="e-name" style="color:#b42318;margin-top:6px"><?= h($errors['name']) ?></p><?php endif; ?>
          </div>
          <div class="field">
            <label for="f-email">Email</label>
            <input id="f-email" name="email" type="email" autocomplete="email" required value="<?= h($values['email']) ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="e-email"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="form-note" id="e-email" style="color:#b42318;margin-top:6px"><?= h($errors['email']) ?></p><?php endif; ?>
          </div>
          <div class="field">
            <label for="f-phone">Phone <span class="opt">(optional)</span></label>
            <input id="f-phone" name="phone" type="tel" autocomplete="tel" value="<?= h($values['phone']) ?>">
          </div>
          <div class="field">
            <label for="f-role">I am a…</label>
            <select id="f-role" name="role">
              <?php foreach ($roles as $r): ?>
                <option<?= $values['role'] === $r ? ' selected' : '' ?>><?= h($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field full">
            <label for="f-message">How can we help?</label>
            <textarea id="f-message" name="message" required<?= isset($errors['message']) ? ' aria-invalid="true" aria-describedby="e-message"' : '' ?>><?= h($values['message']) ?></textarea>
            <?php if (isset($errors['message'])): ?><p class="form-note" id="e-message" style="color:#b42318;margin-top:6px"><?= h($errors['message']) ?></p><?php endif; ?>
          </div>
          <div class="full">
            <button class="btn btn-primary" type="submit">Send message <?= icon('arrow-right', 18) ?></button>
            <p class="form-note">We only use your details to respond to your enquiry. See our <a href="legal.php#privacy">privacy notice</a>.</p>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="container map reveal">
    <iframe title="Map showing the Refugehomes office in Harlow" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
      src="https://www.google.com/maps?q=<?= rawurlencode($s['address']) ?>&amp;output=embed"></iframe>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
