<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cards.php';

$page_key = 'properties';
$page_title = 'Our properties';
$page_description = 'The homes Refugehomes Ltd manages, and before-and-after photos of our renovation projects.';

$managed = listings('managed');
$flips = listings('flips');

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Our properties</p>
    <h1>Homes we look after</h1>
    <p class="lead">A selection of the properties we manage, and the homes we&rsquo;ve brought back to life. Select any property to see all its photos.</p>
    <nav class="subnav" aria-label="Property sections">
      <a href="#portfolio">Managed portfolio <span class="count"><?= count($managed) ?></span></a>
      <a href="#renovations">Renovation projects <span class="count"><?= count($flips) ?></span></a>
    </nav>
  </div>
</section>

<section class="section section-tight" id="portfolio">
  <div class="container">
    <div class="section-head">
      <h2>Managed portfolio</h2>
      <p class="section-intro">Properties we manage on behalf of landlords and for our own portfolio.</p>
    </div>
    <?php if ($managed): ?>
      <div class="card-grid">
        <?php foreach ($managed as $p) property_card($p, 'managed'); ?>
      </div>
    <?php else: ?>
      <p class="empty">Properties will be listed here soon.</p>
    <?php endif; ?>
  </div>
</section>

<section class="section section-alt" id="renovations">
  <div class="container">
    <div class="section-head">
      <h2>Renovation projects</h2>
      <p class="section-intro">Before and after: properties we have bought and transformed. Open a project to compare the photos.</p>
    </div>
    <?php if ($flips): ?>
      <div class="card-grid">
        <?php foreach ($flips as $f) flip_card($f); ?>
      </div>
    <?php else: ?>
      <p class="empty">Renovation projects will be shown here soon.</p>
    <?php endif; ?>
  </div>
</section>

<script type="application/json" data-collection="managed"><?= modal_payload($managed, 'managed') ?></script>
<script type="application/json" data-collection="flips"><?= modal_payload($flips, 'flips') ?></script>
<?php listing_modal(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
