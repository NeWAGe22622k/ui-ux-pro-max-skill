<?php
require __DIR__ . '/includes/bootstrap.php';

$page_key = 'services';
$page_title = 'Services';
$page_description = 'Property management, lettings, renovation and property sourcing services from Refugehomes Ltd.';

// PLACEHOLDER: adjust services and bullet points to match what you offer.
$services = [
    [
        'icon'  => 'key',
        'title' => 'Full property management',
        'intro' => 'A complete, hands-off service for landlords. We look after your property and your tenants day to day.',
        'items' => ['Rent collection and arrears management', 'Repairs and maintenance through vetted tradespeople', 'Regular inspections with written reports', 'Gas, electrical and safety compliance', 'Deposit protection and check-in/check-out'],
    ],
    [
        'icon'  => 'users',
        'title' => 'Lettings & tenant finding',
        'intro' => 'We market your property, find the right tenant and handle everything up to move-in day.',
        'items' => ['Professional photos and listings on major portals', 'Accompanied viewings', 'Full referencing and Right to Rent checks', 'Tenancy agreements and inventory'],
    ],
    [
        'icon'  => 'wrench',
        'title' => 'Renovation & refurbishment',
        'intro' => 'We project-manage refurbishments from planning to completion, for our own projects and for landlords.',
        'items' => ['Kitchens, bathrooms and full refurbishments', 'Bringing properties up to letting standard', 'Trusted, insured tradespeople', 'Clear quotes and timelines'],
    ],
    [
        'icon'  => 'trending',
        'title' => 'We buy properties',
        'intro' => 'Selling a property that needs work? We buy homes in any condition and can move quickly.',
        'items' => ['Fair, no-obligation offers', 'Properties in any condition', 'Flexible completion dates', 'No estate agent fees'],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Services</p>
    <h1>How we can help</h1>
    <p class="lead">Straightforward, reliable property services for landlords, tenants and sellers.</p>
  </div>
</section>

<section class="section">
  <div class="container service-list">
    <?php foreach ($services as $n => $s): ?>
      <article class="service">
        <div class="service-head">
          <span class="feature-icon"><?= icon($s['icon'], 22) ?></span>
          <span class="service-num"><?= sprintf('%02d', $n + 1) ?></span>
        </div>
        <div>
          <h2><?= e($s['title']) ?></h2>
          <p><?= e($s['intro']) ?></p>
        </div>
        <ul class="check-list">
          <?php foreach ($s['items'] as $item): ?><li><?= icon('check', 18) ?> <?= e($item) ?></li><?php endforeach; ?>
        </ul>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow">How it works</p>
      <h2>Getting started is simple</h2>
    </div>
    <ol class="steps">
      <li><strong>Get in touch</strong><span>Tell us about your property or what you&rsquo;re looking for.</span></li>
      <li><strong>Free consultation</strong><span>We visit, talk through your goals and recommend the right service.</span></li>
      <li><strong>Clear proposal</strong><span>You receive a written proposal with transparent fees.</span></li>
      <li><strong>We take it from there</strong><span>Sit back while we look after the detail.</span></li>
    </ol>
  </div>
</section>

<section class="cta-band">
  <div class="container cta-inner">
    <div>
      <h2>Book a free consultation</h2>
      <p>No obligation, just honest advice about your property.</p>
    </div>
    <a class="btn btn-light" href="<?= e(url('contact')) ?>">Contact us <?= icon('arrow') ?></a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
