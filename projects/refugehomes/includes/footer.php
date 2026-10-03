<?php $s = settings(); ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <img src="assets/img/logo-white.png" alt="<?= h($s['company']) ?>" width="201" height="76" loading="lazy">
        <p>A UK property company offering guaranteed rent, lettings, sales and refurbishment for homeowners, landlords and investors.</p>
        <?php if ($links = social_links()): ?>
          <ul class="social" aria-label="Social media">
            <?php foreach ($links as $l): ?>
              <li><a href="<?= h($l['url']) ?>" target="_blank" rel="noopener" aria-label="<?= h($l['label']) ?>"><?= icon($l['icon'], 18) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="footer-col">
        <h2 class="footer-heading">Explore</h2>
        <ul>
          <li><a href="about.php">About us</a></li>
          <li><a href="services.php">Services</a></li>
          <li><a href="properties.php">Our properties</a></li>
          <li><a href="rentals.php">Homes to rent</a></li>
          <li><a href="contact.php">Contact</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h2 class="footer-heading">Services</h2>
        <ul>
          <li><a href="services.php#guaranteed-rent">Guaranteed rent</a></li>
          <li><a href="services.php#lettings">Lettings &amp; rentals</a></li>
          <li><a href="services.php#sales">Residential sales</a></li>
          <li><a href="services.php#investment">Investment &amp; refurbishment</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h2 class="footer-heading">Get in touch</h2>
        <ul class="footer-contact">
          <li><?= icon('map-pin', 16) ?><span><?= h($s['address']) ?></span></li>
          <li><?= icon('phone', 16) ?><span><a href="<?= h(tel($s['phone'])) ?>"><?= h($s['phone']) ?></a><?php if (!empty($s['phone2'])): ?><br><a href="<?= h(tel($s['phone2'])) ?>"><?= h($s['phone2']) ?></a><?php endif; ?></span></li>
          <li><?= icon('mail', 16) ?><span><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a></span></li>
          <li><?= icon('clock', 16) ?><span><?= h($s['hours']) ?></span></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <span data-year><?= date('Y') ?></span> <?= h($s['company']) ?>. All rights reserved.</p>
      <ul>
        <li><a href="legal.php#privacy">Privacy</a></li>
        <li><a href="legal.php#terms">Terms</a></li>
        <li><a href="legal.php#cookies">Cookies</a></li>
      </ul>
    </div>
  </div>
</footer>

<script src="assets/js/main.js?v=1" defer></script>
<?php if (!empty($withListings)): ?>
<script src="assets/js/listings.js?v=1" defer></script>
<?php endif; ?>
</body>
</html>
