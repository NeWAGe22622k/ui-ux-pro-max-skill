<?php
require __DIR__ . '/includes/lib.php';

$active = 'about';
$title = 'About us';
$desc = 'Refugehomes Ltd is a UK property company built on trust: honest advice, informed strategy and measurable results for homeowners, landlords and investors.';

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container page-hero-split">
    <div class="reveal">
      <p class="eyebrow">About us</p>
      <h1 class="h1">Built on trust. Focused on <em>results.</em></h1>
    </div>
    <p class="lead reveal">Refugehomes brings a modern, disciplined approach to property: grounded in honest advice, informed strategy and outcomes you can measure.</p>
  </div>
</section>

<div class="container">
  <div class="banner reveal">
    <img src="assets/img/photos/about.jpg" alt="A bright meeting room with a wooden table and sash windows" width="2000" height="1125">
  </div>
</div>

<section class="section">
  <div class="container split">
    <div class="reveal">
      <p class="eyebrow">Our story</p>
      <h2 class="h2">Raising the standard in property sales, management and investment.</h2>
    </div>
    <div class="prose lead reveal">
      <p>Refugehomes was founded with a clear objective: to bring structure, transparency and disciplined thinking back into property services. In an industry often driven by speed and surface-level advice, we saw the need for a more considered approach.</p>
      <p>That principle still guides us. Whether we are helping a homeowner sell, a landlord find dependable management, or an investor assess an opportunity, we combine local market knowledge with practical experience — and we are measured by the outcomes we deliver, not the number of transactions we close.</p>
    </div>
  </div>
</section>

<section class="section section-white mission">
  <div class="container reveal">
    <p class="eyebrow">Our mission</p>
    <blockquote>To make property decisions <em>simpler and safer</em> for homeowners, landlords and investors across the UK.</blockquote>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Why choose us</p>
        <h2 class="h2">What clients can expect from us.</h2>
      </div>
      <p class="lead">Landlords trust us for guaranteed rent and dependable, hands-off management. Tenants choose us for quality homes in well-connected locations. Investors work with us for carefully assessed opportunities.</p>
    </div>
    <div class="tiles">
      <?php
      $values = [
          ['Trusted service', 'Transparency, reliability and follow-through. Clear communication and honest guidance build relationships that last.'],
          ['Industry expertise', 'Practical experience across sales, management and development. Our advice is based on real market conditions, not assumptions.'],
          ['A personal approach', 'No two clients or properties are the same. Your strategy is shaped around your goals and your situation, never a template.'],
          ['Market knowledge', 'We track local trends, pricing and demand closely, so every property is positioned well and every decision is informed.'],
      ];
      foreach ($values as $i => [$t, $d]): ?>
        <div class="tile reveal">
          <span class="tile-num">0<?= $i + 1 ?></span>
          <h3><?= h($t) ?></h3>
          <p><?= h($d) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Our founders</p>
        <h2 class="h2">The people behind Refugehomes.</h2>
      </div>
      <p class="lead">Our founders started Refugehomes to bring clarity, professionalism and stronger guidance to the property market. That vision still shapes how we work with every client.</p>
    </div>
    <div class="grid grid-3">
      <?php
      $team = [
          ['Abraham', 'Operations &amp; Systems', 'founder-1'],
          ['Ekene Okoye', 'Property Expert', 'founder-2'],
          ['Tochukwu Okoye', 'Investment Expert', 'founder-3'],
      ];
      foreach ($team as [$name, $role, $img]): ?>
        <article class="person reveal">
          <img src="assets/img/placeholder/<?= $img ?>.jpg" alt="<?= h($name) ?>" width="900" height="1100" loading="lazy">
          <h3><?= h($name) ?></h3>
          <p><?= $role ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/cta.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
