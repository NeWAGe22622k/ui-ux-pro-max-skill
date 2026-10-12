<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$denoms = array_filter(array_map('trim', explode(',', setting('voucher_denominations', '₦5,000, ₦10,000, ₦20,000, ₦50,000, ₦100,000, ₦200,000'))));
$note = setting('voucher_note', 'Gift vouchers are sold at the customer service desk of every Prince Ebeano store. For corporate orders, call us and we\'ll put together a package for you.');
$phones = config('store.phones', []);

$perks = [
    ['tag', 'mango', 'Flexible values', 'Choose from a range of amounts to suit any budget or occasion.'],
    ['gift', 'rose', 'Perfect for every occasion', 'Birthdays, thank-yous, weddings, anniversaries, staff rewards and festive gifts.'],
    ['store', 'green', 'Easy to redeem', 'Spend it at any Prince Ebeano store nationwide, on any department or product.'],
    ['calendar', 'sky', 'No expiry pressure', 'A generous validity window, so the people you love can shop when they want.'],
    ['briefcase', 'lilac', 'Corporate packages', 'Customised bundles for businesses and large group gifting.'],
    ['heart', 'peach', 'The gift of choice', 'Let them pick exactly what they need — from groceries to home essentials.'],
];

page_start('Gift vouchers', [
    'active' => 'vouchers',
    'description' => 'Prince Ebeano gift vouchers — flexible values, redeemable at any of our stores. Perfect for birthdays, weddings, staff rewards and festive gifts.',
]);
?>

<section class="page-hero">
  <div class="container split">
    <div>
      <h1>Give the gift of choice.</h1>
      <p class="lede">Whether it's a birthday, a holiday or just because — a Prince Ebeano gift voucher lets them choose exactly what they love.</p>
      <div class="denoms" style="margin-top:1.5rem" aria-label="Available values">
        <?php foreach ($denoms as $d): ?><span class="denom"><?= e($d) ?></span><?php endforeach; ?>
      </div>
    </div>
    <div class="gift-card" aria-hidden="true">
      <div class="gift-card-top"><img src="<?= e(url('assets/img/logo-mark.svg')) ?>" alt=""><span>PRINCE EBEANO<small>SUPERMARKET</small></span></div>
      <span class="gift-card-label">GIFT CARD</span>
      <div class="gift-card-bottom"><span class="gift-card-value"><?= e(end($denoms) ?: '₦200,000') ?></span><?= icon('gift', 'icon icon-lg') ?></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <?= section_head('Why choose a Prince Ebeano gift voucher?', 'Simple to give. A joy to spend.') ?>
    <ul class="perk-list">
      <?php foreach ($perks as [$ic, $tint, $title, $text]): ?>
        <li data-reveal><span class="perk-icon tint-<?= $tint ?>"><?= icon($ic) ?></span><span><strong><?= e($title) ?></strong><span><?= e($text) ?></span></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="voucher-band">
      <div>
        <h2>Pick one up at any store, or call us.</h2>
        <p><?= e($note) ?></p>
        <div class="btn-row" style="display:flex;flex-wrap:wrap;gap:.5rem">
          <?php if ($phones): ?><a class="btn btn-primary" href="<?= e(phone_href($phones[0])) ?>"><?= icon('phone', 'icon icon-sm') ?> Call <?= e($phones[0]) ?></a><?php endif; ?>
          <a class="btn btn-ghost" style="border-color:var(--forest-deep)" href="<?= e(url('stores.php')) ?>">Find a store</a>
        </div>
      </div>
      <div class="gift-card" aria-hidden="true">
        <div class="gift-card-top"><img src="<?= e(url('assets/img/logo-mark.svg')) ?>" alt=""><span>PRINCE EBEANO<small>SUPERMARKET</small></span></div>
        <span class="gift-card-label">THANK YOU</span>
        <div class="gift-card-bottom"><span class="gift-card-value"><?= e($denoms[array_key_first($denoms)] ?? '₦5,000') ?></span><?= icon('heart', 'icon icon-lg') ?></div>
      </div>
    </div>
  </div>
</section>

<?php page_end();
