<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$branch = current_branch();
$category = ($slug = get_str('category')) !== '' ? category_by_slug($slug) : null;
$search = get_str('q', 80);
$hideOut = ($_GET['hide_out'] ?? '') === '1';
$sorts = [
    'featured'   => ['Featured', "FIELD(s.stock_status, 'in_stock', 'low_stock', 'out_of_stock'), p.name"],
    'price_asc'  => ['Price: low to high', 's.price_kobo ASC, p.name'],
    'price_desc' => ['Price: high to low', 's.price_kobo DESC, p.name'],
    'name'       => ['Name: A to Z', 'p.name'],
];
$sort = array_key_exists($sortIn = get_str('sort', 20), $sorts) ? $sortIn : 'featured';
$perCategoryPreview = 12;

$groups = [];
$total = 0;
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
        ORDER BY c.sort_order, c.name, " . $sorts[$sort][1] . "
        LIMIT 3000",
        $params
    );
    foreach ($rows as $r) {
        $groups[(int)$r['category_id']][] = $r;
    }
    $total = count($rows);

    foreach (q_all('SELECT category_id, reviewed_at, staff_name FROM category_reviews WHERE branch_id = ?', [$bid]) as $r) {
        $reviews[(int)$r['category_id']] = $r;
    }
}

$browsing = !$category && $search === '';
$title = $category ? $category['name'] : ($search !== '' ? 'Search' : 'Shop');

page_start($title, [
    'active' => 'shop',
    'aisle' => $category ? $category['slug'] : ($search === '' ? 'all' : ''),
    'search' => $search,
    'branch_prompt' => true,
    'description' => $branch
        ? 'What\'s in stock at Prince Ebeano ' . $branch['name'] . ' today — live stock and prices in Naira.'
        : null,
]);
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(url()) ?>">Home</a> <span aria-hidden="true">›</span>
      <?php if ($category || $search !== ''): ?>
        <a href="<?= e(url('shop.php')) ?>">Shop</a> <span aria-hidden="true">›</span>
        <span aria-current="page"><?= $category ? e($category['name']) : 'Search' ?></span>
      <?php else: ?>
        <span aria-current="page">Shop</span>
      <?php endif; ?>
    </nav>
    <h1><?= $search !== '' ? 'Results for “' . e($search) . '”' : ($category ? e($category['name']) : 'All aisles') ?></h1>
    <?php if ($branch): ?>
      <p class="fresh-line">Prices and stock at <?= e($branch['name']) ?>, last checked <span class="fresh-time"><?= e(fresh_label(branch_last_checked((int)$branch['id']))) ?></span>.
        <button type="button" class="btn-link" data-open-branch>Change store</button></p>
    <?php else: ?>
      <p>Choose your store and we'll show you what's on its shelves right now.</p>
    <?php endif; ?>
  </div>
</section>

<?php if (!$branch): ?>
  <div class="container"><?= branch_chooser_inline() ?></div>
<?php else: ?>

<div class="container">
  <form class="toolbar" method="get" action="<?= e(url('shop.php')) ?>">
    <input type="hidden" name="branch" value="<?= e($branch['slug']) ?>">
    <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category['slug']) ?>"><?php endif; ?>
    <input type="hidden" name="q" value="<?= e($search) ?>">
    <p class="toolbar-count"><span data-result-count><?= $total ?></span> product<?= $total === 1 ? '' : 's' ?></p>
    <div class="toolbar-controls">
      <label class="check toggle"><input type="checkbox" name="hide_out" value="1" <?= $hideOut ? 'checked' : '' ?> data-autosubmit> Hide sold out</label>
      <label class="sort">
        <span>Sort by</span>
        <select name="sort" data-autosubmit>
          <?php foreach ($sorts as $key => [$label]): ?>
            <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <noscript><button class="btn btn-ghost btn-sm" type="submit">Apply</button></noscript>
    </div>
  </form>
</div>

<div class="container">
  <?php if ($search !== ''): ?>
    <p class="results-meta"><a href="<?= e(current_url_with(['q' => null])) ?>">Clear search</a></p>
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
        <h2 id="h-<?= e($c['slug']) ?>"<?= $category ? ' class="visually-hidden"' : '' ?>><?= e($c['name']) ?></h2>
        <span class="fresh-ribbon<?= $fresh ? '' : ' is-stale' ?>">
          <span class="chip-dot"></span>
          <?= $review ? 'Freshly checked ' . e(fresh_label($review['reviewed_at'])) . ($review['staff_name'] ? ' by ' . e($review['staff_name']) : '') : 'Not checked yet' ?>
        </span>
      </div>
      <div class="product-grid is-wide">
        <?php foreach ($shown as $p) echo product_card($p, false); ?>
      </div>
      <?php if ($browsing && count($items) > $perCategoryPreview): ?>
        <p style="margin-top:1.25rem"><a class="link-arrow" href="<?= e(current_url_with(['category' => $c['slug']])) ?>">View all <?= count($items) ?> in <?= e($c['name']) ?> <?= icon('chevron-right', 'icon icon-sm') ?></a></p>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

  <p class="muted" style="margin-top:1.5rem;font-size:.88rem">Stock is checked by our staff each morning and can change during the day. Prices shown are shelf prices at <?= e($branch['name']) ?>.</p>
</div>
<?php endif; ?>

<?php page_end();
