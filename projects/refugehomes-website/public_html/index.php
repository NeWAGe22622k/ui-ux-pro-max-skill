<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cards.php';

$page_key = 'home';
$page_title = 'Property management, lettings and renovation';
$page_description = 'Refugehomes Ltd manages, lets and renovates residential property. Browse homes to rent and see our renovation projects.';

$managed = listings('managed');
$flips = listings('flips');
$rentals = listings('rentals');
$featured_rentals = array_slice(array_values(array_filter($rentals, fn($r) => !empty($r['featured']))), 0, 3) ?: array_slice($rentals, 0, 3);
$showcase = $flips[0] ?? null;

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <p class="eyebrow">Property management &middot; Lettings &middot; Renovation</p>
      <h1><?= e(setting('hero_heading', 'Homes built on trust.')) ?></h1>
      <p class="lead"><?= e(setting('hero_subheading')) ?></p>
      <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('rentals')) ?>">View homes to rent <?= icon('arrow') ?></a>
        <a class="btn btn-secondary" href="<?= e(url('services')) ?>">Services for landlords</a>
      </div>
    </div>
    <div class="hero-media">
      <!-- PLACEHOLDER: replace assets/img/placeholders/hero.svg with a real photo (portrait, ~1200x1400) -->
      <img src="<?= e(url('assets/img/placeholders/hero.svg')) ?>" alt="A Refugehomes property" width="1200" height="1400" fetchpriority="high">
      <div class="hero-card">
        <span class="hero-card-num"><?= count($managed) ?></span>
        <span>properties under<br>our management</span>
      </div>
    </div>
  </div>
</section>

<section class="stats" aria-label="Refugehomes in numbers">
  <!-- PLACEHOLDER: the two bracketed figures below are examples, update them in index.php -->
  <div class="container stats-grid">
    <div><span class="stat-num"><?= count($managed) ?></span><span class="stat-label">Homes managed</span></div>
    <div><span class="stat-num"><?= count($flips) ?></span><span class="stat-label">Renovations completed</span></div>
    <div><span class="stat-num">[X]+</span><span class="stat-label">Years of experience</span></div>
    <div><span class="stat-num">[X]%</span><span class="stat-label">Average occupancy</span></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow">What we do</p>
      <h2>One partner for your property</h2>
      <p class="section-intro">From finding the right tenant to bringing a tired house back to life, we take care of the detail.</p>
    </div>
    <div class="feature-grid">
      <article class="feature">
        <span class="feature-icon"><?= icon('key', 22) ?></span>
        <h3>Property management</h3>
        <p>Rent collection, maintenance, inspections and compliance, handled for you.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('users', 22) ?></span>
        <h3>Lettings &amp; tenant finding</h3>
        <p>Marketing, viewings, referencing and move-in, with tenants matched carefully to each home.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('wrench', 22) ?></span>
        <h3>Renovation &amp; refurbishment</h3>
        <p>We buy, renovate and improve homes, raising the standard of housing in our area.</p>
      </article>
    </div>
    <p class="section-link"><a class="link-arrow" href="<?= e(url('services')) ?>">All services <?= icon('arrow', 16) ?></a></p>
  </div>
</section>

<?php if ($featured_rentals): ?>
<section class="section section-alt">
  <div class="container">
    <div class="section-head section-head-row">
      <div>
        <p class="eyebrow">To let</p>
        <h2>Homes available now</h2>
      </div>
      <a class="link-arrow" href="<?= e(url('rentals')) ?>">All rentals <?= icon('arrow', 16) ?></a>
    </div>
    <div class="card-grid">
      <?php foreach ($featured_rentals as $r) rental_card($r); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($showcase && !empty($showcase['before'][0]) && !empty($showcase['after'][0])): ?>
<section class="section">
  <div class="container split">
    <div class="compare compare-inline" data-compare-inline>
      <img src="<?= e(img($showcase['before'][0])) ?>" alt="<?= e($showcase['title']) ?>, before renovation" loading="lazy">
      <div class="compare-after" data-compare-after-wrap><img src="<?= e(img($showcase['after'][0])) ?>" alt="<?= e($showcase['title']) ?>, after renovation" loading="lazy"></div>
      <span class="tag tag-before compare-tag-l">Before</span>
      <span class="tag tag-after compare-tag-r">After</span>
      <div class="compare-handle" data-compare-handle aria-hidden="true"></div>
      <input type="range" min="0" max="100" value="50" class="compare-range" data-compare-range aria-label="Drag to compare before and after">
    </div>
    <div>
      <p class="eyebrow">Renovation projects</p>
      <h2>From tired to transformed</h2>
      <p><?= e($showcase['summary'] ?? '') ?></p>
      <p class="muted">Drag the slider to compare. Every project is finished to a standard we would be happy to live in ourselves.</p>
      <a class="btn btn-secondary" href="<?= e(url('properties#renovations')) ?>">See all projects <?= icon('arrow') ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section-alt">
  <div class="container">
    <!-- PLACEHOLDER: replace with a real client testimonial -->
    <figure class="testimonial">
      <blockquote>&ldquo;[Placeholder testimonial] Refugehomes have managed our property for three years. Communication is excellent and we never have to chase anything.&rdquo;</blockquote>
      <figcaption><strong>[Client name]</strong> &middot; Landlord, [City]</figcaption>
    </figure>
  </div>
</section>

<section class="cta-band">
  <div class="container cta-inner">
    <div>
      <h2>Own a property? Let&rsquo;s talk.</h2>
      <p>Tell us about your property and we&rsquo;ll explain how we can help, with no obligation.</p>
    </div>
    <a class="btn btn-light" href="<?= e(url('contact')) ?>">Get in touch <?= icon('arrow') ?></a>
  </div>
</section>

<script type="application/json" data-collection="rentals"><?= modal_payload($featured_rentals, 'rentals') ?></script>
<?php listing_modal(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
