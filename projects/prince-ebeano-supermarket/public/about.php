<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$values = [
    ['Providence', 'Our growth and success are rooted in divine guidance and purpose. We owe our inspiration and success to God Almighty.'],
    ['Integrity', 'Transparent, honest and law-abiding in everything we do — with customers, suppliers and each other.'],
    ['Customer centricity', 'Prompt, quality service every time. We prioritise your satisfaction with excellent, timely service.'],
    ['Innovation', 'We evolve, adapt and keep improving how you shop with us — this website is one example.'],
    ['Social service', 'We actively uplift the communities we serve through education, charity and sponsorships.'],
];

page_start('About us', [
    'active' => 'about',
    'description' => 'Prince Ebeano Supermarket has served Lagos and Abuja since 2009. Our story, vision, mission and core values.',
]);
?>

<section class="page-hero">
  <div class="container split">
    <div>
      <p class="eyebrow">About Prince Ebeano</p>
      <h1>Your destination for quality products &amp; household essentials.</h1>
      <p class="lede">Our customers are our greatest asset. They are not dependent on us — we are dependent on them. They feed us.</p>
      <p style="margin-top:1.5rem"><a class="btn btn-primary" href="<?= e(url('stores.php')) ?>"><?= icon('map-pin', 'icon icon-sm') ?> Find a store</a>
        <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>">Contact us</a></p>
    </div>
    <div class="badge-showcase">
      <img src="<?= e(url('assets/img/logo-badge.svg')) ?>" alt="Prince Ebeano Supermarket logo" width="300" height="300">
    </div>
  </div>
</section>

<section class="section">
  <div class="container split">
    <div>
      <p class="eyebrow">Our story</p>
      <h2>Two brothers, one promise: everyday quality for every family.</h2>
    </div>
    <div>
      <p><strong>Prince Ebeano Supermarket</strong> is a partnership between two visionary brothers, <strong>Mr. Sunday Epede</strong> and <strong>Mr. David Ojei</strong>.</p>
      <p>With deep experience in retail, they have grown the brand since its beginning in 2009 into more than ten outlets across Lagos and Abuja — a trusted household name for groceries, home essentials, mothercare, wine and much more.</p>
      <p>Our passion goes beyond products. We're building a thriving workforce of young people, fostering growth and success through paid employment — and we won't stop until we are one of the largest employers in Nigeria and West Africa.</p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container vision-grid">
    <div class="vision-card" data-reveal>
      <h3>Our vision</h3>
      <p>To become the preferred retail chain store in Nigeria and West Africa.</p>
    </div>
    <div class="vision-card" data-reveal>
      <h3>Our mission</h3>
      <p>To establish world-class one-stop retail stores that meet the daily needs of our customers with quality products, in a friendly, courteous and professional environment.</p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <?= section_head('What we stand for', 'Our core values') ?>
    <ul class="core-values">
      <?php foreach ($values as [$name, $text]): ?>
        <li data-reveal><strong><?= e($name) ?></strong><span><?= e($text) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php page_end();
