<?php
declare(strict_types=1);

function status_pill(string $status, string $extraClass = ''): string
{
    $m = status_meta($status);
    return '<span class="pill pill-' . e($m['class']) . ($extraClass ? ' ' . e($extraClass) : '') . '">' . icon($m['icon'], 'icon icon-sm') . e($m['label']) . '</span>';
}

/**
 * Product photo, or a quiet placeholder with a small category icon when there is no photo yet.
 * $tinted keeps the coloured category tint (used only by the small thumbnails in the hero card).
 */
function product_media(array $p, string $class = 'product-media', bool $tinted = false): string
{
    $classes = $class;
    if ($tinted) {
        $classes .= ' tint-' . ($p['category_tint'] ?? 'green');
    } elseif (empty($p['image_path'])) {
        $classes .= ' is-blank';
    }
    $html = '<div class="' . e($classes) . '">';
    if (!empty($p['image_path'])) {
        $html .= '<img src="' . e(url($p['image_path'])) . '" alt="" loading="lazy" decoding="async" width="400" height="400">';
    } else {
        $html .= icon($p['category_icon'] ?? 'package', 'icon media-icon');
    }
    return $html . '</div>';
}

/**
 * $p needs: name, price_kobo, stock_status, image_path, category_name, category_icon, category_tint
 */
/** Small tag on the photo: only for items that need a shopper's attention. */
function product_badge(string $status): string
{
    if ($status === 'low_stock') {
        return '<span class="product-badge">Few left</span>';
    }
    if ($status === 'out_of_stock') {
        return '<span class="product-badge is-out">Sold out</span>';
    }
    return '';
}

function product_card(array $p, bool $showCategory = true): string
{
    $status = $p['stock_status'];
    $out = $status === 'out_of_stock';
    // No photo yet: show the aisle's illustration as a placeholder.
    $media = empty($p['image_path'])
        ? '<div class="product-media is-blank">' . art(category_art(['icon' => $p['category_icon'] ?? '']), '', 'media-art') . '</div>'
        : product_media($p);
    $media = substr($media, 0, strpos($media, '>') + 1) . product_badge($status) . substr($media, strpos($media, '>') + 1);
    ob_start(); ?>
<article class="product-card<?= $out ? ' is-out' : '' ?>" data-pid="<?= (int)$p['id'] ?>" data-price="<?= (int)$p['price_kobo'] ?>" data-status="<?= e($status) ?>">
  <?= $media ?>
  <div class="product-body">
    <?php if ($showCategory): ?><p class="product-cat"><?= e($p['category_name']) ?></p><?php endif; ?>
    <h3 class="product-name"><?= e($p['name']) ?></h3>
    <?php if (!empty($p['description'])): ?><p class="product-desc"><?= e($p['description']) ?></p><?php endif; ?>
    <p class="price"><?= $p['price_kobo'] > 0 ? e(naira((int)$p['price_kobo'])) : '<span class="price-ask">Ask in store</span>' ?></p>
  </div>
  <div class="product-status"><?= status_pill($status) ?></div>
</article>
<?php
    return (string)ob_get_clean();
}

function section_head(string $eyebrow, string $title, string $sub = '', string $linkHref = '', string $linkLabel = ''): string
{
    // $eyebrow is kept in the signature for existing callers but no longer shown.
    $html = '<div class="section-head"><div><h2>' . e($title) . '</h2>';
    if ($sub !== '') {
        $html .= '<p class="section-sub">' . e($sub) . '</p>';
    }
    $html .= '</div>';
    if ($linkHref !== '') {
        $html .= '<a class="link-arrow" href="' . e($linkHref) . '">' . e($linkLabel) . icon('arrow-right', 'icon icon-sm') . '</a>';
    }
    return $html . '</div>';
}

function empty_state(string $iconName, string $title, string $text, string $actionHtml = ''): string
{
    return '<div class="empty-state"><span class="empty-icon">' . icon($iconName) . '</span><h3>' . e($title) . '</h3><p>' . e($text) . '</p>' . $actionHtml . '</div>';
}

/** Inline branch chooser for pages that need a branch before showing stock (works without JS). */
function branch_chooser_inline(string $heading = 'Pick your store to see what\'s in stock'): string
{
    ob_start(); ?>
<div class="branch-chooser">
  <h2><?= e($heading) ?></h2>
  <div class="branch-chooser-grid">
    <?php foreach (branches() as $b): ?>
      <a class="branch-option" href="<?= e(current_url_with(['branch' => $b['slug']])) ?>">
        <span class="branch-option-text"><strong><?= e($b['name']) ?></strong><small><?= e($b['city']) ?></small></span>
        <?= icon('chevron-right', 'icon icon-sm') ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php
    return (string)ob_get_clean();
}

/** Shared SELECT for public product listings at a branch. */
function stock_select_sql(): string
{
    return 'SELECT p.id, p.name, p.description, p.image_path,
                   s.stock_status, s.price_kobo, s.updated_at,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
                   c.icon AS category_icon, c.tint AS category_tint
            FROM branch_stock s
            JOIN products p   ON p.id = s.product_id AND p.is_active = 1
            JOIN categories c ON c.id = p.category_id';
}

/** A decorative illustration from assets/img/art/ (Fluent Emoji 3D, MIT licence). */
function art(string $name, string $style = '', string $class = 'art'): string
{
    return '<img class="' . e($class) . '" src="' . e(url('assets/img/art/' . $name . '.webp')) . '" alt="" width="256" height="256" decoding="async"'
        . ($style !== '' ? ' style="' . e($style) . '"' : '') . '>';
}

/**
 * A banner illustration: each item is [name, x%, y%, width%, rotation deg] inside a box with a fixed shape,
 * so the arrangement keeps its proportions at every screen size.
 */
function art_scene(array $items, string $class = 'banner-art'): string
{
    $html = '<span class="' . e($class) . '" aria-hidden="true">';
    foreach ($items as [$name, $x, $y, $w, $r]) {
        $html .= art($name, "--x:{$x}%;--y:{$y}%;--w:{$w}%;--r:{$r}deg");
    }
    return $html . '</span>';
}

/**
 * A banner photo from assets/img/photos/ (Pexels, see CREDITS.txt). It fills one side of the banner
 * and fades into the banner's tint so the text side stays calm and readable.
 */
function banner_photo(string $name, string $position = 'center', bool $eager = false): string
{
    return '<span class="banner-media" aria-hidden="true"><img src="' . e(url('assets/img/photos/' . $name . '.webp')) . '" alt=""'
        . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async" style="object-position:' . e($position) . '"></span>';
}

/** Illustration for an aisle, chosen by the aisle's icon so new aisles get one too. */
function category_art(array $c): string
{
    $byIcon = [
        'cup-soda' => 'cup', 'wheat' => 'cereal', 'milk' => 'milk', 'sparkles' => 'sponge', 'spray-can' => 'lotion',
        'baby' => 'babybottle', 'croissant' => 'bread', 'wine' => 'wine', 'apple' => 'apple', 'cookie' => 'cookie',
        'gift' => 'gift', 'basket' => 'basket', 'package' => 'basket', 'heart' => 'strawberry',
    ];
    return $byIcon[$c['icon'] ?? ''] ?? 'basket';
}

/** Horizontal product row with previous/next buttons (buttons are added by app.js). */
function product_rail(array $products, string $label): string
{
    $html = '<div class="rail-wrap"><div class="rail" tabindex="0" aria-label="' . e($label) . '">';
    foreach ($products as $p) {
        $html .= product_card($p);
    }
    return $html . '</div></div>';
}
