<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

require_owner();

$fields = ['name' => 80, 'city' => 80, 'address' => 255, 'phones' => 160, 'email' => 160, 'hours_text' => 255, 'map_url' => 500];
$editId = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);
$editing = $editId ? q_one('SELECT * FROM branches WHERE id = ?', [$editId]) : null;
$values = $editing ?? array_fill_keys(array_keys($fields), '') + ['is_active' => 1, 'sort_order' => 0];
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete' && $editing) {
        q('DELETE FROM branches WHERE id = ?', [$editing['id']]);
        flash('success', "Deleted {$editing['name']} and its stock list and staff logins.");
        redirect(url('admin/branches.php'));
    }

    foreach ($fields as $f => $max) {
        $values[$f] = post_str($f, $max);
    }
    $values['is_active'] = (int)isset($_POST['is_active']);
    $values['sort_order'] = (int)($_POST['sort_order'] ?? 0);

    if ($values['name'] === '') {
        $errors[] = 'Please enter the store name.';
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address doesn\'t look right.';
    }
    if ($values['map_url'] !== '' && !preg_match('#^https://#i', $values['map_url'])) {
        $errors[] = 'The map link must start with https://';
    }

    if (!$errors) {
        $slug = $editing['slug'] ?? slugify($values['name']);
        if (!$editing) {
            $base = $slug;
            for ($i = 2; q_val('SELECT id FROM branches WHERE slug = ?', [$slug]); $i++) {
                $slug = "$base-$i";
            }
        }
        $params = [$values['name'], $values['city'], $values['address'], $values['phones'], $values['email'], $values['hours_text'], $values['map_url'], $values['is_active'], $values['sort_order']];
        if ($editing) {
            q('UPDATE branches SET name = ?, city = ?, address = ?, phones = ?, email = ?, hours_text = ?, map_url = ?, is_active = ?, sort_order = ? WHERE id = ?', [...$params, $editing['id']]);
            flash('success', "Saved {$values['name']}.");
        } else {
            q('INSERT INTO branches (name, city, address, phones, email, hours_text, map_url, is_active, sort_order, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$params, $slug]);
            flash('success', "Added {$values['name']}. Next, create staff logins for it.");
        }
        redirect(url('admin/branches.php'));
    }
}

$all = q_all('SELECT b.*, (SELECT COUNT(*) FROM accounts a WHERE a.branch_id = b.id) AS logins,
                     (SELECT COUNT(*) FROM branch_stock s WHERE s.branch_id = b.id) AS listed
              FROM branches b ORDER BY b.sort_order, b.name');

admin_start('Branches', 'branches');
?>
<div class="admin-head"><div><h1>Branches</h1><p>Your stores. Shoppers pick one of these to see its live stock.</p></div></div>

<div class="table-wrap" style="margin-bottom:1.5rem">
  <table class="table">
    <thead><tr><th>Store</th><th>City</th><th>Products listed</th><th>Staff logins</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($all as $b): ?>
        <tr>
          <td><strong><?= e($b['name']) ?></strong><br><small class="muted">/<?= e($b['slug']) ?></small></td>
          <td><?= e($b['city']) ?></td>
          <td><?= (int)$b['listed'] ?></td>
          <td><?= (int)$b['logins'] ?></td>
          <td><span class="status-dot<?= $b['is_active'] ? '' : ' off' ?>"><?= $b['is_active'] ? 'Live' : 'Hidden' ?></span></td>
          <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/branches.php?edit=' . $b['id'])) ?>#form"><?= icon('pencil', 'icon icon-sm') ?> Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form class="card" method="post" action="<?= e(url('admin/branches.php')) ?>" id="form" style="max-width:820px">
  <h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add a branch' ?></h2>
  <?php foreach ($errors as $err): ?><div class="flash flash-error" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
  <div class="form-grid cols-2">
    <div class="field"><label for="b-name">Store name *</label><input class="input" id="b-name" name="name" value="<?= e($values['name']) ?>" placeholder="e.g. Ikeja" required></div>
    <div class="field"><label for="b-city">City</label><input class="input" id="b-city" name="city" value="<?= e($values['city']) ?>" placeholder="Lagos"></div>
    <div class="field span-2"><label for="b-addr">Address</label><input class="input" id="b-addr" name="address" value="<?= e($values['address']) ?>"></div>
    <div class="field"><label for="b-phones">Phone numbers</label><input class="input" id="b-phones" name="phones" value="<?= e($values['phones']) ?>"><span class="hint">Separate with commas.</span></div>
    <div class="field"><label for="b-email">Email</label><input class="input" id="b-email" name="email" type="email" value="<?= e($values['email']) ?>"></div>
    <div class="field span-2"><label for="b-hours">Opening hours</label><input class="input" id="b-hours" name="hours_text" value="<?= e($values['hours_text']) ?>" placeholder="Mon–Sat 8am–9:30pm · Sun 12pm–9pm"></div>
    <div class="field span-2"><label for="b-map">Google Maps link</label><input class="input" id="b-map" name="map_url" value="<?= e($values['map_url']) ?>" placeholder="https://maps.app.goo.gl/…"><span class="hint">In Google Maps, find the store → Share → Copy link. Leave blank to search by address.</span></div>
    <div class="field"><label for="b-sort">Order in lists</label><input class="input" id="b-sort" name="sort_order" type="number" value="<?= (int)$values['sort_order'] ?>"></div>
    <div class="field"><span class="label">Visibility</span><label class="check"><input type="checkbox" name="is_active" value="1" <?= $values['is_active'] ? 'checked' : '' ?>> Show on the website</label></div>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
    <button class="btn btn-primary" type="submit" name="action" value="save"><?= $editing ? 'Save changes' : 'Add branch' ?></button>
    <?php if ($editing): ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/branches.php')) ?>">Cancel</a>
      <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete <?= e($editing['name']) ?>? Its stock list and staff logins will be deleted too. To just hide it, untick 'Show on the website' instead."><?= icon('trash', 'icon icon-sm') ?> Delete</button>
    <?php endif; ?>
  </div>
</form>
<?php admin_end();
