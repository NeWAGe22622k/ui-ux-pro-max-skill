<?php
require __DIR__ . '/../includes/admin.php';
require_login();

$list = valid_list((string) ($_GET['list'] ?? $_POST['list'] ?? 'properties'));
$isRentals = $list === 'rentals';
$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$items = load_json($list, []);
$index = $id !== '' ? find_index($items, $id) : null;
if ($id !== '' && $index === null) {
    flash('That item no longer exists.', 'error');
    header('Location: index.php?list=' . $list);
    exit;
}

$blank = $isRentals
    ? ['id' => '', 'title' => '', 'location' => '', 'price_pcm' => '', 'bedrooms' => '', 'bathrooms' => '', 'property_type' => '',
       'furnished' => '', 'available_from' => 'Available now', 'deposit' => '', 'description' => '', 'features' => [],
       'images' => [], 'links' => [], 'status' => 'available', 'published' => true]
    : ['id' => '', 'type' => 'managed', 'title' => '', 'location' => '', 'summary' => '',
       'images' => [], 'before' => [], 'after' => [], 'published' => true];
$item = $index !== null ? array_merge($blank, $items[$index]) : $blank;
$errors = [];

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $str = fn($k, $max = 300) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
    $old = $item;

    $item['title'] = $str('title', 140);
    $item['location'] = $str('location', 140);
    $item['published'] = !empty($_POST['published']);
    if ($item['title'] === '') $errors[] = 'Please enter a title.';

    $groups = $isRentals ? ['images'] : ['images', 'before', 'after'];
    foreach ($groups as $g) {
        [$new, $upErrors] = store_uploads('new_' . $g);
        $item[$g] = array_values(array_unique(array_merge(posted_paths('keep_' . $g), $new)));
        $errors = array_merge($errors, $upErrors);
    }

    if ($isRentals) {
        $item['price_pcm'] = max(0, (int) preg_replace('/[^0-9]/', '', $str('price_pcm', 12)));
        $item['bedrooms'] = max(0, min(20, (int) $str('bedrooms', 3)));
        $item['bathrooms'] = max(0, min(20, (int) $str('bathrooms', 3)));
        $item['property_type'] = $str('property_type', 60);
        $item['furnished'] = $str('furnished', 60);
        $item['available_from'] = $str('available_from', 60);
        $item['deposit'] = $str('deposit', 60);
        $item['description'] = $str('description', 6000);
        $item['features'] = array_values(array_filter(array_map('trim', explode("\n", $str('features', 4000)))));
        $item['status'] = ($_POST['status'] ?? '') === 'let' ? 'let' : 'available';
        $links = [];
        foreach ((array) ($_POST['link_platform'] ?? []) as $k => $platform) {
            $url = trim((string) ($_POST['link_url'][$k] ?? ''));
            if ($url === '') continue;
            if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
            if (!filter_var($url, FILTER_VALIDATE_URL)) { $errors[] = 'This link is not a valid web address: ' . $url; continue; }
            $links[] = ['platform' => mb_substr(trim((string) $platform), 0, 40) ?: 'View listing', 'url' => $url];
        }
        $item['links'] = $links;
    } else {
        $item['type'] = ($_POST['type'] ?? '') === 'flip' ? 'flip' : 'managed';
        $item['summary'] = $str('summary', 600);
        if ($item['type'] === 'flip' && $item['published'] && (!$item['before'] || !$item['after'])) {
            $errors[] = 'A before & after project needs at least one “before” and one “after” photo.';
        }
    }

    if (!$errors) {
        $isNew = $index === null;
        if ($isNew) {
            $item['id'] = make_id($item['title']);
            array_unshift($items, $item); // newest first
        } else {
            $items[$index] = $item;
        }
        if (save_json($list, array_values($items))) {
            $oldPaths = array_merge($old['images'] ?? [], $old['before'] ?? [], $old['after'] ?? []);
            remove_unused($oldPaths);
            flash('“' . $item['title'] . '” ' . ($isNew ? 'added.' : 'saved.') . ($item['published'] ? '' : ' It is hidden until you publish it.'));
            header('Location: index.php?list=' . $list);
            exit;
        }
        $errors[] = 'Could not save. Check that the /data folder is writable.';
    }
}

/* ---------- Form ---------- */
$isEdit = $index !== null;
$noun = $isRentals ? 'rental' : 'property';
admin_head(($isEdit ? 'Edit ' : 'Add ') . $noun, $list);

/** Sortable photo group: existing photos + file picker for new ones. */
function photo_group(string $key, string $label, array $paths, string $help = ''): void
{
    ?>
    <fieldset class="photos" data-photos>
      <legend><?= h($label) ?></legend>
      <?php if ($help): ?><p class="hint"><?= h($help) ?></p><?php endif; ?>
      <ul class="photo-list" data-sortable>
        <?php foreach ($paths as $p): ?>
          <li class="photo" draggable="true">
            <img src="<?= h(admin_src($p)) ?>" alt="" loading="lazy" decoding="async">
            <input type="hidden" name="keep_<?= $key ?>[]" value="<?= h($p) ?>">
            <button type="button" class="photo-remove" data-remove aria-label="Remove photo">×</button>
          </li>
        <?php endforeach; ?>
      </ul>
      <label class="drop">
        <input type="file" name="new_<?= $key ?>[]" accept="image/jpeg,image/png,image/webp" multiple data-file>
        <span><strong>+ Add photos</strong> or drag them here</span>
      </label>
      <ul class="photo-list photo-new" data-new-previews></ul>
    </fieldset>
    <?php
}
?>
<div class="page-head">
  <div>
    <p class="crumb"><a href="index.php?list=<?= $list ?>">← <?= h(LISTS[$list]) ?></a></p>
    <h1><?= $isEdit ? 'Edit ' . h($item['title']) : 'Add a ' . $noun ?></h1>
  </div>
</div>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="editor" data-editor>
  <?= csrf_field() ?>
  <input type="hidden" name="list" value="<?= $list ?>">
  <input type="hidden" name="id" value="<?= h($item['id']) ?>">

  <div class="editor-main">
    <section class="panel">
      <h2>Details</h2>
      <?php if (!$isRentals): ?>
        <div class="choice" role="radiogroup" aria-label="Type">
          <label><input type="radio" name="type" value="managed" data-type-switch<?= $item['type'] !== 'flip' ? ' checked' : '' ?>> <span><strong>Managed property</strong><small>A property in our care, with a photo gallery</small></span></label>
          <label><input type="radio" name="type" value="flip" data-type-switch<?= $item['type'] === 'flip' ? ' checked' : '' ?>> <span><strong>Before &amp; after</strong><small>A refurbishment / flip project</small></span></label>
        </div>
      <?php endif; ?>
      <label><span>Title <span class="req">*</span></span><input type="text" name="title" required maxlength="140" value="<?= h($item['title']) ?>" placeholder="<?= $isRentals ? 'e.g. Two-bedroom terraced house' : 'e.g. Family home refurbishment' ?>"></label>
      <label>Location<input type="text" name="location" maxlength="140" value="<?= h($item['location']) ?>" placeholder="e.g. Harlow, Essex"></label>

      <?php if ($isRentals): ?>
        <div class="cols-3">
          <label>Rent (£ per month)<input type="number" name="price_pcm" min="0" step="1" inputmode="numeric" value="<?= h((string) $item['price_pcm']) ?>"></label>
          <label>Bedrooms<input type="number" name="bedrooms" min="0" max="20" value="<?= h((string) $item['bedrooms']) ?>"></label>
          <label>Bathrooms<input type="number" name="bathrooms" min="0" max="20" value="<?= h((string) $item['bathrooms']) ?>"></label>
        </div>
        <div class="cols-2">
          <label>Property type
            <input type="text" name="property_type" list="types" value="<?= h($item['property_type']) ?>" placeholder="House, Flat…">
            <datalist id="types"><option>House</option><option>Flat</option><option>Apartment</option><option>Maisonette</option><option>Bungalow</option><option>Studio</option><option>Room</option></datalist>
          </label>
          <label>Furnishing
            <select name="furnished">
              <?php foreach (['', 'Furnished', 'Part furnished', 'Unfurnished', 'Furnished or unfurnished'] as $f): ?>
                <option value="<?= h($f) ?>"<?= $item['furnished'] === $f ? ' selected' : '' ?>><?= $f === '' ? '—' : h($f) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Available from<input type="text" name="available_from" maxlength="60" value="<?= h($item['available_from']) ?>" placeholder="Available now, or e.g. 1 December"></label>
          <label>Deposit<input type="text" name="deposit" maxlength="60" value="<?= h($item['deposit']) ?>" placeholder="e.g. £1,673"></label>
        </div>
        <label>Description<textarea name="description" rows="7" placeholder="Describe the home. Leave a blank line between paragraphs."><?= h($item['description']) ?></textarea></label>
        <label><span>Key features <small>(one per line)</small></span><textarea name="features" rows="5" placeholder="Private garden&#10;Off-street parking"><?= h(implode("\n", $item['features'])) ?></textarea></label>
      <?php else: ?>
        <label>Short description<textarea name="summary" rows="3" maxlength="600" placeholder="One or two sentences shown on the card and in the gallery."><?= h($item['summary']) ?></textarea></label>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Photos</h2>
      <p class="hint">Drag photos to reorder them — the first photo is the cover. Large photos are resized automatically.</p>
      <?php if ($isRentals): ?>
        <?php photo_group('images', 'Photos', $item['images']); ?>
      <?php else: ?>
        <div data-for-type="managed"><?php photo_group('images', 'Gallery photos', $item['images']); ?></div>
        <div data-for-type="flip">
          <?php photo_group('before', 'Before photos', $item['before'], 'Taken before the work started.'); ?>
          <?php photo_group('after', 'After photos', $item['after'], 'The finished result. The first one is paired with the first “before” photo on the card slider.'); ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($isRentals): ?>
      <section class="panel">
        <h2>Where it is advertised</h2>
        <p class="hint">Add a link for each website the home is listed on. Visitors will see a button for each one.</p>
        <div class="links" data-links>
          <?php $links = $item['links'] ?: [['platform' => 'Rightmove', 'url' => '']]; ?>
          <?php foreach ($links as $l): ?>
            <div class="link-row" data-link-row>
              <input type="text" name="link_platform[]" list="platforms" value="<?= h($l['platform']) ?>" placeholder="Website" aria-label="Website name">
              <input type="text" inputmode="url" name="link_url[]" value="<?= h($l['url']) ?>" placeholder="https://www.rightmove.co.uk/properties/…" aria-label="Listing link">
              <button type="button" class="icon" data-link-remove aria-label="Remove link">×</button>
            </div>
          <?php endforeach; ?>
        </div>
        <datalist id="platforms"><option>Rightmove</option><option>Zoopla</option><option>OnTheMarket</option><option>OpenRent</option><option>SpareRoom</option><option>Facebook Marketplace</option></datalist>
        <button type="button" class="btn btn-sm" data-link-add>+ Add another link</button>
      </section>
    <?php endif; ?>
  </div>

  <aside class="editor-side">
    <section class="panel sticky">
      <h2>Visibility</h2>
      <label class="check"><input type="checkbox" name="published" value="1"<?= $item['published'] ? ' checked' : '' ?>> Show on the website</label>
      <?php if ($isRentals): ?>
        <fieldset class="radio-list">
          <legend>Status</legend>
          <label class="check"><input type="radio" name="status" value="available"<?= $item['status'] !== 'let' ? ' checked' : '' ?>> Available</label>
          <label class="check"><input type="radio" name="status" value="let"<?= $item['status'] === 'let' ? ' checked' : '' ?>> Let agreed</label>
        </fieldset>
      <?php endif; ?>
      <button class="btn btn-primary btn-block" type="submit" data-save><?= $isEdit ? 'Save changes' : 'Add ' . $noun ?></button>
      <a class="btn btn-block" href="index.php?list=<?= $list ?>">Cancel</a>
      <p class="hint" data-upload-status aria-live="polite"></p>
    </section>
  </aside>
</form>
<?php admin_foot(); ?>
