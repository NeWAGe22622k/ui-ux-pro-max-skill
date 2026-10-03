<?php
require __DIR__ . '/includes/lib.php';
require __DIR__ . '/includes/cards.php';

$active = 'rentals';
$title = 'Homes to rent';
$desc = 'Available homes to rent from Refugehomes Ltd. View photos and full details, and find each listing on the major property portals.';
$withListings = true;

$items = published('rentals');
// Available homes first, "let agreed" after
usort($items, fn($a, $b) => (($a['status'] ?? '') === 'let') <=> (($b['status'] ?? '') === 'let'));
$available = count(array_filter($items, fn($r) => ($r['status'] ?? '') !== 'let'));

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container page-hero-split">
    <div class="reveal">
      <p class="eyebrow">Rentals</p>
      <h1 class="h1">Homes to <em>rent.</em></h1>
    </div>
    <div class="reveal">
      <p class="lead">Quality, well-managed homes in well-connected locations. Select a home for full details, photos and links to where it is advertised.</p>
      <?php if ($items): ?><p class="muted" style="margin-top:16px"><?= $available ?> home<?= $available === 1 ? '' : 's' ?> available now</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <?php if ($items): ?>
      <div class="grid grid-3">
        <?php foreach ($items as $r) rental_card($r); ?>
      </div>
    <?php endif; ?>
    <?php if (!$available): ?>
      <div class="empty"<?= $items ? ' style="margin-top:48px"' : '' ?>>
        <h2 class="h3">No homes available right now</h2>
        <p>New homes come up regularly. Tell us what you are looking for and we will let you know as soon as something suitable is available.</p>
        <a class="btn btn-primary" href="contact.php?enquiry=Register%20interest%20in%20renting">Register your interest</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-white">
  <div class="container split">
    <div class="reveal">
      <p class="eyebrow">For landlords</p>
      <h2 class="h2">Looking for reliable tenants?</h2>
    </div>
    <div class="reveal">
      <p class="prose lead">We market your property on the major portals, carry out thorough referencing and can manage the tenancy for you from start to finish — including guaranteed rent options.</p>
      <a class="link-arrow" href="services.php#management">Our management service <?= icon('arrow-right', 16) ?></a>
    </div>
  </div>
</section>

<?php listing_modals(); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
