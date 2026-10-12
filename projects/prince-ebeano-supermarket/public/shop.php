<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$branch = current_branch();
$category = ($slug = get_str('category')) !== '' ? category_by_slug($slug) : null;
$search = get_str('q', 80);
$hideOut = ($_GET['hide_out'] ?? '') === '1';
$perCategoryPreview = 8;

$groups = [];
$total = 0;
$chipCounts = [];
$reviews = [];

if ($branch) {
    $bid = (int)$branch['id'];
    $where = ['s.branch_id = ?'];
    $params = [$bid];
    if ($category) {
        $where[] = 'c.id = ?';
        $params[] = (int)$category['id'];
    }
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if ($hideOut) {
        $where[] = "s.stock_status <> 'out_of_stock'";
    }
    $rows = q_all(
        stock_select_sql() . ' WHERE ' . implode(' AND ', $where) . "
        ORDER BY c.sort_order, c.name, FIELD(s.stock_status, 'in_stock', 'low_stock', 'out_of_stock'), p.name
        LIMIT 3000",
        $params
    );
    foreach ($rows as $r) {
        $groups[(int)$r['category_id']][] = $r;
    }
    $total = count($rows);

    foreach (q_all(
        "SELECT p.category_id, COUNT(*) AS n FROM branch_stock s JOIN products p ON p.id = s.product_id AND p.is_active = 1
         WHERE s.branch_id = ? AND s.stock_status IN ('in_stock','low_stock') GROUP BY p.category_id",
        [$bid]
    ) as $r) {
        $chipCounts[(int)$r['category_id']] = (int)$r['n'];
    }
    foreach (q_all('SELECT category_id, reviewed_at, staff_name FROM category_reviews WHERE branch_id = ?', [$bid]) as $r) {
        $reviews[(int)$r['category_id']] = $r;
    }
}

$browsing = !$category && $search === '';
$title = $category ? $category['name'] : ($search !== '' ? 'Search' : 'Shop');

page_start($title, [
    'active' => 'shop',
    'branch_prompt' => true,
    'description' => $branch
        ? 'What\'s in stock at Prince Ebeano ' . $branch['name'] . ' today — live stock and prices in Naira.'
        : null,
]);
?>

<section class="page-hero">
  <div class="container shop-head">
    <div>
      <p class="eyebrow"><?= $branch ? 'Live stock · ' . e($branch['name']) : 'Live stock' ?></p>
      <h1><?= $category ? e($category['name']) : ($branch ? 'Shop ' . e($branch['name']) : 'Shop') ?></h1>
      <?php if ($branch): ?>
        <span class="fresh-line"><?= icon('clock', 'icon icon-sm') ?> Last stock check <?= e(fresh_label(branch_last_checked((int)$branch['id']))) ?></span>
      <?php else: ?>
        <p class="lede">Choose your store and we'll show you what's on its shelves right now.</p>
      <?php endif; ?>
    </div>
    <?php if ($branch): ?>
      <button type="button" class="btn btn-ghost" data-open-branch><?= icon('map-pin', 'icon icon-sm') ?> Change store</button>
    <?php endif; ?>
  </div>
</section>

<?php if (!$branch): ?>
  <div class="container"><?= branch_chooser_inline() ?></div>
<?php else: ?>

<div class="filters">
  <form class="container filters-inner" method="get" action="<?= e(url('shop.php')) ?>" role="search">
    <input type="hidden" name="branch" value="<?= e($branch['slug']) ?>">
    <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category['slug']) ?>"><?php endif; ?>
    <div class="filters-row">
      <div class="filter-search">
        <?= icon('search') ?>
        <label for="shop-q" class="visually-hidden">Search products</label>
        <input id="shop-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Search <?= e($category ? strtolower($category['name']) : 'products') ?>…">
        <button class="btn btn-primary btn-sm" type="submit">Go</button>
      </div>
      <label class="toggle">
        <input type="checkbox" name="hide_out" value="1" <?= $hideOut ? 'checked' : '' ?> data-autosubmit>
        <span>Hide sold out</span>
      </label>
    </div>
    <nav class="chips" aria-label="Aisles">
      <a class="chip" href="<?= e(current_url_with(['category' => null, 'q' => null])) ?>" aria-current="<?= !$category ? 'true' : 'false' ?>">All aisles</a>
      <?php foreach (categories() as $c): ?>
        <a class="chip" href="<?= e(current_url_with(['category' => $c['slug'], 'q' => null])) ?>" aria-current="<?= $category && $category['id'] === $c['id'] ? 'true' : 'false' ?>">
          <?= icon($c['icon'], 'icon icon-sm') ?> <?= e($c['name']) ?> <span class="count"><?= $chipCounts[(int)$c['id']] ?? 0 ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </form>
</div>

<div class="container">
  <?php if ($search !== ''): ?>
    <p class="results-meta"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($search) ?>” at <?= e($branch['name']) ?>
      · <a href="<?= e(current_url_with(['q' => null])) ?>">Clear search</a></p>
  <?php endif; ?>

  <?php if (!$groups): ?>
    <div style="margin-top:2rem">
      <?= empty_state(
          'basket',
          $search !== '' ? 'Nothing matches that search' : 'Nothing listed here yet',
          $search !== ''
              ? 'Try a shorter word, or check another aisle. Our staff may not have added this item online yet — feel free to call the store.'
              : 'Our team at ' . $branch['name'] . ' hasn\'t listed items in this aisle yet. Please check back after the morning stock check.',
          '<a class="btn btn-primary" href="' . e(url('shop.php')) . '">See all aisles</a>'
      ) ?>
    </div>
  <?php endif; ?>

  <?php foreach (categories() as $c):
      $items = $groups[(int)$c['id']] ?? [];
      if (!$items) continue;
      $review = $reviews[(int)$c['id']] ?? null;
      $fresh = $review && is_today($review['reviewed_at']);
      $shown = $browsing ? array_slice($items, 0, $perCategoryPreview) : $items; ?>
    <section class="cat-section" id="<?= e($c['slug']) ?>" aria-labelledby="h-<?= e($c['slug']) ?>">
      <div class="cat-section-head">
        <h2 id="h-<?= e($c['slug']) ?>"><span class="cat-badge tint-<?= e($c['tint']) ?>"><?= icon($c['icon']) ?></span><?= e($c['name']) ?></h2>
        <span class="fresh-ribbon<?= $fresh ? '' : ' is-stale' ?>">
          <span class="chip-dot<?= $fresh ? ' is-live' : '' ?>"></span>
          <?= $review ? 'Freshly checked ' . e(fresh_label($review['reviewed_at'])) . ($review['staff_name'] ? ' by ' . e($review['staff_name']) : '') : 'Not checked yet' ?>
        </span>
      </div>
      <div class="product-grid is-wide">
        <?php foreach ($shown as $p) echo product_card($p, false); ?>
      </div>
      <?php if ($browsing && count($items) > $perCategoryPreview): ?>
        <p style="margin-top:1rem"><a class="btn btn-ghost" href="<?= e(current_url_with(['category' => $c['slug']])) ?>">See all <?= count($items) ?> in <?= e($c['name']) ?> <?= icon('arrow-right', 'icon icon-sm') ?></a></p>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

  <p class="muted" style="margin-top:2.5rem;font-size:.9rem"><?= icon('circle-alert', 'icon icon-sm') ?> Stock is checked by our staff each morning and can change during the day. Prices shown are shelf prices at <?= e($branch['name']) ?>.</p>
</div>
<?php endif; ?>

<?php page_end();
