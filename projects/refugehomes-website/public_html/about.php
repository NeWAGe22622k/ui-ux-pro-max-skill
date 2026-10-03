<?php
require __DIR__ . '/includes/bootstrap.php';

$page_key = 'about';
$page_title = 'About us';
$page_description = 'Refugehomes Ltd is a property company built on trust, managing, letting and renovating homes.';

// PLACEHOLDER: replace names, roles and photos with your real team (photos ~1000x1000).
$team = [
    ['name' => '[Full name]', 'role' => 'Founder & Director', 'photo' => 'assets/img/placeholders/team-1.svg'],
    ['name' => '[Full name]', 'role' => 'Property Manager', 'photo' => 'assets/img/placeholders/team-2.svg'],
    ['name' => '[Full name]', 'role' => 'Projects Lead', 'photo' => 'assets/img/placeholders/team-3.svg'],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">About us</p>
    <h1>A property company built on trust</h1>
    <p class="lead">We look after homes as if they were our own, for the landlords who own them and the people who live in them.</p>
  </div>
</section>

<section class="section">
  <div class="container split split-wide-text">
    <div class="prose">
      <!-- PLACEHOLDER: replace this story with your own -->
      <h2>Our story</h2>
      <p>[Placeholder] Refugehomes Ltd was founded in [year] with a simple idea: property should be a place of refuge. Good homes, looked after properly, make a real difference to the people who live in them.</p>
      <p>We started by renovating and letting our own properties. Today we manage homes for private landlords across [area], letting them to carefully referenced tenants and keeping them in excellent condition.</p>
      <p>Alongside management we buy and renovate run-down properties, bringing empty and neglected homes back into use and raising the quality of housing in our community.</p>
    </div>
    <div class="media-frame">
      <img src="<?= e(url('assets/img/placeholders/about.svg')) ?>" alt="The Refugehomes team" width="1200" height="1000" loading="lazy">
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow">Our values</p>
      <h2>What you can expect from us</h2>
    </div>
    <div class="feature-grid">
      <article class="feature">
        <span class="feature-icon"><?= icon('shield', 22) ?></span>
        <h3>Trust &amp; transparency</h3>
        <p>Clear fees, honest advice and regular reporting. No surprises.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('home', 22) ?></span>
        <h3>Quality homes</h3>
        <p>Every property we manage or renovate meets a standard we would be happy to live in.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('clock', 22) ?></span>
        <h3>Responsive service</h3>
        <p>Repairs and queries are handled quickly, by people who know your property.</p>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow">Our team</p>
      <h2>The people behind Refugehomes</h2>
    </div>
    <div class="team-grid">
      <?php foreach ($team as $m): ?>
        <figure class="team-member">
          <img src="<?= e(url($m['photo'])) ?>" alt="<?= e($m['name']) ?>" width="1000" height="1000" loading="lazy">
          <figcaption><strong><?= e($m['name']) ?></strong><span><?= e($m['role']) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="container cta-inner">
    <div>
      <h2>Work with us</h2>
      <p>Whether you&rsquo;re a landlord, a tenant or selling a property, we&rsquo;d like to hear from you.</p>
    </div>
    <a class="btn btn-light" href="<?= e(url('contact')) ?>">Contact us <?= icon('arrow') ?></a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
