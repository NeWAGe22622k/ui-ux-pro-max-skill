<?php
/** Listing card partials. Each card opens the shared modal (assets/js/site.js). */

function card_meta(array $i): string
{
    $bits = [];
    if (!empty($i['bedrooms'])) {
        $bits[] = icon('bed', 16) . ' ' . e($i['bedrooms']) . ' bed';
    }
    if (!empty($i['bathrooms'])) {
        $bits[] = icon('bath', 16) . ' ' . e($i['bathrooms']) . ' bath';
    }
    if (!empty($i['property_type'])) {
        $bits[] = icon('home', 16) . ' ' . e($i['property_type']);
    }
    if (!$bits) {
        return '';
    }
    return '<ul class="card-meta"><li>' . implode('</li><li>', $bits) . '</li></ul>';
}

function sample_badge(array $i): string
{
    return !empty($i['placeholder']) ? '<span class="badge badge-sample">Sample listing</span>' : '';
}

function property_card(array $i, string $collection = 'managed'): void
{
    $images = $i['images'] ?? [];
    $cover = $images[0] ?? 'assets/img/placeholders/managed-1-exterior.svg';
    ?>
    <article class="card">
      <div class="card-media">
        <img src="<?= e(img($cover)) ?>" alt="" loading="lazy">
        <?= sample_badge($i) ?>
        <span class="card-count"><?= icon('images', 14) ?> <?= count($images) ?> photo<?= count($images) === 1 ? '' : 's' ?></span>
      </div>
      <div class="card-body">
        <?php if (!empty($i['location'])): ?><p class="card-location"><?= icon('pin', 14) ?> <?= e($i['location']) ?></p><?php endif; ?>
        <h3 class="card-title">
          <button type="button" class="card-link" data-open="<?= e($i['id']) ?>" data-collection="<?= e($collection) ?>" aria-haspopup="dialog"><?= e($i['title']) ?></button>
        </h3>
        <?= card_meta($i) ?>
        <?php if (!empty($i['summary'])): ?><p class="card-summary"><?= e($i['summary']) ?></p><?php endif; ?>
        <span class="card-cta" aria-hidden="true">View photos <?= icon('arrow', 16) ?></span>
      </div>
    </article>
    <?php
}

function flip_card(array $i): void
{
    $before = $i['before'][0] ?? 'assets/img/placeholders/flip-1-before-exterior.svg';
    $after = $i['after'][0] ?? 'assets/img/placeholders/flip-1-after-exterior.svg';
    ?>
    <article class="card card-flip">
      <div class="card-media card-media-split">
        <figure><img src="<?= e(img($before)) ?>" alt="" loading="lazy"><figcaption class="tag tag-before">Before</figcaption></figure>
        <figure><img src="<?= e(img($after)) ?>" alt="" loading="lazy"><figcaption class="tag tag-after">After</figcaption></figure>
        <?= sample_badge($i) ?>
      </div>
      <div class="card-body">
        <?php if (!empty($i['location'])): ?><p class="card-location"><?= icon('pin', 14) ?> <?= e($i['location']) ?></p><?php endif; ?>
        <h3 class="card-title">
          <button type="button" class="card-link" data-open="<?= e($i['id']) ?>" data-collection="flips" aria-haspopup="dialog"><?= e($i['title']) ?></button>
        </h3>
        <?= card_meta($i) ?>
        <?php if (!empty($i['summary'])): ?><p class="card-summary"><?= e($i['summary']) ?></p><?php endif; ?>
        <span class="card-cta" aria-hidden="true">See the transformation <?= icon('arrow', 16) ?></span>
      </div>
    </article>
    <?php
}

function rental_card(array $i): void
{
    $images = $i['images'] ?? [];
    $cover = $images[0] ?? 'assets/img/placeholders/rental-1-exterior.svg';
    $let = ($i['status'] ?? 'available') === 'let_agreed';
    ?>
    <article class="card card-rental<?= $let ? ' is-let' : '' ?>" data-status="<?= $let ? 'let_agreed' : 'available' ?>">
      <div class="card-media">
        <img src="<?= e(img($cover)) ?>" alt="" loading="lazy">
        <?= sample_badge($i) ?>
        <span class="badge <?= $let ? 'badge-let' : 'badge-available' ?>"><?= $let ? 'Let agreed' : 'Available' ?></span>
        <span class="card-count"><?= icon('images', 14) ?> <?= count($images) ?></span>
      </div>
      <div class="card-body">
        <?php if (!empty($i['price'])): ?><p class="card-price"><?= e($i['price']) ?></p><?php endif; ?>
        <h3 class="card-title">
          <button type="button" class="card-link" data-open="<?= e($i['id']) ?>" data-collection="rentals" aria-haspopup="dialog"><?= e($i['title']) ?></button>
        </h3>
        <?php if (!empty($i['location'])): ?><p class="card-location"><?= icon('pin', 14) ?> <?= e($i['location']) ?></p><?php endif; ?>
        <?= card_meta($i) ?>
        <?php if (!empty($i['available_from'])): ?><p class="card-available"><?= icon('calendar', 14) ?> Available <?= e($i['available_from']) ?></p><?php endif; ?>
        <span class="card-cta" aria-hidden="true">View full details <?= icon('arrow', 16) ?></span>
      </div>
    </article>
    <?php
}

/** The one modal used by every listing page; content is filled in by site.js. */
function listing_modal(): void
{
    ?>
    <dialog class="modal" data-modal aria-labelledby="modal-title">
      <div class="modal-inner">
        <button type="button" class="modal-close" data-modal-close aria-label="Close"><?= icon('x', 22) ?></button>
        <div class="modal-gallery">
          <div class="modal-tabs" data-modal-tabs role="group" aria-label="Show photos" hidden></div>
          <div class="gallery-stage" data-stage>
            <img data-stage-img alt="">
            <div class="compare" data-compare hidden>
              <img data-compare-before alt="">
              <div class="compare-after" data-compare-after-wrap><img data-compare-after alt=""></div>
              <span class="tag tag-before compare-tag-l">Before</span>
              <span class="tag tag-after compare-tag-r">After</span>
              <div class="compare-handle" data-compare-handle aria-hidden="true"></div>
              <input type="range" min="0" max="100" value="50" class="compare-range" data-compare-range aria-label="Drag to compare before and after">
            </div>
            <button type="button" class="gallery-nav gallery-prev" data-prev aria-label="Previous photo"><?= icon('left', 22) ?></button>
            <button type="button" class="gallery-nav gallery-next" data-next aria-label="Next photo"><?= icon('right', 22) ?></button>
            <span class="gallery-counter" data-counter aria-live="polite"></span>
          </div>
          <ul class="gallery-thumbs" data-thumbs></ul>
        </div>
        <div class="modal-details" data-details></div>
      </div>
    </dialog>
    <?php
}
