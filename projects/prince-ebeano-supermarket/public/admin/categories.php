<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

require_owner();

$editId = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);
$editing = $editId ? q_one('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
$values = $editing ?? ['name' => '', 'icon' => 'basket', 'tint' => 'green', 'sort_order' => 0];
$errors = [];

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'delete' && $editing) {
        $count = (int)q_val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$editing['id']]);
        if ($count > 0) {
            flash('error', "{$editing['name']} still has $count product(s). Move or delete them first.");
            redirect(url('admin/categories.php?edit=' . $editing['id']));
        }
        q('DELETE FROM categories WHERE id = ?', [$editing['id']]);
        flash('success', "Deleted {$editing['name']}.");
        redirect(url('admin/categories.php'));
    }

    $values['name'] = post_str('name', 80);
    $values['icon'] = in_array($_POST['icon'] ?? '', CATEGORY_ICONS, true) ? $_POST['icon'] : 'basket';
    $values['tint'] = in_array($_POST['tint'] ?? '', CATEGORY_TINTS, true) ? $_POST['tint'] : 'green';
    $values['sort_order'] = (int)($_POST['sort_order'] ?? 0);

    if ($values['name'] === '') {
        $errors[] = 'Please enter the aisle name.';
    }
    if (!$errors) {
        if ($editing) {
            q('UPDATE categories SET name = ?, icon = ?, tint = ?, sort_order = ? WHERE id = ?', [$values['name'], $values['icon'], $values['tint'], $values['sort_order'], $editing['id']]);
            flash('success', "Saved {$values['name']}.");
        } else {
            $slug = $base = slugify($values['name']);
            for ($i = 2; q_val('SELECT id FROM categories WHERE slug = ?', [$slug]); $i++) {
                $slug = "$base-$i";
            }
            q('INSERT INTO categories (name, slug, icon, tint, sort_order) VALUES (?, ?, ?, ?, ?)', [$values['name'], $slug, $values['icon'], $values['tint'], $values['sort_order']]);
            flash('success', "Added {$values['name']}. Create a staff login for it at each store.");
        }
        redirect(url('admin/categories.php'));
    }
}

$all = q_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products FROM categories c ORDER BY c.sort_order, c.name');

admin_start('Categories', 'categories');
?>
<div class="admin-head"><div><h1>Categories (aisles)</h1><p>The same aisles are used at every store. Each store + aisle pair gets its own staff login.</p></div></div>

<div class="cat-grid" style="margin-bottom:1.5rem">
  <?php foreach ($all as $c): ?>
    <a class="cat-tile tint-<?= e($c['tint']) ?>" href="<?= e(url('admin/categories.php?edit=' . $c['id'])) ?>#form" style="min-height:140px">
      <span class="cat-icon"><?= icon($c['icon']) ?></span>
      <span class="cat-arrow"><?= icon('pencil', 'icon icon-sm') ?></span>
      <strong><?= e($c['name']) ?></strong>
      <span class="cat-count"><?= (int)$c['products'] ?> products</span>
    </a>
  <?php endforeach; ?>
</div>

<form class="card" method="post" action="<?= e(url('admin/categories.php')) ?>" id="form" style="max-width:760px">
  <h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add an aisle' ?></h2>
  <?php foreach ($errors as $err): ?><div class="flash flash-error" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
  <div class="form-grid">
    <div class="form-grid cols-2">
      <div class="field"><label for="c-name">Name *</label><input class="input" id="c-name" name="name" value="<?= e($values['name']) ?>" placeholder="e.g. Drinks" required></div>
      <div class="field"><label for="c-sort">Order in lists</label><input class="input" id="c-sort" name="sort_order" type="number" value="<?= (int)$values['sort_order'] ?>"></div>
    </div>
    <fieldset class="field" style="border:0;padding:0;margin:0">
      <legend class="label" style="margin-bottom:.4rem">Icon</legend>
      <div class="icon-picker">
        <?php foreach (CATEGORY_ICONS as $ic): ?>
          <label><input type="radio" name="icon" value="<?= $ic ?>" <?= $values['icon'] === $ic ? 'checked' : '' ?>><span title="<?= e($ic) ?>"><?= icon($ic, 'icon', $ic) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset class="field" style="border:0;padding:0;margin:0">
      <legend class="label" style="margin-bottom:.4rem">Colour</legend>
      <div class="icon-picker tint-picker">
        <?php foreach (CATEGORY_TINTS as $t): ?>
          <label><input type="radio" name="tint" value="<?= $t ?>" <?= $values['tint'] === $t ? 'checked' : '' ?>><span class="tint-<?= $t ?>" title="<?= e($t) ?>"><span class="visually-hidden"><?= e($t) ?></span></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
    <button class="btn btn-primary" type="submit" name="action" value="save"><?= $editing ? 'Save changes' : 'Add aisle' ?></button>
    <?php if ($editing): ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/categories.php')) ?>">Cancel</a>
      <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete the <?= e($editing['name']) ?> aisle?"><?= icon('trash', 'icon icon-sm') ?> Delete</button>
    <?php endif; ?>
  </div>
</form>
<?php admin_end();
