<?php
require __DIR__ . '/includes/bootstrap.php';

$page_key = 'contact';
$page_title = 'Contact us';
$page_description = 'Contact Refugehomes Ltd about renting a home, property management or selling your property.';

$types = [
    'renting'  => 'I want to rent a home',
    'landlord' => 'I\'m a landlord / property management',
    'selling'  => 'I want to sell a property',
    'other'    => 'Something else',
];

start_session();
$errors = [];
$sent = isset($_GET['sent']);
$old = [
    'name'     => '',
    'email'    => '',
    'phone'    => '',
    'type'     => array_key_exists($_GET['type'] ?? '', $types) ? $_GET['type'] : '',
    'property' => mb_substr(trim((string) ($_GET['property'] ?? '')), 0, 150),
    'message'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try sending the form again.';
    }
    if (($_POST['website'] ?? '') !== '') {
        // Honeypot field filled in: almost certainly a bot. Pretend it worked.
        header('Location: ' . url('contact?sent=1'), true, 303);
        exit;
    }
    if (time() - ($_SESSION['last_enquiry'] ?? 0) < 30) {
        $errors['form'] = 'Please wait a moment before sending another message.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Please enter your name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address, like name@example.com.';
    }
    if (mb_strlen($old['phone']) > 40) {
        $errors['phone'] = 'That phone number looks too long.';
    }
    if (!array_key_exists($old['type'], $types)) {
        $errors['type'] = 'Please choose what your enquiry is about.';
    }
    if (mb_strlen($old['message']) < 10 || mb_strlen($old['message']) > 5000) {
        $errors['message'] = 'Please write a short message (at least 10 characters).';
    }
    $old['property'] = mb_substr($old['property'], 0, 150);

    if (!$errors) {
        $enquiry = [
            'id'       => bin2hex(random_bytes(6)),
            'date'     => date('c'),
            'name'     => $old['name'],
            'email'    => $old['email'],
            'phone'    => $old['phone'],
            'type'     => $types[$old['type']],
            'property' => $old['property'],
            'message'  => $old['message'],
        ];
        // Every enquiry is kept in the admin panel, even if email delivery fails.
        $file = PRIVATE_DIR . '/enquiries.json';
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        array_unshift($all, $enquiry);
        save_json('enquiries', array_slice($all, 0, 1000), PRIVATE_DIR);

        $to = setting('enquiry_email', setting('email'));
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $host = preg_replace('/^www\./', '', preg_replace('/[^a-z0-9.\-]/', '', strtolower($_SERVER['HTTP_HOST'] ?? 'localhost')));
            $subject = 'Website enquiry: ' . $enquiry['type'] . ' - ' . preg_replace('/[\r\n]+/', ' ', $enquiry['name']);
            $body = "New enquiry from the website\n\n"
                . "Name: {$enquiry['name']}\nEmail: {$enquiry['email']}\nPhone: {$enquiry['phone']}\n"
                . "Enquiry: {$enquiry['type']}\n" . ($enquiry['property'] ? "Property: {$enquiry['property']}\n" : '')
                . "\nMessage:\n{$enquiry['message']}\n";
            $headers = [
                'From'         => setting('company_name', 'Refugehomes') . " Website <no-reply@$host>",
                'Reply-To'     => $enquiry['email'],
                'Content-Type' => 'text/plain; charset=UTF-8',
            ];
            @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        }
        $_SESSION['last_enquiry'] = time();
        header('Location: ' . url('contact?sent=1'), true, 303);
        exit;
    }
}

$phone = setting('phone');
$email = setting('email');
$whatsapp = preg_replace('/[^0-9]/', '', setting('whatsapp'));

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Contact</p>
    <h1>Get in touch</h1>
    <p class="lead">Questions about a rental, our services or selling your property? Send us a message and we&rsquo;ll reply within one working day.</p>
  </div>
</section>

<section class="section section-tight">
  <div class="container contact-grid">
    <div class="contact-form-wrap">
      <?php if ($sent): ?>
        <div class="notice notice-success" role="status" tabindex="-1" data-autofocus>
          <?= icon('check', 20) ?>
          <div><strong>Thank you, your message has been sent.</strong><br>We&rsquo;ll be in touch shortly.</div>
        </div>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="notice notice-error" role="alert" tabindex="-1" data-autofocus>
            <strong><?= e($errors['form'] ?? 'Please check the highlighted fields.') ?></strong>
          </div>
        <?php endif; ?>
        <form class="form" method="post" action="<?= e(url('contact')) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

          <div class="form-row">
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
              <label for="f-name">Full name</label>
              <input id="f-name" name="name" type="text" autocomplete="name" required maxlength="100" value="<?= e($old['name']) ?>"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="e-name"' : '' ?>>
              <?php if (isset($errors['name'])): ?><p class="field-error" id="e-name"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
              <label for="f-phone">Phone <span class="optional">(optional)</span></label>
              <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" value="<?= e($old['phone']) ?>"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="e-phone"' : '' ?>>
              <?php if (isset($errors['phone'])): ?><p class="field-error" id="e-phone"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
          </div>

          <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
            <label for="f-email">Email</label>
            <input id="f-email" name="email" type="email" autocomplete="email" required value="<?= e($old['email']) ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="e-email"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="field-error" id="e-email"><?= e($errors['email']) ?></p><?php endif; ?>
          </div>

          <div class="field<?= isset($errors['type']) ? ' has-error' : '' ?>">
            <label for="f-type">What is your enquiry about?</label>
            <select id="f-type" name="type" required<?= isset($errors['type']) ? ' aria-invalid="true" aria-describedby="e-type"' : '' ?>>
              <option value="">Please choose</option>
              <?php foreach ($types as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $old['type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['type'])): ?><p class="field-error" id="e-type"><?= e($errors['type']) ?></p><?php endif; ?>
          </div>

          <?php if ($old['property'] !== ''): ?>
            <div class="field">
              <label for="f-property">Property</label>
              <input id="f-property" name="property" type="text" maxlength="150" value="<?= e($old['property']) ?>">
            </div>
          <?php endif; ?>

          <div class="field<?= isset($errors['message']) ? ' has-error' : '' ?>">
            <label for="f-message">Message</label>
            <textarea id="f-message" name="message" rows="6" required maxlength="5000"<?= isset($errors['message']) ? ' aria-invalid="true" aria-describedby="e-message"' : '' ?>><?= e($old['message']) ?></textarea>
            <?php if (isset($errors['message'])): ?><p class="field-error" id="e-message"><?= e($errors['message']) ?></p><?php endif; ?>
          </div>

          <p class="form-note">We&rsquo;ll only use your details to respond to your enquiry.</p>
          <button class="btn btn-primary" type="submit" data-submit>Send message <?= icon('arrow') ?></button>
        </form>
      <?php endif; ?>
    </div>

    <aside class="contact-aside" aria-label="Contact details">
      <ul class="contact-list">
        <?php if ($phone): ?>
          <li><span class="feature-icon"><?= icon('phone', 20) ?></span><div><span class="label">Phone</span><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a></div></li>
        <?php endif; ?>
        <?php if ($whatsapp): ?>
          <li><span class="feature-icon"><?= icon('message', 20) ?></span><div><span class="label">WhatsApp</span><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></div></li>
        <?php endif; ?>
        <?php if ($email): ?>
          <li><span class="feature-icon"><?= icon('mail', 20) ?></span><div><span class="label">Email</span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div></li>
        <?php endif; ?>
        <?php if (setting('address')): ?>
          <li><span class="feature-icon"><?= icon('pin', 20) ?></span><div><span class="label">Office</span><span><?= nl2br(e(setting('address'))) ?></span></div></li>
        <?php endif; ?>
        <?php if (setting('office_hours')): ?>
          <li><span class="feature-icon"><?= icon('clock', 20) ?></span><div><span class="label">Opening hours</span><span><?= nl2br(e(setting('office_hours'))) ?></span></div></li>
        <?php endif; ?>
      </ul>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
