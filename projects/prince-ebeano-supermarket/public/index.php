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

page_start('', ['active' => 'home', 'branch_prompt' => true]);
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="hero-badge"><span class="chip-dot is-live"></span> Live stock · updated every morning</span>
      <h1>See what's on our shelves <span class="hl">before</span> you leave home.</h1>
      <p class="hero-sub"><?= e(config('store.tagline')) ?>. Check prices in Naira and what's in stock at your Prince Ebeano store — no wasted trips.</p>

      <form class="search-bar" action="<?= e(url('shop.php')) ?>" method="get" role="search">
        <?= icon('search') ?>
        <label for="hero-q" class="visually-hidden">Search products</label>
        <input id="hero-q" name="q" type="search" placeholder="Search Milo, rice, diapers…" autocomplete="off">
        <?php if ($branch): ?><input type="hidden" name="branch" value="<?= e($branch['slug']) ?>"><?php endif; ?>
        <button class="btn btn-mango" type="submit">Search</button>
      </form>

      <div class="quick-cats" aria-label="Popular categories">
        <?php foreach (array_slice(categories(), 0, 5) as $c): ?>
          <a href="<?= e(url('shop.php?category=' . urlencode($c['slug']))) ?>"><?= icon($c['icon'], 'icon icon-sm') ?> <?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <aside class="live-card" aria-label="Latest stock updates">
      <?php if ($branch): ?>
        <div class="live-head">
          <h2><span class="chip-dot is-live"></span> Live at <?= e($branch['name']) ?></h2>
          <button type="button" class="btn btn-ghost btn-sm" data-open-branch>Switch</button>
        </div>
        <?php if ($liveRows): ?>
          <ul class="live-list">
            <?php foreach ($liveRows as $r): ?>
              <li class="live-row" data-pid="<?= (int)$r['id'] ?>">
                <?= product_media($r, 'live-thumb', true) ?>
                <span class="live-name"><?= e($r['name']) ?><small><?= $r['price_kobo'] > 0 ? e(naira((int)$r['price_kobo'])) : 'Ask in store' ?></small></span>
                <?= status_pill($r['stock_status']) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted">Our team at <?= e($branch['name']) ?> hasn't posted today's stock yet. Check back soon.</p>
        <?php endif; ?>
        <div class="live-foot">
          <span><?= icon('clock', 'icon icon-sm') ?> Checked <?= e(fresh_label($lastChecked)) ?></span>
          <a class="link-arrow" href="<?= e(url('shop.php')) ?>">See all<?= icon('arrow-right', 'icon icon-sm') ?></a>
        </div>
      <?php else: ?>
        <div class="live-head"><h2><?= icon('map-pin', 'icon') ?> Pick your store</h2></div>
        <p class="muted">Stock is different at each Prince Ebeano. Choose yours and we'll show you exactly what's on the shelves today.</p>
        <ul class="live-list">
          <?php foreach (array_slice(branches(), 0, 4) as $b): ?>
            <li><a class="branch-option" href="<?= e(current_url_with(['branch' => $b['slug']])) ?>">
              <span class="branch-option-text"><strong><?= e($b['name']) ?></strong><small><?= e($b['city']) ?></small></span>
              <?= icon('chevron-right', 'icon icon-sm') ?>
            </a></li>
          <?php endforeach; ?>
        </ul>
        <div class="live-foot"><span><?= $branchCount ?> stores</span><button type="button" class="btn btn-primary btn-sm" data-open-branch>All stores</button></div>
      <?php endif; ?>
    </aside>
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
        $cover['category_icon'] = $c['icon'];
        $names = array_map(fn($r) => preg_replace('/\s+\d.*$/', '', $r['name']), array_slice($items, 0, 3));
        $available = count(array_filter($items, fn($r) => in_array($r['stock_status'] ?? '', ['in_stock', 'low_stock'], true))); ?>
        <a class="aisle-card" href="<?= e(url('shop.php?category=' . urlencode($c['slug']))) ?>">
          <?php if ($cover['image_path']): ?><?= product_media($cover, 'aisle-card-media') ?><?php else: ?><span class="aisle-card-icon"><?= icon($c['icon']) ?></span><?php endif; ?>
          <span class="aisle-card-name"><?= e($c['name']) ?></span>
          <span class="aisle-card-sub"><?= $names ? e(implode(', ', $names)) . '…' : 'Coming soon' ?></span>
          <?php if ($branch): ?><span class="aisle-card-count"><?= $available ?> available</span><?php endif; ?>
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

<?php if ($restocked): ?>
<section class="section shelf">
  <div class="container">
    <?= section_head('', 'Back in stock at ' . $branch['name'], 'Items our staff restocked in the last two days.', url('shop.php'), 'View all') ?>
    <?= product_rail($restocked, 'Back in stock') ?>
  </div>
</section>
<?php endif; ?>

<?php if ($branch): foreach (categories() as $c):
    $items = $byCat[(int)$c['id']] ?? [];
    if (!$items) continue;
    $review = $reviews[(int)$c['id']] ?? null; ?>
<section class="section shelf" id="aisle-<?= e($c['slug']) ?>">
  <div class="container">
    <div class="section-head">
      <div>
        <h2><?= e($c['name']) ?></h2>
        <p class="section-sub"><span class="fresh-ribbon<?= $review && is_today($review['reviewed_at']) ? '' : ' is-stale' ?>"><span class="chip-dot"></span><?= $review ? 'Checked ' . e(fresh_label($review['reviewed_at'])) . ($review['staff_name'] ? ' by ' . e($review['staff_name']) : '') : 'Not checked yet' ?></span></p>
      </div>
      <a class="link-arrow" href="<?= e(url('shop.php?category=' . urlencode($c['slug']))) ?>">View all <?= count($items) ?> <?= icon('chevron-right', 'icon icon-sm') ?></a>
    </div>
    <?= product_rail(array_slice($items, 0, 12), $c['name']) ?>
  </div>
</section>
<?php endforeach; endif; ?>

<section class="section-tight">
  <div class="container">
    <div class="promo-row">
      <p><strong>Gift vouchers</strong> for birthdays, weddings and staff rewards. Redeemable at every Prince Ebeano store.</p>
      <a class="link-arrow" href="<?= e(url('gift-vouchers.php')) ?>">See voucher values <?= icon('chevron-right', 'icon icon-sm') ?></a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <?= section_head('', 'Visit us', $branchCount . ' stores in ' . implode(' and ', $cities) . '.', url('stores.php'), 'Addresses & directions') ?>
    <ul class="store-list">
      <?php foreach (branches() as $b): ?>
        <li>
          <a href="<?= e(url('stores.php#' . $b['slug'])) ?>">
            <strong><?= e($b['name']) ?></strong>
            <span><?= e($b['city']) ?><?= $b['hours_text'] ? ' · ' . e($b['hours_text']) : '' ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section about-note">
  <div class="container">
    <h2>Prince Ebeano Supermarket in Lagos and Abuja</h2>
    <p>Since 2009, Prince Ebeano has grown into a trusted chain of supermarkets across Lagos and Abuja, stocking groceries, drinks, toiletries, mothercare, household items, wine and spirits.</p>
    <p>This website shows what is on the shelves at each of our stores, with shelf prices in Naira. Our staff check every aisle each morning, so you can plan your trip before you leave home. To buy, visit any Prince Ebeano store.</p>
  </div>
</section>

<?php page_end();
