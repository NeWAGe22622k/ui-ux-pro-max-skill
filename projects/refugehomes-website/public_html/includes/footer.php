<?php
$company = setting('company_name', 'Refugehomes Ltd');
$phone = setting('phone');
$email = setting('email');
$socials = array_filter([
    'Facebook'  => setting('facebook'),
    'Instagram' => setting('instagram'),
    'LinkedIn'  => setting('linkedin'),
]);
?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?= e(url('assets/img/logo-light.png')) ?>" alt="<?= e($company) ?>" width="406" height="155" loading="lazy">
      <p>Property management, lettings and home renovation. <?= e(setting('tagline', 'Built on trust')) ?>.</p>
    </div>
    <div>
      <h2 class="footer-title">Explore</h2>
      <ul class="footer-links">
        <li><a href="<?= e(url('about')) ?>">About us</a></li>
        <li><a href="<?= e(url('services')) ?>">Services</a></li>
        <li><a href="<?= e(url('properties')) ?>">Our properties</a></li>
        <li><a href="<?= e(url('rentals')) ?>">Rentals</a></li>
        <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
      </ul>
    </div>
    <div>
      <h2 class="footer-title">Get in touch</h2>
      <ul class="footer-links">
        <?php if ($phone): ?><li><a href="<?= e(tel_href($phone)) ?>"><?= icon('phone', 16) ?> <?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($email): ?><li><a href="mailto:<?= e($email) ?>"><?= icon('mail', 16) ?> <?= e($email) ?></a></li><?php endif; ?>
        <?php if (setting('address')): ?><li class="footer-address"><?= icon('pin', 16) ?> <span><?= nl2br(e(setting('address'))) ?></span></li><?php endif; ?>
      </ul>
      <?php if ($socials): ?>
        <ul class="footer-social">
          <?php foreach ($socials as $label => $href): ?>
            <li><a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <div class="container footer-legal">
    <p>&copy; <?= date('Y') ?> <?= e($company) ?>. All rights reserved.<?php if (setting('company_number')): ?> Registered company no. <?= e(setting('company_number')) ?>.<?php endif; ?></p>
  </div>
</footer>
</body>
</html>
