<?php
require __DIR__ . '/includes/lib.php';

$active = 'services';
$title = 'Services';
$desc = 'Property management, lettings, residential sales and investment & refurbishment from Refugehomes Ltd.';

$services = [
    [
        'id' => 'management',
        'title' => 'Property management',
        'img' => 'harlow-3',
        'intro' => 'Our management service protects your asset, reduces stress and simplifies ownership. We take a proactive, professional approach so your property stays well maintained, compliant and performing — giving you peace of mind and your tenants a positive living experience.',
        'points' => [
            'Tenant sourcing and thorough referencing',
            'Rent collection and financial administration',
            'Compliance and regulatory oversight',
            'Inspections and maintenance coordination',
            'Tenant communication and issue resolution',
            'Guaranteed rent options for landlords',
        ],
        'cta' => ['contact.php?enquiry=Property%20management', 'Discuss management'],
    ],
    [
        'id' => 'lettings',
        'title' => 'Lettings &amp; rentals',
        'img' => 'rental-2',
        'intro' => 'For tenants, we offer quality, well-kept homes in well-connected locations, with honest listings and a responsive point of contact. For landlords, we market your property widely and match it with reliable, fully referenced tenants.',
        'points' => [
            'Homes advertised on the major property portals',
            'Accurate, honest descriptions and pricing',
            'Viewings arranged around you',
            'Clear tenancy paperwork and deposit protection',
            'A named contact throughout the tenancy',
        ],
        'cta' => ['rentals.php', 'Browse homes to rent'],
    ],
    [
        'id' => 'sales',
        'title' => 'Residential sales',
        'img' => 'harlow-1',
        'intro' => 'Selling a property is not always straightforward. Whether your home is ready for the open market or has been sitting unsold, we provide practical solutions tailored to your situation — including direct purchase for sellers who need speed, certainty and discretion.',
        'points' => [
            'Accurate valuation and pricing strategy',
            'Bespoke marketing and property positioning',
            'Skilled negotiation to maximise value',
            'Direct purchase options for urgent or stalled sales',
            'Transaction coordination through to completion',
            'Clear communication at every stage',
        ],
        'cta' => ['contact.php?enquiry=Selling%20a%20property', 'Get a valuation'],
    ],
    [
        'id' => 'investment',
        'title' => 'Investment &amp; refurbishment',
        'img' => 'flip1-after-1',
        'intro' => 'We work closely with investors to identify, assess and execute opportunities built on strong fundamentals. Our approach is insight-led and disciplined, focused on risk awareness, long-term value and well-structured decisions — from acquisition through refurbishment to letting or resale.',
        'points' => [
            'Deal sourcing and opportunity assessment',
            'Market and yield analysis',
            'Acquisition and negotiation support',
            'Refurbishment and value-add delivery',
            'Portfolio strategy and optimisation',
            'Ongoing support aligned with your goals',
        ],
        'cta' => ['properties.php?show=flip', 'See our refurbishments'],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container page-hero-split">
    <div class="reveal">
      <p class="eyebrow">Services</p>
      <h1 class="h1">Property services, delivered with <em>precision.</em></h1>
    </div>
    <p class="lead reveal">Our services are built for people who expect more than a transaction. Through careful planning, active management and informed market positioning, we help you get the full potential from your property.</p>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <?php foreach ($services as $i => $svc): ?>
      <article class="feature" id="<?= $svc['id'] ?>">
        <div class="feature-media reveal">
          <img src="assets/img/placeholder/<?= $svc['img'] ?>.jpg" alt="" width="1600" height="1100" loading="lazy">
        </div>
        <div class="reveal">
          <p class="eyebrow">0<?= $i + 1 ?></p>
          <h2 class="h2"><?= $svc['title'] ?></h2>
          <p class="prose"><?= h($svc['intro']) ?></p>
          <ul class="checklist checklist-2">
            <?php foreach ($svc['points'] as $pt): ?>
              <li><?= icon('check', 16) ?><span><?= h($pt) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="link-arrow" href="<?= $svc['cta'][0] ?>"><?= h($svc['cta'][1]) ?> <?= icon('arrow-right', 16) ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section section-white">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">How we work</p>
        <h2 class="h2">The step-by-step process.</h2>
      </div>
      <p class="lead">From early planning to final outcomes, you get clear guidance, practical solutions and hands-on support shaped around your goals.</p>
    </div>
    <ol class="steps" style="list-style:none;margin:0;padding:0">
      <?php
      $steps = [
          ['Consultation', 'We start with a focused conversation to understand your property, priorities and what success means to you.'],
          ['Strategy &amp; planning', 'We create a tailored plan grounded in market insight and built around your specific goals.'],
          ['Execution', 'We manage every detail carefully, so the process runs smoothly and efficiently.'],
          ['Ongoing updates', 'We keep you informed with clear updates and honest guidance at every stage.'],
          ['Completion &amp; aftercare', 'We finish everything properly and remain available for your next steps.'],
      ];
      foreach ($steps as $i => [$t, $d]): ?>
        <li class="step reveal">
          <div class="step-num">0<?= $i + 1 ?></div>
          <h3><?= $t ?></h3>
          <p><?= $d ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <p class="eyebrow">Who we work with</p>
        <h2 class="h2">Support for every stage of the property journey.</h2>
      </div>
      <p class="lead">Whether it is your first transaction or one of many, you get structured support tailored to your goals and circumstances.</p>
    </div>
    <div class="tiles">
      <?php
      $who = [
          ['Homeowners selling', 'Honest advice, accurate pricing and a well-run sale, so you can move on with confidence and without unnecessary stress.'],
          ['Landlords', 'Professional, consistent management for single properties and growing portfolios, protecting both your income and your asset.'],
          ['Investors', 'Realistic analysis and disciplined decision-making, whether you are buying your first investment or expanding a portfolio.'],
          ['Developers &amp; value-add buyers', 'Refurbishment and repositioning grounded in market demand, with projects approached carefully and commercially.'],
      ];
      foreach ($who as $i => [$t, $d]): ?>
        <div class="tile reveal">
          <span class="tile-num">0<?= $i + 1 ?></span>
          <h3><?= $t ?></h3>
          <p><?= $d ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/cta.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
