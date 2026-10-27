<?php
require __DIR__ . '/includes/lib.php';
require __DIR__ . '/includes/cards.php';

$active = 'home';
$title = '';
$withListings = true;
$s = settings();
$featured = array_slice(published('properties'), 0, 3);

// Numbers are edited in Admin → Site details → Homepage numbers
$heroStats = [];
for ($i = 1; $i <= 4; $i++) {
    if (($s["hero_stat{$i}_value"] ?? '') !== '') $heroStats[] = [$s["hero_stat{$i}_value"], $s["hero_stat{$i}_label"] ?? ''];
}
$impact = [];
for ($i = 1; $i <= 5; $i++) {
    if (($s["impact{$i}_value"] ?? '') !== '') $impact[] = [$s["impact{$i}_value"], $s["impact{$i}_title"] ?? '', $s["impact{$i}_text"] ?? ''];
}

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
        <p class="lead">We work with landlords and investors to create and manage quality homes that meet residents' needs while supporting their property and investment goals.</p>
        <div class="btn-row">
          <a class="btn btn-primary" href="contact.php?enquiry=Partnering%20with%20Refuge%20Homes">Partner with us <?= icon('arrow-right', 18) ?></a>
          <a class="btn btn-outline" href="rentals.php">Find your next home</a>
        </div>
      </div>
    </div>
  </div>
  <div class="container">
    <figure class="hero-media reveal">
      <img <?= img_attrs('assets/img/photos/hero.jpg', '(max-width: 1340px) 100vw, 1240px') ?> alt="A row of white stucco terraced houses on a tree-lined street" width="1500" height="844" fetchpriority="high">
    </figure>
    <?php if ($heroStats): ?>
      <ul class="hero-strip hero-stats" aria-label="Refuge Homes in numbers">
        <?php foreach ($heroStats as $i => [$value, $label]): ?>
          <li>
            <span class="stat-num">0<?= $i + 1 ?></span>
            <strong class="stat-value"><?= h($value) ?></strong>
            <span class="stat-label"><?= h($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<!-- Why we exist -->
<section class="section">
  <div class="container split">
    <div class="reveal">
      <p class="eyebrow">Why we exist</p>
      <h2 class="h2">More Than Properties.<br><em>It's About People.</em></h2>
    </div>
    <div class="reveal">
      <div class="prose">
        <p>At Refuge Homes, we believe a property is more than a building or an investment. Behind every property are people with different needs, aspirations and expectations — whether it's a resident looking for a comfortable place to call home, a landlord seeking peace of mind, or an investor looking to create lasting value.</p>
        <p>As homeownership becomes increasingly out of reach for many, we believe that renting should not mean compromising on the quality and comfort of the place you call home.</p>
        <p>Our purpose is to help bridge that gap by creating and providing quality rental homes through trusted landlord partnerships, responsible property management and thoughtful investment.</p>
        <p class="prose-strong">We believe that when we understand the people behind every property, we can create better homes, stronger partnerships and lasting value for everyone involved.</p>
      </div>
      <a class="link-arrow" href="about.php">Discover Our Story <?= icon('arrow-right', 16) ?></a>
    </div>
  </div>
</section>

<!-- Who we serve -->
<section class="section section-white">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Who we serve</p>
        <h2 class="h2 h2-wide">Different priorities.<br><span class="accent">One thoughtful approach.</span></h2>
        <p class="lead section-sub">Every property has people behind it. We listen, understand and tailor our approach.</p>
      </div>
      <a class="link-arrow" href="services.php">Our approach <?= icon('arrow-right', 16) ?></a>
    </div>
    <div class="service-list">
      <?php
      $serve = [
          ['rentals.php', 'Residents', 'Quality homes, respectful management and a rental experience built around comfort and care.'],
          ['services.php#guaranteed-rent', 'Landlords', 'Predictable guaranteed income and responsible management shaped around your property goals.'],
          ['services.php#investment', 'Investors', 'Thoughtful opportunities to acquire, improve and create lasting value through residential property.'],
      ];
      foreach ($serve as $i => [$href, $name, $line]): ?>
        <a class="service-row reveal" href="<?= $href ?>">
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
<!-- What we've achieved -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">What we've achieved</p>
        <h2 class="h2 h2-wide">Real homes. Real people.<br>Lasting impact.</h2>
        <p class="lead section-sub">Selected properties, partnerships and improvements that reflect the homes we create and the value we build.</p>
      </div>
      <a class="link-arrow" href="properties.php">View full portfolio <?= icon('arrow-right', 16) ?></a>
    </div>
    <div class="grid grid-3">
      <?php foreach ($featured as $p) property_card($p, true); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($impact): ?>
<!-- Our impact -->
<section class="section section-sand">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Our impact</p>
        <h2 class="h2 h2-line">The numbers behind our work.</h2>
      </div>
      <p class="lead impact-intro">A snapshot of the homes, partnerships and investment activity that reflect our growth and impact.</p>
    </div>
    <ol class="impact">
      <?php foreach ($impact as $i => [$value, $t, $d]): ?>
        <li class="reveal">
          <span class="impact-num">0<?= $i + 1 ?></span>
          <strong class="impact-value"><?= h($value) ?></strong>
          <h3><?= h($t) ?></h3>
          <?php if ($d !== ''): ?><p><?= h($d) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<!-- Client stories -->
<section class="section section-white">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Client stories</p>
        <h2 class="h2 h2-wide">What people say about<br>working with Refuge Homes.</h2>
      </div>
    </div>
    <div class="quotes">
      <figure class="quote reveal">
        <blockquote>“The home was clean, comfortable and well managed. It genuinely felt like somewhere I could settle.”</blockquote>
        <figcaption><strong>Resident</strong></figcaption>
      </figure>
      <figure class="quote reveal">
        <blockquote>“Refuge Homes made the process straightforward and gave me confidence that my property was being looked after properly.”</blockquote>
        <figcaption><strong>Landlord</strong></figcaption>
      </figure>
      <figure class="quote reveal">
        <blockquote>“What stood out was the balance between commercial discipline and a genuine commitment to creating better homes.”</blockquote>
        <figcaption><strong>Investor</strong></figcaption>
      </figure>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/cta.php'; ?>

<?php listing_modals(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
