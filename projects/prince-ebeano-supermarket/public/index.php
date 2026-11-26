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

// Lowest shelf price at this store in the given aisles, for the banner lines ("from ₦350").
$fromPrice = function (array $slugs) use ($byCat, $bid): ?int {
    if (!$bid) {
        return null;
    }
    $min = null;
    foreach ($slugs as $slug) {
        $c = category_by_slug($slug);
        foreach ($c ? ($byCat[(int)$c['id']] ?? []) : [] as $r) {
            if ($r['stock_status'] !== 'out_of_stock' && $r['price_kobo'] > 0) {
                $min = $min === null ? (int)$r['price_kobo'] : min($min, (int)$r['price_kobo']);
            }
        }
    }
    return $min;
};
$aisle = fn(string $slug) => url(category_by_slug($slug) ? 'shop.php?category=' . urlencode($slug) : 'shop.php');
$where = $branch ? ' at ' . $branch['name'] : '';
$fromLine = function (array $slugs, string $fallback) use ($fromPrice, $where): string {
    $min = $fromPrice($slugs);
    return $min ? 'from <strong>' . e(naira($min)) . '</strong>' . e($where) : e($fallback);
};
$shopNow = 'Shop now <span aria-hidden="true">»</span>';

page_start('', ['active' => 'home', 'branch_prompt' => true]);
?>

<section class="hero">
  <div class="container hero-grid">
    <div class="hero-main" data-slider>
      <div class="hero-track" data-slider-track>
        <div class="hero-slide" id="slide-1" aria-label="1 of 2">
          <div class="hero-copy">
            <p class="hero-kicker">Fresh &amp; everyday</p>
            <h1 class="hero-title">Groceries &amp; drinks</h1>
            <p class="hero-line"><?= $fromLine(['drinks', 'cereals', 'dairy', 'bakery'], 'Live stock at ' . $branchCount . ' stores') ?></p>
            <a class="btn btn-dark" href="<?= e(url('shop.php')) ?>"><?= $shopNow ?></a>
          </div>
          <?= art_scene([
              ['leaves', 70, 0, 22, 25], ['leafy', 30, 6, 26, -18], ['corn', 53, 2, 24, 22], ['carrot', 42, 12, 20, 8],
              ['basket', 18, 22, 60, 0], ['avocado', 0, 40, 20, -14], ['tangerine', 8, 66, 22, 0],
              ['apple', 66, 60, 22, 8], ['lemon', 76, 30, 17, -12],
          ], 'hero-art') ?>
        </div>
        <div class="hero-slide" id="slide-2" aria-label="2 of 2">
          <div class="hero-copy">
            <p class="hero-kicker">Clean &amp; cared for</p>
            <h2 class="hero-title">Home &amp; personal care</h2>
            <p class="hero-line"><?= $fromLine(['household', 'toiletries', 'mothercare'], 'Cleaning, toiletries and baby care') ?></p>
            <a class="btn btn-dark" href="<?= e($aisle('household')) ?>"><?= $shopNow ?></a>
          </div>
          <?= art_scene([
              ['bubbles', 66, 2, 20, 0], ['babybottle', 14, 4, 24, -12], ['lotion', 42, 0, 28, 6], ['tissue', 0, 40, 26, 0],
              ['sponge', 24, 52, 30, -10], ['soap', 52, 46, 32, 10], ['toothbrush', 74, 40, 24, 20],
          ], 'hero-art') ?>
        </div>
      </div>
      <div class="hero-dots" role="group" aria-label="Choose slide">
        <button type="button" data-slide="0" aria-label="Show slide 1" aria-current="true"></button>
        <button type="button" data-slide="1" aria-label="Show slide 2" aria-current="false"></button>
      </div>
    </div>

    <a class="banner tone-yellow hero-side" href="<?= e($aisle('drinks')) ?>">
      <span class="banner-copy">
        <span class="banner-kicker">Chilled &amp; ready</span>
        <span class="banner-title">Drinks</span>
        <span class="btn btn-dark btn-sm"><?= $shopNow ?></span>
      </span>
      <?= art_scene([['juicebox', 6, 14, 46, -8], ['cup', 42, 0, 52, 6], ['lemon', 0, 58, 34, -14], ['tangerine', 46, 54, 40, 0]]) ?>
    </a>
    <a class="banner tone-pink hero-side" href="<?= e($aisle('bakery')) ?>">
      <span class="banner-copy">
        <span class="banner-kicker">Baked daily</span>
        <span class="banner-title">Bakery</span>
        <span class="btn btn-dark btn-sm"><?= $shopNow ?></span>
      </span>
      <?= art_scene([['bread', 24, 0, 58, -6], ['croissant', -4, 46, 54, -10], ['baguette', 44, 50, 56, 14]]) ?>
    </a>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <?= section_head('', 'Shop by aisle', '', url('shop.php'), 'All aisles') ?>
    <div class="aisle-cards">
      <?php foreach (categories() as $c):
        $items = $byCat[(int)$c['id']] ?? [];
        $available = count(array_filter($items, fn($r) => in_array($r['stock_status'] ?? '', ['in_stock', 'low_stock'], true))); ?>
        <a class="aisle-card" href="<?= e(url('shop.php?category=' . urlencode($c['slug']))) ?>">
          <span class="aisle-card-media"><?= art(category_art($c), '', 'aisle-art') ?></span>
          <span class="aisle-card-name"><?= e($c['name']) ?></span>
          <span class="aisle-card-count"><?= $branch ? $available . ' available' : count($items) . ' products' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!$branch): ?>
<section class="section" data-reveal>
  <div class="container"><?= branch_chooser_inline('Choose your store to see prices and what is in stock') ?></div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section" data-reveal>
  <div class="container">
    <?= section_head('', 'In stock at ' . $branch['name'], '', url('shop.php'), 'View all products') ?>
    <div class="product-grid">
      <?php foreach ($featured as $p) echo product_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" data-reveal>
  <div class="container">
    <a class="banner banner-wide tone-mint" href="<?= e($aisle('dairy')) ?>">
      <?= art_scene([
          ['bread', 2, 10, 17, -10], ['baguette', 40, 0, 15, 32], ['milk', 22, 4, 13, 0], ['cheese', 18, 52, 15, 8],
          ['croissant', 36, 46, 17, -12], ['egg', 56, 12, 8, 10], ['egg', 62, 30, 8, -14], ['butter', 60, 56, 14, 0],
          ['strawberry', 2, 64, 10, -20], ['cupcake', 78, 8, 12, 0], ['pancakes', 80, 52, 14, 0],
      ], 'banner-art banner-art-wide') ?>
      <span class="banner-copy">
        <span class="banner-title-lg">Dairy &amp; Bakery<br>Fresh every morning</span>
        <span class="banner-line"><strong>Checked before we open</strong> <?= $branch ? 'at ' . e($branch['name']) : 'at every store' ?></span>
        <span class="btn btn-dark">Shop now</span>
      </span>
    </a>
  </div>
</section>

<?php if ($restocked): ?>
<section class="section" data-reveal>
  <div class="container">
    <?= section_head('', 'Back in stock', 'Items our staff restocked at ' . $branch['name'] . ' in the last two days.', url('shop.php'), 'View all') ?>
    <div class="product-grid">
      <?php foreach (array_slice($restocked, 0, 8) as $p) echo product_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" data-reveal>
  <div class="container">
    <div class="banner-row">
      <a class="banner tone-pink" href="<?= e(url('gift-vouchers.php')) ?>">
        <span class="banner-copy">
          <span class="banner-kicker">Gift vouchers</span>
          <span class="banner-title">Give the gift of choice</span>
          <span class="btn btn-dark btn-sm">See values <span aria-hidden="true">»</span></span>
        </span>
        <?= art_scene([['gift', 18, 14, 64, -6]]) ?>
      </a>
      <a class="banner tone-yellow" href="<?= e(url('stores.php')) ?>">
        <span class="banner-copy">
          <span class="banner-kicker">Find a store</span>
          <span class="banner-title"><?= $branchCount ?> stores near you</span>
          <span class="btn btn-dark btn-sm">Addresses <span aria-hidden="true">»</span></span>
        </span>
        <?= art_scene([['store', 12, 12, 66, 0]]) ?>
      </a>
      <a class="banner tone-mint" href="<?= e(url('careers.php')) ?>">
        <span class="banner-copy">
          <span class="banner-kicker">Careers</span>
          <span class="banner-title">Grow with us</span>
          <span class="btn btn-dark btn-sm">Open roles <span aria-hidden="true">»</span></span>
        </span>
        <?= art_scene([['shopbags', 14, 14, 62, -4]]) ?>
      </a>
    </div>
  </div>
</section>

<?php page_end();
