<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

// Add or edit a product in the shared catalogue. Staff can only touch their own aisle.

$account = require_login();
[$scopeBranch, $scopeCategory] = staff_scope();
$owner = is_owner();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$product = $id ? q_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) {
    abort(404, 'That product no longer exists.');
}
if ($product && !can_edit_category((int)$product['category_id'])) {
    abort(403, 'This product is in another aisle, so your login can\'t change it.');
}

// The store whose stock we set when creating (staff: their own; owner: from ?branch=).
$branchId = $scopeBranch ?? (int)($_GET['branch'] ?? $_POST['branch_id'] ?? 0);
$branch = $branchId ? branch_by_id($branchId) : null;

$values = [
    'name'        => $product['name'] ?? '',
    'description' => $product['description'] ?? '',
    'category_id' => (int)($product['category_id'] ?? ($scopeCategory ?? (int)($_GET['category'] ?? 0))),
    'is_active'   => (int)($product['is_active'] ?? 1),
    'status'      => 'in_stock',
    'price'       => '',
];
$errors = [];

$backUrl = url('admin/index.php') . ($owner ? '?' . http_build_query(['branch' => $branchId ?: null, 'category' => $values['category_id'] ?: null]) : '');

if (is_post()) {
    csrf_verify();

    // ---- delete (owner only)
    if (($_POST['action'] ?? '') === 'delete') {
        if (!$owner || !$product) {
            abort(403, 'Only the owner can delete products.');
        }
        q('DELETE FROM products WHERE id = ?', [$product['id']]);
        delete_upload($product['image_path']);
        flash('success', "“{$product['name']}” was deleted from every store.");
        redirect($backUrl);
    }

    $values['name'] = post_str('name', 160);
    $values['description'] = post_str('description', 500);
    $values['category_id'] = $owner ? (int)($_POST['category_id'] ?? 0) : $scopeCategory;
    $values['is_active'] = $owner ? (int)isset($_POST['is_active']) : (int)($product['is_active'] ?? 1);
    $values['status'] = post_str('status', 20);
    $values['price'] = post_str('price', 20);

    if ($values['name'] === '') {
        $errors['name'] = 'Please enter the product name.';
    }
    if (!category_by_id($values['category_id'])) {
        $errors['category_id'] = 'Please choose an aisle.';
    }
    if (!$errors) {
        $dupe = q_val('SELECT id FROM products WHERE category_id = ? AND LOWER(name) = LOWER(?) AND id <> ?', [$values['category_id'], $values['name'], $id]);
        if ($dupe) {
            $errors['name'] = 'A product with this name already exists in this aisle.';
        }
    }

    $newImage = null;
    if (!$errors && has_upload('photo')) {
        try {
            $newImage = save_uploaded_image($_FILES['photo'], 'products');
        } catch (RuntimeException $ex) {
            $errors['photo'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $imagePath = $product['image_path'] ?? null;
        if ($newImage) {
            delete_upload($imagePath);
            $imagePath = $newImage;
        } elseif (isset($_POST['remove_photo'])) {
            delete_upload($imagePath);
            $imagePath = null;
        }

        if ($product) {
            q('UPDATE products SET name = ?, description = ?, category_id = ?, image_path = ?, is_active = ? WHERE id = ?',
                [$values['name'], $values['description'], $values['category_id'], $imagePath, $values['is_active'], $product['id']]);
            flash('success', "Saved “{$values['name']}”.");
        } else {
            q('INSERT INTO products (category_id, name, description, image_path, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                [$values['category_id'], $values['name'], $values['description'], $imagePath, $account['id'], $values['is_active']]);
            $newId = (int)db()->lastInsertId();

            // Set stock at this store straight away, if a status was picked.
            if ($branch && in_array($values['status'], STOCK_STATUSES, true)) {
                $price = parse_naira($values['price']) ?? 0;
                q('INSERT INTO branch_stock (branch_id, product_id, stock_status, price_kobo, updated_by_account, updated_by_name) VALUES (?, ?, ?, ?, ?, ?)',
                    [$branch['id'], $newId, $values['status'], $price, $account['id'], staff_name()]);
                q('INSERT INTO stock_updates (branch_id, product_id, account_id, staff_name, old_status, new_status, old_price_kobo, new_price_kobo) VALUES (?, ?, ?, ?, NULL, ?, NULL, ?)',
                    [$branch['id'], $newId, $account['id'], staff_name(), $values['status'], $price]);
            }
            flash('success', "Added “{$values['name']}”" . ($branch ? " and listed it at {$branch['name']}." : '. Set its stock at each store from the morning update.'));
        }
        redirect(url('admin/index.php') . ($owner ? '?' . http_build_query(['branch' => $branchId ?: null, 'category' => $values['category_id']]) : ''));
    } elseif ($newImage) {
        delete_upload($newImage);
    }
}

$cat = category_by_id($values['category_id']);
$preview = ['image_path' => $product['image_path'] ?? null, 'category_icon' => $cat['icon'] ?? 'package', 'category_tint' => $cat['tint'] ?? 'green'];
$title = $product ? 'Edit product' : 'Add a product';

admin_start($title, 'update');
?>
<div class="admin-head">
  <div>
    <p class="eyebrow"><?= e($cat['name'] ?? 'Catalogue') ?></p>
    <h1><?= e($title) ?></h1>
    <p>Products are shared by every store. Each store sets its own stock and price in the morning update.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e($backUrl) ?>">← Back to morning update</a>
</div>

<form class="card" method="post" enctype="multipart/form-data" action="<?= e(url('admin/product.php')) ?>" style="max-width:760px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">

  <div class="form-grid">
    <div class="field">
      <label for="p-name">Product name *</label>
      <input class="input" id="p-name" name="name" value="<?= e($values['name']) ?>" required maxlength="160" placeholder="e.g. Nestlé Milo 400g tin" <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
      <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php else: ?><span class="hint">Include the brand and size so shoppers know exactly what it is.</span><?php endif; ?>
    </div>

    <div class="field">
      <label for="p-desc">Short description</label>
      <input class="input" id="p-desc" name="description" value="<?= e($values['description']) ?>" maxlength="500" placeholder="Optional — e.g. Chocolate malt drink">
    </div>

    <?php if ($owner): ?>
      <div class="field">
        <label for="p-cat">Aisle *</label>
        <select class="select" id="p-cat" name="category_id">
          <?php foreach (categories() as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $values['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?><span class="field-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="field">
      <span class="label">Photo</span>
      <div class="photo-field">
        <div class="photo-preview tint-<?= e($preview['category_tint']) ?>" id="photo-preview">
          <?php if ($preview['image_path']): ?><img src="<?= e(url($preview['image_path'])) ?>" alt=""><?php else: ?><?= icon($preview['category_icon'], 'icon media-icon') ?><?php endif; ?>
        </div>
        <div class="field" style="flex:1;min-width:200px">
          <input class="input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="photo-preview" aria-label="Choose a photo">
          <span class="hint">A clear photo on a plain background works best. JPG, PNG or WebP, up to 3MB. We resize it for you.</span>
          <?php if (isset($errors['photo'])): ?><span class="field-error"><?= e($errors['photo']) ?></span><?php endif; ?>
          <?php if ($preview['image_path']): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!$product && $branch): ?>
      <fieldset class="card" style="background:var(--cream);border-style:dashed">
        <legend class="label" style="font-weight:800;padding:0 .4rem">At <?= e($branch['name']) ?> today</legend>
        <div class="form-grid cols-2">
          <div class="field">
            <label for="p-status">Stock</label>
            <select class="select" id="p-status" name="status">
              <option value="in_stock" <?= $values['status'] === 'in_stock' ? 'selected' : '' ?>>In stock</option>
              <option value="low_stock" <?= $values['status'] === 'low_stock' ? 'selected' : '' ?>>Low stock</option>
              <option value="out_of_stock" <?= $values['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
              <option value="none" <?= $values['status'] === 'none' ? 'selected' : '' ?>>Not sold at this store</option>
            </select>
          </div>
          <div class="field">
            <label for="p-price">Price</label>
            <span class="price-input"><span>₦</span><input id="p-price" name="price" value="<?= e($values['price']) ?>" inputmode="decimal" placeholder="e.g. 3,250"></span>
          </div>
        </div>
      </fieldset>
    <?php endif; ?>

    <?php if ($owner): ?>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $values['is_active'] ? 'checked' : '' ?>> Show this product on the website</label>
    <?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;gap:.5rem;justify-content:space-between">
      <button class="btn btn-primary" type="submit"><?= icon('circle-check', 'icon icon-sm') ?> <?= $product ? 'Save changes' : 'Add product' ?></button>
    </div>
  </div>
</form>

<?php if ($product && $owner): ?>
  <form method="post" action="<?= e(url('admin/product.php')) ?>" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
    <input type="hidden" name="action" value="delete">
    <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete “<?= e($product['name']) ?>” from every store? This can't be undone."><?= icon('trash', 'icon icon-sm') ?> Delete product</button>
  </form>
<?php endif; ?>

<?php admin_end();
