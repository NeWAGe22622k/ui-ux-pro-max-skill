<?php
require __DIR__ . '/includes/lib.php';
require __DIR__ . '/includes/cards.php';

$active = 'home';
$title = '';
$withListings = true;
$featured = array_slice(published('properties'), 0, 3);
$rentals = array_slice(array_values(array_filter(published('rentals'), fn($r) => ($r['status'] ?? '') !== 'let')), 0, 3);

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div class="hero-grid">
      <div class="hero-copy reveal">
        <p class="eyebrow">Refugehomes Ltd &middot; Built on trust</p>
        <h1 class="display">Homes built<br>on <em>trust.</em></h1>
      </div>
      <div class="hero-aside reveal">
        <p class="lead">Guaranteed rent, lettings, sales and refurbishment across the UK, delivered with honest advice and careful execution.</p>
        <div class="btn-row">
          <a class="btn btn-primary" href="rentals.php">View available homes <?= icon('arrow-right', 18) ?></a>
          <a class="btn btn-outline" href="contact.php">Talk to us</a>
        </div>
      </div>
    </div>
  </div>
  <div class="container">
    <figure class="hero-media reveal">
      <img src="assets/img/placeholder/hero.jpg" alt="A Refugehomes property" width="2400" height="1400" fetchpriority="high">
    </figure>
    <ul class="hero-strip" aria-label="What we do">
      <li><strong>01</strong> Guaranteed rent</li>
      <li><strong>02</strong> Lettings &amp; rentals</li>
      <li><strong>03</strong> Residential sales</li>
      <li><strong>04</strong> Investment &amp; refurbishment</li>
    </ul>
  </div>
</section>

<section class="section">
  <div class="container split">
    <div class="reveal">
      <p class="eyebrow">Who we are</p>
      <h2 class="h2">A modern property company for homeowners, landlords and investors.</h2>
    </div>
    <div class="reveal">
      <div class="prose lead">
        <p>Property decisions are among the most significant financial choices people make. We handle them with the care they deserve: clear advice, realistic numbers and steady communication from the first conversation to the last.</p>
        <p>Whether you are letting a home, selling, or growing a portfolio, you will always know where things stand and what happens next.</p>
      </div>
      <a class="link-arrow" href="about.php">More about Refugehomes <?= icon('arrow-right', 16) ?></a>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Services</p>
        <h2 class="h2">Everything your property needs, under one roof.</h2>
      </div>
      <a class="link-arrow" href="services.php">All services <?= icon('arrow-right', 16) ?></a>
    </div>
    <div class="service-list">
      <?php
      $services = [
          ['guaranteed-rent', 'Guaranteed rent', 'A fixed monthly rent for landlords, paid even when the property is empty, with no day-to-day work.'],
          ['lettings', 'Lettings &amp; rentals', 'Quality homes for tenants, and well-matched, referenced tenants for landlords.'],
          ['sales', 'Residential sales', 'Accurate pricing, strong marketing and skilled negotiation, with direct-purchase options.'],
          ['investment', 'Investment &amp; refurbishment', 'Sourcing, analysis and refurbishment that turns the right property into long-term value.'],
      ];
      foreach ($services as $i => [$anchor, $name, $line]): ?>
        <a class="service-row reveal" href="services.php#<?= $anchor ?>">
          <span class="service-num">0<?= $i + 1 ?></span>
          <h3 class="h3"><?= $name ?></h3>
          <p><?= $line ?></p>
          <span class="icon-btn" aria-hidden="true"><?= icon('arrow-up-right', 18) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($featured): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Our properties</p>
        <h2 class="h2">Recent projects &amp; managed homes.</h2>
      </div>
      <a class="link-arrow" href="properties.php">View all properties <?= icon('arrow-right', 16) ?></a>
    </div>
    <div class="grid grid-3">
      <?php foreach ($featured as $p) property_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($rentals): ?>
<section class="section section-sand">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">To rent</p>
        <h2 class="h2">Homes available now.</h2>
      </div>
      <a class="link-arrow" href="rentals.php">See all rentals <?= icon('arrow-right', 16) ?></a>
    </div>
    <div class="grid grid-3">
      <?php foreach ($rentals as $r) rental_card($r); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">How we work</p>
        <h2 class="h2">A clear process, start to finish.</h2>
      </div>
      <p class="lead">From the first call to aftercare, every step is planned, explained and handled by the same accountable team.</p>
    </div>
    <ol class="steps" style="list-style:none;margin:0;padding:0">
      <?php
      $steps = [
          ['Consultation', 'We listen first: your property, your priorities and what success looks like.'],
          ['Strategy', 'A tailored plan grounded in market insight and built around your goals.'],
          ['Execution', 'We manage every detail carefully so the process runs smoothly.'],
          ['Updates', 'Clear, honest progress updates at every stage. No surprises.'],
          ['Aftercare', 'We finish properly and stay available for whatever comes next.'],
      ];
      foreach ($steps as $i => [$t, $d]): ?>
        <li class="step reveal">
          <div class="step-num">0<?= $i + 1 ?></div>
          <h3><?= $t ?></h3>
          <p><?= $d ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Client stories</p>
        <h2 class="h2">What our clients say.</h2>
      </div>
    </div>
    <div class="quotes">
      <figure class="quote reveal">
        <blockquote>“Selling our home felt stressful at first, but we were guided through every step. Clear, calm and handled with care from start to finish.”</blockquote>
        <figcaption><strong>James R.</strong></figcaption>
      </figure>
      <figure class="quote reveal">
        <blockquote>“We appreciated the honesty more than anything. No pressure, no overpromising — just clear advice and good communication throughout.”</blockquote>
        <figcaption><strong>Sarah L.</strong></figcaption>
      </figure>
      <figure class="quote reveal">
        <blockquote>“Everything was explained clearly and realistically. I felt confident making decisions because I understood both the numbers and the risks.”</blockquote>
        <figcaption><strong>Michael T.</strong></figcaption>
      </figure>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/cta.php'; ?>

<?php listing_modals(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
