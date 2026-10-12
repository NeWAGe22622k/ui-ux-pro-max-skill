<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

// The morning update: one screen to set stock + price for every product in one aisle at one store.

$account = require_login();
[$scopeBranch, $scopeCategory] = staff_scope();

if ($scopeBranch === null) {
    $branchId = (int)($_GET['branch'] ?? 0) ?: (int)(branches()[0]['id'] ?? 0);
    $categoryId = (int)($_GET['category'] ?? 0) ?: (int)(categories()[0]['id'] ?? 0);
} else {
    $branchId = $scopeBranch;
    $categoryId = $scopeCategory;
}
$branch = branch_by_id($branchId);
$category = category_by_id($categoryId);

$selfUrl = url('admin/index.php') . (is_owner() ? '?' . http_build_query(['branch' => $branchId, 'category' => $categoryId]) : '');

// ------------------------------------------------------------------ save
if (is_post()) {
    csrf_verify();
    $postBranch = (int)($_POST['branch_id'] ?? 0);
    $postCategory = (int)($_POST['category_id'] ?? 0);
    if (!branch_by_id($postBranch) || !category_by_id($postCategory) || !can_edit_branch_category($postBranch, $postCategory)) {
        abort(403, 'Your login can only update its own store and aisle.');
    }

    $statusIn = is_array($_POST['status'] ?? null) ? $_POST['status'] : [];
    $priceIn = is_array($_POST['price'] ?? null) ? $_POST['price'] : [];
    $who = staff_name();
    $changes = 0;
    $badPrices = [];

    $rows = q_all(
        'SELECT p.id, p.name, s.stock_status, s.price_kobo
         FROM products p LEFT JOIN branch_stock s ON s.product_id = p.id AND s.branch_id = ?
         WHERE p.category_id = ? AND p.is_active = 1',
        [$postBranch, $postCategory]
    );

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $pid = (int)$row['id'];
            $new = $statusIn[$pid] ?? null;
            if (!is_string($new) || !in_array($new, ['none', ...STOCK_STATUSES], true)) {
                continue;
            }
            $oldStatus = $row['stock_status'];
            $oldPrice = $row['price_kobo'] !== null ? (int)$row['price_kobo'] : null;

            if ($new === 'none') {
                if ($oldStatus !== null) {
                    q('DELETE FROM branch_stock WHERE branch_id = ? AND product_id = ?', [$postBranch, $pid]);
                    q('INSERT INTO stock_updates (branch_id, product_id, account_id, staff_name, old_status, new_status, old_price_kobo, new_price_kobo)
                       VALUES (?, ?, ?, ?, ?, NULL, ?, NULL)', [$postBranch, $pid, $account['id'], $who, $oldStatus, $oldPrice]);
                    $changes++;
                }
                continue;
            }

            $rawPrice = is_string($priceIn[$pid] ?? null) ? $priceIn[$pid] : '';
            $newPrice = parse_naira($rawPrice);
            if ($newPrice === null) {
                if (trim($rawPrice) !== '') {
                    $badPrices[] = $row['name'];
                }
                $newPrice = $oldPrice ?? 0;
            }
            if ($new === $oldStatus && $newPrice === $oldPrice) {
                continue;
            }

            q('INSERT INTO branch_stock (branch_id, product_id, stock_status, price_kobo, updated_at, updated_by_account, updated_by_name)
               VALUES (?, ?, ?, ?, NOW(), ?, ?)
               ON DUPLICATE KEY UPDATE stock_status = VALUES(stock_status), price_kobo = VALUES(price_kobo), updated_at = NOW(),
                                       updated_by_account = VALUES(updated_by_account), updated_by_name = VALUES(updated_by_name)',
                [$postBranch, $pid, $new, $newPrice, $account['id'], $who]);
            q('INSERT INTO stock_updates (branch_id, product_id, account_id, staff_name, old_status, new_status, old_price_kobo, new_price_kobo)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$postBranch, $pid, $account['id'], $who, $oldStatus, $new, $oldPrice, $newPrice]);
            $changes++;
        }

        q('INSERT INTO category_reviews (branch_id, category_id, reviewed_at, staff_name) VALUES (?, ?, NOW(), ?)
           ON DUPLICATE KEY UPDATE reviewed_at = NOW(), staff_name = VALUES(staff_name)', [$postBranch, $postCategory, $who]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    $b = branch_by_id($postBranch);
    $c = category_by_id($postCategory);
    flash('success', ($changes ? "Saved $changes change" . ($changes === 1 ? '' : 's') . '. ' : 'No changes needed. ')
        . "{$c['name']} at {$b['name']} is marked as checked today at " . date('g:ia') . '.');
    if ($badPrices) {
        flash('error', 'These prices were not numbers, so the old price was kept: ' . implode(', ', $badPrices) . '.');
    }
    redirect(url('admin/index.php') . (is_owner() ? '?' . http_build_query(['branch' => $postBranch, 'category' => $postCategory]) : ''));
}

// ------------------------------------------------------------------ show
admin_start('Morning update', 'update');

if (!$branch || !$category) {
    echo '<div class="admin-head"><div><h1>Morning update</h1></div></div>';
    echo is_owner()
        ? empty_state('store', 'Add a store and an aisle first', 'Create at least one branch and one category, then come back here to set stock.', '<a class="btn btn-primary" href="' . e(url('admin/branches.php')) . '">Add a branch</a>')
        : empty_state('lock', 'Your login isn\'t linked to an active store', 'Please ask the owner to check your login settings.');
    admin_end();
    exit;
}

$products = q_all(
    'SELECT p.id, p.name, p.description, p.image_path, s.stock_status, s.price_kobo, s.updated_at, s.updated_by_name
     FROM products p LEFT JOIN branch_stock s ON s.product_id = p.id AND s.branch_id = ?
     WHERE p.category_id = ? AND p.is_active = 1
     ORDER BY (s.product_id IS NULL), p.name',
    [$branchId, $categoryId]
);
$review = q_one('SELECT reviewed_at, staff_name FROM category_reviews WHERE branch_id = ? AND category_id = ?', [$branchId, $categoryId]);
$counts = ['in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'none' => 0];
foreach ($products as $p) {
    $counts[$p['stock_status'] ?? 'none']++;
}
$options = [
    'none'         => ['Not sold', 'circle-minus'],
    'in_stock'     => ['In', 'circle-check'],
    'low_stock'    => ['Low', 'circle-alert'],
    'out_of_stock' => ['Out', 'circle-x'],
];
?>
<div class="admin-head">
  <div>
    <p class="eyebrow"><?= e(date('l j F')) ?></p>
    <h1><?= e($category['name']) ?> · <?= e($branch['name']) ?></h1>
    <p><?= $review ? 'Last checked ' . e(fresh_label($review['reviewed_at'])) . ($review['staff_name'] ? ' by ' . e($review['staff_name']) : '') : 'Not checked yet — let\'s do the first one!' ?></p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin/product.php?category=' . $categoryId . (is_owner() ? '&branch=' . $branchId : ''))) ?>"><?= icon('plus', 'icon icon-sm') ?> Add a product</a>
</div>

<?php if (is_owner()): ?>
  <form class="card scope-bar" method="get" action="<?= e(url('admin/index.php')) ?>">
    <div class="field">
      <label for="o-branch">Store</label>
      <select class="select" id="o-branch" name="branch" data-autosubmit>
        <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>" <?= (int)$b['id'] === $branchId ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="o-cat">Aisle</label>
      <select class="select" id="o-cat" name="category" data-autosubmit>
        <?php foreach (categories() as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $categoryId ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <noscript><button class="btn btn-primary" type="submit">Show</button></noscript>
  </form>
<?php endif; ?>

<?php if (!$products): ?>
  <?= empty_state('package', 'No products in this aisle yet', 'Add the products you sell in ' . $category['name'] . '. Every store shares the same product list, and each store sets its own stock and price.',
      '<a class="btn btn-primary" href="' . e(url('admin/product.php?category=' . $categoryId . (is_owner() ? '&branch=' . $branchId : ''))) . '">Add the first product</a>') ?>
<?php else: ?>
  <div class="update-summary">
    <span class="pill pill-in"><?= icon('circle-check', 'icon icon-sm') ?><?= $counts['in_stock'] ?> in stock</span>
    <span class="pill pill-low"><?= icon('circle-alert', 'icon icon-sm') ?><?= $counts['low_stock'] ?> low</span>
    <span class="pill pill-out"><?= icon('circle-x', 'icon icon-sm') ?><?= $counts['out_of_stock'] ?> out</span>
    <span class="pill pill-none"><?= icon('circle-minus', 'icon icon-sm') ?><?= $counts['none'] ?> not sold here</span>
  </div>

  <div class="filter-search" style="margin-bottom:1rem;max-width:420px">
    <?= icon('search') ?>
    <label for="row-filter" class="visually-hidden">Find a product</label>
    <input id="row-filter" type="search" placeholder="Find a product…" data-row-filter>
  </div>

  <form method="post" action="<?= e($selfUrl) ?>" data-update-form>
    <?= csrf_field() ?>
    <input type="hidden" name="branch_id" value="<?= $branchId ?>">
    <input type="hidden" name="category_id" value="<?= $categoryId ?>">

    <div class="update-list">
      <?php foreach ($products as $p):
          $pid = (int)$p['id'];
          $current = $p['stock_status'] ?? 'none';
          $p['category_icon'] = $category['icon'];
          $p['category_tint'] = $category['tint'];
          $priceValue = $p['price_kobo'] !== null && (int)$p['price_kobo'] > 0
              ? number_format((int)$p['price_kobo'] / 100, (int)$p['price_kobo'] % 100 === 0 ? 0 : 2, '.', ',') : ''; ?>
        <div class="update-row" data-row data-name="<?= e(mb_strtolower($p['name'])) ?>">
          <?= product_media($p, 'row-thumb') ?>
          <div class="row-name">
            <strong><?= e($p['name']) ?></strong>
            <small><?= $p['updated_at'] ? 'Changed ' . e(fresh_label($p['updated_at'])) . ($p['updated_by_name'] ? ' by ' . e($p['updated_by_name']) : '') : 'Not listed at this store yet' ?></small>
            · <a href="<?= e(url('admin/product.php?id=' . $pid . (is_owner() ? '&branch=' . $branchId : ''))) ?>">Edit details</a>
          </div>
          <div class="row-controls">
            <div class="seg" role="radiogroup" aria-label="Stock for <?= e($p['name']) ?>">
              <?php foreach ($options as $value => [$label, $ic]): $id = "s-$pid-$value"; ?>
                <input type="radio" id="<?= $id ?>" name="status[<?= $pid ?>]" value="<?= $value ?>" <?= $current === $value ? 'checked' : '' ?>>
                <label for="<?= $id ?>"><?= icon($ic) ?><?= e($label) ?></label>
              <?php endforeach; ?>
            </div>
            <label class="price-input">
              <span>₦</span>
              <input name="price[<?= $pid ?>]" value="<?= e($priceValue) ?>" inputmode="decimal" placeholder="Price" autocomplete="off" data-price aria-label="Price of <?= e($p['name']) ?> in naira">
            </label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="save-bar">
      <p><span class="changed-count" data-changed-count>No changes yet</span></p>
      <button class="btn btn-mango" type="submit"><?= icon('circle-check', 'icon icon-sm') ?> Save &amp; mark <?= e($category['name']) ?> checked</button>
    </div>
  </form>
<?php endif; ?>

<?php admin_end();
