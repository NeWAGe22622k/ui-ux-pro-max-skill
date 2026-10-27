<?php
require __DIR__ . '/includes/lib.php';

$s = settings();
$active = '';
$title = 'Privacy, terms & cookies';

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Legal</p>
    <h1 class="h1">Privacy, terms &amp; cookies</h1>
    <p class="lead">[PLACEHOLDER] These notices are a starting point only. Please have them reviewed and replaced with your own policies before launch.</p>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container legal">
    <h2 id="privacy">Privacy notice</h2>
    <p><?= h($s['company']) ?> (“we”, “us”) collects the information you choose to send us through this website — such as your name, email address, phone number and message — so that we can respond to your enquiry.</p>
    <ul>
      <li>We use your details only to reply to you and to provide the services you ask about.</li>
      <li>We do not sell or share your details with third parties for marketing.</li>
      <li>You can ask us to see, correct or delete the information we hold about you at any time by emailing <a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a>.</li>
    </ul>
    <p>[PLACEHOLDER] Add your ICO registration number, data retention period and lawful basis for processing.</p>

    <h2 id="terms">Terms of use</h2>
    <p>The information on this website is provided for general guidance and does not form part of any offer or contract. Property details, prices and availability may change without notice. Photos may be illustrative.</p>
    <p>[PLACEHOLDER] Add your company registration number, registered office and any redress scheme or client money protection memberships.</p>

    <h2 id="cookies">Cookies</h2>
    <p>This website does not use advertising or tracking cookies. A single essential cookie may be set when you send the contact form, to prevent spam. Embedded Google Maps may set its own cookies, governed by Google's privacy policy.</p>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
