<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$current = current_branch();
$byCity = [];
foreach (branches() as $b) {
    $byCity[$b['city'] ?: 'Other'][] = $b;
}

page_start('Store locator', [
    'active' => 'stores',
    'description' => 'Find a Prince Ebeano Supermarket near you — addresses, opening hours, phone numbers and directions for every store in Lagos and Abuja.',
]);
?>

<section class="page-hero page-hero-dark">
  <div class="container">
    <h1>Find your nearest Prince Ebeano</h1>
    <p class="lede"><?= count(branches()) ?> stores across <?= e(implode(' and ', array_keys($byCity))) ?>. Tap a store to see what's on its shelves today.</p>
  </div>
</section>

<div class="container">
  <?php foreach ($byCity as $city => $list): ?>
    <section class="section-tight">
      <h2 style="font-size:1.5rem;margin:1.5rem 0 1rem"><?= e($city) ?></h2>
      <div class="store-grid">
        <?php foreach ($list as $b): $checked = branch_last_checked((int)$b['id']); ?>
          <article class="store-card<?= $current && $current['id'] === $b['id'] ? ' is-current' : '' ?>" id="<?= e($b['slug']) ?>" data-reveal>
            <div class="store-card-head">
              <div>
                <p class="store-city"><?= e($b['city']) ?></p>
                <h3><?= e($b['name']) ?></h3>
              </div>
              <?php if ($current && $current['id'] === $b['id']): ?><span class="pill pill-in"><?= icon('circle-check', 'icon icon-sm') ?>Your store</span><?php endif; ?>
            </div>
            <ul class="store-lines">
              <?php if ($b['address']): ?><li><?= icon('map-pin', 'icon icon-sm') ?><span><?= e($b['address']) ?></span></li><?php endif; ?>
              <?php if ($b['hours_text']): ?><li><?= icon('clock', 'icon icon-sm') ?><span><?= nl2br(e($b['hours_text'])) ?></span></li><?php endif; ?>
              <?php foreach (array_filter(array_map('trim', explode(',', $b['phones']))) as $p): ?>
                <li><?= icon('phone', 'icon icon-sm') ?><a href="<?= e(phone_href($p)) ?>"><?= e($p) ?></a></li>
              <?php endforeach; ?>
              <?php if ($b['email']): ?><li><?= icon('mail', 'icon icon-sm') ?><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a></li><?php endif; ?>
              <li><?= icon('basket', 'icon icon-sm') ?><span>Stock checked <?= e(fresh_label($checked)) ?></span></li>
            </ul>
            <div class="store-actions">
              <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php?branch=' . urlencode($b['slug']))) ?>">See what's in stock</a>
              <?php $maps = $b['map_url'] ?: 'https://www.google.com/maps/search/?api=1&query=' . urlencode('Prince Ebeano Supermarket ' . $b['name'] . ' ' . $b['address']); ?>
              <a class="btn btn-ghost btn-sm" href="<?= e($maps) ?>" target="_blank" rel="noopener"><?= icon('map-pin', 'icon icon-sm') ?> Directions</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?php page_end();
