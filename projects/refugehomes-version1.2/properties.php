<?php
require __DIR__ . '/includes/lib.php';
require __DIR__ . '/includes/cards.php';

$active = 'properties';
$title = 'Our properties';
$desc = 'Properties managed by Refugehomes Ltd, and before-and-after photos of our refurbishment projects.';
$withListings = true;

$items = published('properties');
$counts = ['all' => count($items), 'managed' => 0, 'flip' => 0];
foreach ($items as $p) {
    $counts[($p['type'] ?? '') === 'flip' ? 'flip' : 'managed']++;
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container page-hero-split">
    <div class="reveal">
      <p class="eyebrow">Our properties</p>
      <h1 class="h1">Homes we manage &amp; <em>transform.</em></h1>
    </div>
    <p class="lead reveal">A selection of the properties in our care, and the refurbishment projects we have taken from tired to transformed. Select any property to see the full set of photos.</p>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <?php if ($items): ?>
      <div class="filters" data-filters role="group" aria-label="Filter properties">
        <button type="button" class="filter-btn" data-filter="all" aria-pressed="true">All<span class="count"><?= $counts['all'] ?></span></button>
        <button type="button" class="filter-btn" data-filter="managed" aria-pressed="false">Managed properties<span class="count"><?= $counts['managed'] ?></span></button>
        <button type="button" class="filter-btn" data-filter="flip" aria-pressed="false">Before &amp; after<span class="count"><?= $counts['flip'] ?></span></button>
      </div>
      <div class="grid grid-3" data-filter-grid>
        <?php foreach ($items as $p) property_card($p); ?>
      </div>
      <div class="empty" data-filter-empty hidden>
        <h2 class="h3">Nothing to show here yet</h2>
        <p>We are adding new projects regularly. Check back soon.</p>
      </div>
    <?php else: ?>
      <div class="empty">
        <h2 class="h3">Properties coming soon</h2>
        <p>We are preparing our portfolio for the new website. In the meantime, get in touch to hear about our work.</p>
        <a class="btn btn-primary" href="contact.php">Contact us</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/cta.php'; ?>
<?php listing_modals(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
