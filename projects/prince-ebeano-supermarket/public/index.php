<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$branch = current_branch();
$bid = $branch ? (int)$branch['id'] : null;

// Everything the chosen store carries, grouped by aisle (in stock first).
$byCat = [];
$reviews = [];
if ($bid) {
    $rows = q_all(stock_select_sql() . " WHERE s.branch_id = ?
        ORDER BY FIELD(s.stock_status, 'in_stock', 'low_stock', 'out_of_stock'), p.name", [$bid]);
    foreach (q_all('SELECT category_id, reviewed_at, staff_name FROM category_reviews WHERE branch_id = ?', [$bid]) as $row) {
        $reviews[(int)$row['category_id']] = $row;
    }
} else {
    $rows = q_all('SELECT p.id, p.name, p.image_path, p.category_id FROM products p WHERE p.is_active = 1 ORDER BY p.name');
}
foreach ($rows as $row) {
    $byCat[(int)$row['category_id']][] = $row;
}

$liveRows = $bid ? q_all(stock_select_sql() . ' WHERE s.branch_id = ? ORDER BY s.updated_at DESC LIMIT 5', [$bid]) : [];
$lastChecked = $bid ? branch_last_checked($bid) : null;

// Back in stock in the last 2 days.
$restocked = $bid ? q_all(
    stock_select_sql() . "
    JOIN (SELECT product_id, MAX(created_at) AS at FROM stock_updates
          WHERE branch_id = ? AND new_status = 'in_stock' AND (old_status IS NULL OR old_status <> 'in_stock')
            AND created_at >= (NOW() - INTERVAL 2 DAY)
          GROUP BY product_id) r ON r.product_id = p.id
    WHERE s.branch_id = ? AND s.stock_status = 'in_stock'
    ORDER BY r.at DESC LIMIT 12",
    [$bid, $bid]
) : [];

$branchCount = count(branches());
$cities = array_values(array_unique(array_filter(array_column(branches(), 'city'))));

// Featured: a couple of in-stock items from each aisle, so the grid shows the range.
$featured = [];
if ($bid) {
    for ($round = 0; $round < 2 && count($featured) < 8; $round++) {
        foreach (categories() as $c) {
            $inStock = array_values(array_filter($byCat[(int)$c['id']] ?? [], fn($r) => $r['stock_status'] === 'in_stock'));
            if (isset($inStock[$round])) {
                $featured[] = $inStock[$round];
            }
            if (count($featured) === 8) {
                break;
            }
        }
    }
}

page_start('', ['active' => 'home', 'branch_prompt' => true]);
?>

<section class="hero">
  <div class="container">
    <div class="hero-panel">
      <div>
        <p class="hero-eyebrow">Welcome to Prince Ebeano Supermarket</p>
        <h1>See what's on our shelves before you leave home</h1>
        <p class="hero-offer"><?= $branch ? 'Live stock at <span>' . e($branch['name']) . '</span>' : 'Live stock at <span>' . $branchCount . ' stores</span>' ?></p>
        <p class="hero-sub">Our staff check every aisle each morning, so you know what's in stock and the shelf price in Naira before you make the trip.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Browse aisles <?= icon('arrow-right', 'icon icon-sm') ?></a>
          <?php if (!$branch): ?><button type="button" class="btn btn-ghost" data-open-branch>Choose your store</button><?php endif; ?>
        </div>
      </div>

      <aside class="live-card" aria-label="Latest stock updates">
        <?php if ($branch): ?>
          <div class="live-head">
            <h2><span class="chip-dot is-live"></span> Latest at <?= e($branch['name']) ?></h2>
            <button type="button" class="btn btn-ghost btn-sm" data-open-branch>Switch</button>
          </div>
          <?php if ($liveRows): ?>
            <ul class="live-list">
              <?php foreach ($liveRows as $r): ?>
                <li class="live-row" data-pid="<?= (int)$r['id'] ?>">
                  <?= product_media($r, 'live-thumb') ?>
                  <span class="live-name"><?= e($r['name']) ?><small><?= $r['price_kobo'] > 0 ? e(naira((int)$r['price_kobo'])) : 'Ask in store' ?></small></span>
                  <?= status_pill($r['stock_status']) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">Our team at <?= e($branch['name']) ?> hasn't posted today's stock yet. Check back soon.</p>
          <?php endif; ?>
          <div class="live-foot">
            <span>Checked <?= e(fresh_label($lastChecked)) ?></span>
            <a class="link-arrow" href="<?= e(url('shop.php')) ?>">See all<?= icon('arrow-right', 'icon icon-sm') ?></a>
          </div>
        <?php else: ?>
          <div class="live-head"><h2>Pick your store</h2></div>
          <ul class="live-list">
            <?php foreach (array_slice(branches(), 0, 4) as $b): ?>
              <li><a class="branch-option" href="<?= e(current_url_with(['branch' => $b['slug']])) ?>">
                <span class="branch-option-text"><strong><?= e($b['name']) ?></strong><small><?= e($b['city']) ?></small></span>
                <?= icon('chevron-right', 'icon icon-sm') ?>
              </a></li>
            <?php endforeach; ?>
          </ul>
          <div class="live-foot"><span>Stock differs at each store</span><button type="button" class="btn-link" data-open-branch>All <?= $branchCount ?> stores</button></div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</section>

<section class="facts-section">
  <div class="container">
    <div class="facts">
      <div class="fact"><span class="fact-icon"><?= icon('clock') ?></span><strong>Checked every morning</strong><span>Staff update each aisle before opening.</span></div>
      <div class="fact"><span class="fact-icon"><?= icon('tag') ?></span><strong>Shelf prices in Naira</strong><span>The same price you pay at the till.</span></div>
      <div class="fact"><span class="fact-icon"><?= icon('store') ?></span><strong><?= $branchCount ?> stores</strong><span><?= e(implode(' and ', $cities)) ?>, open <?= e(today_hours()) ?> today.</span></div>
      <div class="fact"><span class="fact-icon"><?= icon('basket') ?></span><strong>Buy in store</strong><span>See it here, then pick it up at the shelf.</span></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <?= section_head('', 'Shop by aisle', '', url('shop.php'), 'All aisles') ?>
    <div class="aisle-cards">
      <?php foreach (categories() as $c):
        $items = $byCat[(int)$c['id']] ?? [];
        $withPhoto = array_values(array_filter($items, fn($r) => !empty($r['image_path'])));
        $cover = $withPhoto[0] ?? ['image_path' => null];
        $available = count(array_filter($items, fn($r) => in_array($r['stock_status'] ?? '', ['in_stock', 'low_stock'], true))); ?>
        <a class="aisle-card" href="<?= e(url('shop.php?category=' . urlencode($c['slug']))) ?>">
          <span class="aisle-card-media"><?php if ($cover['image_path']): ?><img src="<?= e(url($cover['image_path'])) ?>" alt="" loading="lazy" width="400" height="400"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
          <span class="aisle-card-name"><?= e($c['name']) ?></span>
          <span class="aisle-card-count"><?= $branch ? $available . ' available' : count($items) . ' products' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!$branch): ?>
<section class="section">
  <div class="container"><?= branch_chooser_inline('Choose your store to see prices and what is in stock') ?></div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section">
  <div class="container">
    <?= section_head('', 'In stock at ' . $branch['name'], '', url('shop.php'), 'View all products') ?>
    <div class="product-grid">
      <?php foreach ($featured as $p) echo product_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="promo-grid">
      <a class="promo promo-lg" href="<?= e(url('gift-vouchers.php')) ?>">
        <span class="promo-art" aria-hidden="true"><?= icon('gift') ?></span>
        <span class="promo-eyebrow">Gift vouchers</span>
        <h2>Give the gift of choice</h2>
        <p>For birthdays, weddings and staff rewards. Redeemable at every Prince Ebeano store.</p>
        <span class="btn">See voucher values <?= icon('arrow-right', 'icon icon-sm') ?></span>
      </a>
      <div class="promo-stack">
        <a class="promo promo-mid" href="<?= e(url('stores.php')) ?>">
          <span class="promo-art" aria-hidden="true"><?= icon('map-pin') ?></span>
          <span class="promo-eyebrow">Find a store</span>
          <h2><?= $branchCount ?> stores in <?= e(implode(' and ', $cities)) ?></h2>
          <span class="btn">Addresses and hours <?= icon('arrow-right', 'icon icon-sm') ?></span>
        </a>
        <a class="promo promo-dark" href="<?= e(url('careers.php')) ?>">
          <span class="promo-art" aria-hidden="true"><?= icon('briefcase') ?></span>
          <span class="promo-eyebrow">Careers</span>
          <h2>Grow with Prince Ebeano</h2>
          <span class="btn">See open roles <?= icon('arrow-right', 'icon icon-sm') ?></span>
        </a>
      </div>
    </div>
  </div>
</section>

<?php if ($restocked): ?>
<section class="section">
  <div class="container">
    <?= section_head('', 'Back in stock', 'Items our staff restocked at ' . $branch['name'] . ' in the last two days.', url('shop.php'), 'View all') ?>
    <div class="product-grid">
      <?php foreach (array_slice($restocked, 0, 8) as $p) echo product_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php page_end();
