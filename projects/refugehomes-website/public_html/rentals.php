<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cards.php';

$page_key = 'rentals';
$page_title = 'Homes to rent';
$page_description = 'Browse homes available to rent from Refugehomes Ltd, with full details, photos and links to apply.';

$rentals = listings('rentals');
// Available homes first; "let agreed" ones stay visible underneath. usort is stable on PHP 8.
usort($rentals, fn($a, $b) => (($a['status'] ?? '') === 'let_agreed') <=> (($b['status'] ?? '') === 'let_agreed'));
$available = count(array_filter($rentals, fn($r) => ($r['status'] ?? 'available') === 'available'));

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Rentals</p>
    <h1>Homes to rent</h1>
    <p class="lead">
      <?= $available ?> home<?= $available === 1 ? '' : 's' ?> available right now.
      Select a property for full details, photos and links to where it&rsquo;s advertised.
    </p>
    <?php if ($rentals): ?>
      <div class="filter" role="group" aria-label="Filter rentals" data-filter>
        <button type="button" class="chip" aria-pressed="true" data-filter-value="all">All</button>
        <button type="button" class="chip" aria-pressed="false" data-filter-value="available">Available only</button>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-tight">
  <div class="container">
    <?php if ($rentals): ?>
      <div class="card-grid" data-filter-target>
        <?php foreach ($rentals as $r) rental_card($r); ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <p><strong>No homes are available right now.</strong></p>
        <p>New properties come up regularly. <a href="<?= e(url('contact?type=renting')) ?>">Tell us what you&rsquo;re looking for</a> and we&rsquo;ll let you know.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-alt">
  <div class="container split">
    <div>
      <h2>Can&rsquo;t see the right home?</h2>
      <p>Register your interest and we&rsquo;ll contact you when a suitable property becomes available.</p>
    </div>
    <div class="split-end">
      <a class="btn btn-primary" href="<?= e(url('contact?type=renting')) ?>">Register your interest <?= icon('arrow') ?></a>
    </div>
  </div>
</section>

<script type="application/json" data-collection="rentals"><?= modal_payload($rentals, 'rentals') ?></script>
<?php listing_modal(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
