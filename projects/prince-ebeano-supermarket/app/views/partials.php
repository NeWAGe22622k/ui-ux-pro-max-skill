<?php
declare(strict_types=1);

function status_pill(string $status, string $extraClass = ''): string
{
    $m = status_meta($status);
    return '<span class="pill pill-' . e($m['class']) . ($extraClass ? ' ' . e($extraClass) : '') . '">' . icon($m['icon'], 'icon icon-sm') . e($m['label']) . '</span>';
}

/** Product photo, or a tinted tile with the category icon when there is no photo yet. */
function product_media(array $p, string $class = 'product-media'): string
{
    $tint = $p['category_tint'] ?? 'green';
    $html = '<div class="' . e($class) . ' tint-' . e($tint) . '">';
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
function product_card(array $p, bool $showCategory = true): string
{
    $status = $p['stock_status'];
    $out = $status === 'out_of_stock';
    ob_start(); ?>
<article class="product-card<?= $out ? ' is-out' : '' ?>" data-reveal>
  <?= product_media($p) ?>
  <div class="product-body">
    <?php if ($showCategory): ?><p class="product-cat"><?= e($p['category_name']) ?></p><?php endif; ?>
    <h3 class="product-name"><?= e($p['name']) ?></h3>
    <?php if (!empty($p['description'])): ?><p class="product-desc"><?= e($p['description']) ?></p><?php endif; ?>
    <div class="product-foot">
      <span class="price"><?= $p['price_kobo'] > 0 ? e(naira((int)$p['price_kobo'])) : '<span class="price-ask">Ask in store</span>' ?></span>
      <?= status_pill($status) ?>
    </div>
  </div>
</article>
<?php
    return (string)ob_get_clean();
}

function section_head(string $eyebrow, string $title, string $sub = '', string $linkHref = '', string $linkLabel = ''): string
{
    $html = '<div class="section-head"><div><p class="eyebrow">' . e($eyebrow) . '</p><h2>' . e($title) . '</h2>';
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
        <span class="branch-option-icon"><?= icon('store') ?></span>
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
