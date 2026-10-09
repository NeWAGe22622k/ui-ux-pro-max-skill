<?php
/** Card + modal markup shared by the home, properties and rentals pages. */

function property_card(array $p): void
{
    $isFlip = ($p['type'] ?? '') === 'flip';
    $payload = property_payload($p);
    $count = $isFlip ? count($payload['before']) + count($payload['after']) : count($payload['images']);
    $before = $payload['before'][0] ?? null;
    $after = $payload['after'][0] ?? null;
    ?>
    <article class="card prop-card reveal" data-type="<?= $isFlip ? 'flip' : 'managed' ?>" data-payload="<?= json_attr($payload) ?>">
      <?php if ($isFlip && $before && $after): ?>
        <div class="card-media compare" data-compare>
          <img src="<?= h($after) ?>" alt="<?= h($p['title']) ?> after refurbishment" loading="lazy" decoding="async">
          <div class="compare-before"><img src="<?= h($before) ?>" alt="<?= h($p['title']) ?> before refurbishment" loading="lazy" decoding="async"></div>
          <span class="compare-label compare-label-before">Before</span>
          <span class="compare-label compare-label-after">After</span>
          <span class="compare-handle" aria-hidden="true"><span><?= icon('move', 16) ?></span></span>
          <input class="compare-range" type="range" min="0" max="100" value="50" aria-label="Drag to compare before and after photos of <?= h($p['title']) ?>">
        </div>
      <?php else: ?>
        <button type="button" class="card-media" data-open-gallery aria-label="View photos of <?= h($p['title']) ?>">
          <img src="<?= h(cover($p)) ?>" alt="<?= h($p['title']) ?>" loading="lazy" decoding="async">
        </button>
      <?php endif; ?>
      <div class="card-body">
        <p class="card-kicker"><span class="tag"><?= $isFlip ? 'Refurbishment' : 'Managed' ?></span><?= h($p['location'] ?? '') ?></p>
        <h3 class="card-title"><?= h($p['title'] ?? '') ?></h3>
        <?php if (!empty($p['summary'])): ?><p class="card-text"><?= h($p['summary']) ?></p><?php endif; ?>
        <button type="button" class="link-arrow" data-open-gallery>
          <?= $isFlip ? 'See before &amp; after' : 'View photos' ?> <span class="muted">(<?= (int) $count ?>)</span> <?= icon('arrow-right', 16) ?>
        </button>
      </div>
    </article>
    <?php
}

function rental_payload(array $r): array
{
    return [
        'id'             => $r['id'] ?? '',
        'title'          => $r['title'] ?? '',
        'location'       => $r['location'] ?? '',
        'price'          => money($r['price_pcm'] ?? 0),
        'bedrooms'       => (int) ($r['bedrooms'] ?? 0),
        'bathrooms'      => (int) ($r['bathrooms'] ?? 0),
        'property_type'  => $r['property_type'] ?? '',
        'furnished'      => $r['furnished'] ?? '',
        'available_from' => $r['available_from'] ?? '',
        'deposit'        => $r['deposit'] ?? '',
        'description'    => $r['description'] ?? '',
        'features'       => array_values($r['features'] ?? []),
        'images'         => array_values($r['images'] ?? []),
        'links'          => array_values(array_filter($r['links'] ?? [], fn($l) => !empty($l['url']))),
        'status'         => $r['status'] ?? 'available',
    ];
}

function rental_card(array $r): void
{
    $d = rental_payload($r);
    $let = $d['status'] === 'let';
    ?>
    <article class="card rental-card reveal<?= $let ? ' is-let' : '' ?>" id="home-<?= h($d['id']) ?>" data-payload="<?= json_attr($d) ?>">
      <button type="button" class="card-media" data-open-rental aria-label="View details of <?= h($d['title']) ?>">
        <img src="<?= h(cover($r)) ?>" alt="<?= h($d['title']) ?>" loading="lazy" decoding="async">
        <span class="status <?= $let ? 'status-let' : 'status-available' ?>"><?= $let ? 'Let agreed' : 'Available' ?></span>
      </button>
      <div class="card-body">
        <p class="price"><?= h($d['price']) ?> <span>pcm</span></p>
        <h3 class="card-title"><?= h($d['title']) ?></h3>
        <p class="card-location"><?= icon('map-pin', 15) ?> <?= h($d['location']) ?></p>
        <ul class="facts">
          <?php if ($d['bedrooms']): ?><li><?= icon('bed', 16) ?> <?= $d['bedrooms'] ?> bed<?= $d['bedrooms'] > 1 ? 's' : '' ?></li><?php endif; ?>
          <?php if ($d['bathrooms']): ?><li><?= icon('bath', 16) ?> <?= $d['bathrooms'] ?> bath<?= $d['bathrooms'] > 1 ? 's' : '' ?></li><?php endif; ?>
          <?php if ($d['property_type']): ?><li><?= icon('home', 16) ?> <?= h($d['property_type']) ?></li><?php endif; ?>
        </ul>
        <button type="button" class="link-arrow" data-open-rental>View details <?= icon('arrow-right', 16) ?></button>
      </div>
    </article>
    <?php
}

/** The two <dialog>s used by listings.js. Output once per page. */
function listing_modals(): void
{
    ?>
    <dialog class="modal" id="gallery-modal" aria-labelledby="gallery-title">
      <div class="modal-inner">
        <header class="modal-head">
          <div>
            <p class="eyebrow" data-g-location></p>
            <h2 class="modal-title" id="gallery-title" data-g-title></h2>
          </div>
          <button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 22) ?></button>
        </header>
        <div class="seg" role="tablist" aria-label="Photo set" data-g-tabs hidden>
          <button type="button" role="tab" data-set="before">Before</button>
          <button type="button" role="tab" data-set="after">After</button>
        </div>
        <div class="gallery" data-gallery>
          <div class="gallery-stage">
            <img alt="" data-g-img>
            <button type="button" class="gallery-nav prev" data-g-prev aria-label="Previous photo"><?= icon('chevron-left', 24) ?></button>
            <button type="button" class="gallery-nav next" data-g-next aria-label="Next photo"><?= icon('chevron-right', 24) ?></button>
            <span class="gallery-count" data-g-count aria-live="polite"></span>
          </div>
          <div class="gallery-thumbs" data-g-thumbs></div>
        </div>
        <p class="modal-summary" data-g-summary></p>
      </div>
    </dialog>

    <dialog class="modal modal-wide" id="rental-modal" aria-labelledby="rental-title">
      <div class="modal-inner">
        <header class="modal-head">
          <div>
            <p class="eyebrow" data-r-location></p>
            <h2 class="modal-title" id="rental-title" data-r-title></h2>
          </div>
          <button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 22) ?></button>
        </header>
        <div class="rental-detail">
          <div class="gallery" data-gallery>
            <div class="gallery-stage">
              <img alt="" data-g-img>
              <button type="button" class="gallery-nav prev" data-g-prev aria-label="Previous photo"><?= icon('chevron-left', 24) ?></button>
              <button type="button" class="gallery-nav next" data-g-next aria-label="Next photo"><?= icon('chevron-right', 24) ?></button>
              <span class="gallery-count" data-g-count aria-live="polite"></span>
            </div>
            <div class="gallery-thumbs" data-g-thumbs></div>
          </div>
          <div class="rental-info">
            <p class="price price-lg" data-r-price></p>
            <span class="status" data-r-status></span>
            <dl class="spec" data-r-spec></dl>
            <div class="prose" data-r-desc></div>
            <div data-r-features-wrap>
              <h3 class="mini-heading">Features</h3>
              <ul class="checklist" data-r-features></ul>
            </div>
            <div data-r-links-wrap>
              <h3 class="mini-heading">Also listed on</h3>
              <div class="platforms" data-r-links></div>
            </div>
            <a class="btn btn-primary btn-block" data-r-enquire href="contact.php">Enquire about this home <?= icon('arrow-right', 18) ?></a>
          </div>
        </div>
      </div>
    </dialog>
    <?php
}
