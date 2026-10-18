<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$branch = current_branch();
$bid = $branch ? (int)$branch['id'] : null;

// Per-category numbers for the chosen branch.
$catStats = [];
if ($bid) {
    foreach (q_all(
        "SELECT p.category_id,
                SUM(s.stock_status IN ('in_stock','low_stock')) AS available,
                COUNT(*) AS carried
         FROM branch_stock s JOIN products p ON p.id = s.product_id AND p.is_active = 1
         WHERE s.branch_id = ? GROUP BY p.category_id",
        [$bid]
    ) as $row) {
        $catStats[(int)$row['category_id']] = $row;
    }
    foreach (q_all('SELECT category_id, reviewed_at FROM category_reviews WHERE branch_id = ?', [$bid]) as $row) {
        $catStats[(int)$row['category_id']]['reviewed_at'] = $row['reviewed_at'];
    }
}

$liveRows = $bid ? q_all(stock_select_sql() . ' WHERE s.branch_id = ? ORDER BY s.updated_at DESC LIMIT 5', [$bid]) : [];
$lastChecked = $bid ? branch_last_checked($bid) : null;

// "Just restocked": back in stock in the last 2 days. Falls back to what's on the shelves now.
$restocked = [];
$restockTitle = 'Just restocked';
if ($bid) {
    $restocked = q_all(
        stock_select_sql() . "
        JOIN (SELECT product_id, MAX(created_at) AS at FROM stock_updates
              WHERE branch_id = ? AND new_status = 'in_stock' AND (old_status IS NULL OR old_status <> 'in_stock')
                AND created_at >= (NOW() - INTERVAL 2 DAY)
              GROUP BY product_id) r ON r.product_id = p.id
        WHERE s.branch_id = ? AND s.stock_status = 'in_stock'
        ORDER BY r.at DESC LIMIT 12",
        [$bid, $bid]
    );
    if (count($restocked) < 4) {
        // Too few to fill the rail: top up with other items on the shelves now.
        $restockTitle = 'On the shelves now';
        $seen = array_column($restocked, 'id');
        foreach (q_all(stock_select_sql() . " WHERE s.branch_id = ? AND s.stock_status = 'in_stock' ORDER BY s.updated_at DESC LIMIT 12", [$bid]) as $row) {
            if (count($restocked) >= 12) {
                break;
            }
            if (!in_array($row['id'], $seen, true)) {
                $restocked[] = $row;
            }
        }
    }
}

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
    <?= section_head('', 'Aisles', $branch
        ? 'What is available at ' . $branch['name'] . ' and when each aisle was last checked.'
        : 'Choose an aisle, then pick your store to see what is in stock.') ?>
    <ul class="aisle-list">
      <?php foreach (categories() as $c):
        $st = $catStats[(int)$c['id']] ?? [];
        $reviewed = $st['reviewed_at'] ?? null; ?>
        <li>
          <a href="<?= e(url('shop.php?category=' . urlencode($c['slug']))) ?>">
            <span class="aisle-name"><?= e($c['name']) ?></span>
            <span class="aisle-meta">
              <?php if ($branch): ?>
                <?= (int)($st['available'] ?? 0) ?> available · <span class="aisle-fresh"><?= $reviewed ? 'checked ' . e(fresh_label($reviewed)) : 'not checked yet' ?></span>
              <?php else: ?>
                Browse aisle
              <?php endif; ?>
            </span>
            <?= icon('chevron-right', 'icon icon-sm') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php if ($restocked): ?>
<section class="section">
  <div class="container">
    <?= section_head('', $restockTitle . ' at ' . $branch['name'], '', url('shop.php'), 'Shop all') ?>
    <div class="rail">
      <?php foreach ($restocked as $p) echo product_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section-tight">
  <div class="container">
    <div class="promo-row">
      <p><strong>Gift vouchers</strong> for birthdays, weddings and staff rewards. Redeemable at every Prince Ebeano store.</p>
      <a class="link-arrow" href="<?= e(url('gift-vouchers.php')) ?>">See voucher values <?= icon('arrow-right', 'icon icon-sm') ?></a>
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

<?php page_end();
