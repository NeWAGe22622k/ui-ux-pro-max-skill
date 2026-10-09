<?php
require __DIR__ . '/includes/lib.php';

$active = 'services';
$title = 'Services';
$desc = 'Guaranteed rent for landlords, lettings, residential sales and investment & refurbishment from Refugehomes Ltd.';

$services = [
    [
        'id' => 'guaranteed-rent',
        'title' => 'Guaranteed rent',
        'line' => 'A fixed monthly rent for landlords, paid even when the property is empty.',
        'img' => 'photos/guaranteed-rent', 'alt' => 'A landlord relaxing at home, checking his phone',
        'intro' => 'We lease your property on an agreed term and pay you a fixed rent every month, whether the property is occupied or not. You get a predictable income with none of the day-to-day work: we find and manage the tenants, coordinate maintenance and keep you informed, so you can own a rental property without running one.',
        'points' => [
            'Fixed rent paid on the same date every month',
            'Paid even when the property is empty',
            'Tenant finding and management handled by us',
            'Routine maintenance and inspections coordinated',
            'Compliance and safety checks kept up to date',
            'One point of contact and regular updates',
        ],
        'cta' => ['contact.php?enquiry=Guaranteed%20rent', 'Get a guaranteed rent offer'],
    ],
    [
        'id' => 'lettings',
        'title' => 'Lettings &amp; rentals',
        'line' => 'Quality homes for tenants, and referenced tenants for landlords.',
        'img' => 'photos/lettings', 'alt' => 'A bright open-plan living room and kitchen',
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
        'line' => 'Valuation, marketing and negotiation, plus direct purchase.',
        'img' => 'photos/sales', 'alt' => 'A detached family home with a lawn and gravel drive',
        'intro' => 'Selling a property is not always straightforward. Whether your home is ready for the open market or has been sitting unsold, we provide practical solutions tailored to your situation — including direct purchase for sellers who need speed, certainty and discretion.',
        'points' => [
            'Accurate valuation and pricing strategy',
            'Bespoke marketing and property positioning',
            'Skilled negotiation to maximise value',
            'Direct purchase of properties in any condition',
            'Transaction coordination through to completion',
            'Clear communication at every stage',
        ],
        'cta' => ['contact.php?enquiry=Selling%20a%20property', 'Get a valuation'],
    ],
    [
        'id' => 'investment',
        'title' => 'Investment &amp; refurbishment',
        'line' => 'Sourcing, analysis and refurbishment for long-term value.',
        'img' => 'photos/investment', 'alt' => 'Tradespeople fitting a new floor during a refurbishment',
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
$s = settings();
$gr = $services[0];
$others = array_slice($services, 1);
?>

<section class="page-hero">
  <div class="container">
    <div class="page-hero-split">
      <div class="reveal">
        <p class="eyebrow">Services</p>
        <h1 class="h1">Property services, delivered with <em>precision.</em></h1>
      </div>
      <p class="lead reveal">Four ways we help homeowners, landlords and investors. Choose a service to jump straight to it.</p>
    </div>

    <nav class="svc-index" aria-label="Our services">
      <?php foreach ($services as $i => $svc): ?>
        <a class="reveal" href="#<?= $svc['id'] ?>">
          <span class="svc-index-num">0<?= $i + 1 ?></span>
          <span class="svc-index-title"><?= $svc['title'] ?></span>
          <span class="svc-index-line"><?= h($svc['line']) ?></span>
          <span class="svc-index-go" aria-hidden="true"><?= icon('arrow-right', 18) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

<!-- Flagship: guaranteed rent -->
<section class="flagship" id="<?= $gr['id'] ?>">
  <div class="container">
    <div class="flagship-top">
      <div class="reveal">
        <p class="eyebrow">01 &middot; For landlords</p>
        <h2 class="h2"><?= $gr['title'] ?></h2>
        <p class="prose lead"><?= h($gr['intro']) ?></p>
        <div class="btn-row">
          <a class="btn btn-light" href="<?= $gr['cta'][0] ?>"><?= h($gr['cta'][1]) ?> <?= icon('arrow-right', 18) ?></a>
          <a class="btn btn-outline-light" href="<?= h(tel($s['phone'])) ?>"><?= icon('phone', 18) ?> <?= h($s['phone']) ?></a>
        </div>
      </div>
      <div class="flagship-media reveal">
        <img <?= img_attrs('assets/img/' . $gr['img'] . '.jpg', '(max-width: 1000px) 100vw, 560px') ?> alt="<?= h($gr['alt']) ?>" width="1800" height="1013" loading="lazy">
      </div>
    </div>

    <div class="how">
      <h3 class="how-title reveal">How it works</h3>
      <ol class="how-steps">
        <?php
        $how = [
            ['Tell us about your property', 'Share the address and a few details, in any condition. We arrange a visit.'],
            ['Receive your offer', 'We propose a fixed monthly rent and an agreed term, with no obligation.'],
            ['We take it from there', 'We find and manage the tenants and coordinate maintenance and safety checks.'],
            ['Get paid every month', 'Your rent arrives on the same date each month, even when the property is empty.'],
        ];
        foreach ($how as $i => [$t, $d]): ?>
          <li class="reveal">
            <span class="how-num"><?= $i + 1 ?></span>
            <h4><?= h($t) ?></h4>
            <p><?= h($d) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="included reveal">
      <h3 class="how-title">What's included</h3>
      <ul class="included-list">
        <?php foreach ($gr['points'] as $pt): ?>
          <li><?= icon('check', 18) ?><span><?= h($pt) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <p class="flag-note reveal">Rent and term are agreed for each property. Ask us for a no-obligation offer.</p>
  </div>
</section>

<!-- Any condition -->
<section class="section-tight section-white">
  <div class="container split">
    <div class="reveal">
      <p class="eyebrow">Any property, any condition</p>
      <h2 class="h2">We work with properties in <em>any condition.</em></h2>
    </div>
    <div class="reveal">
      <ul class="conditions" aria-label="Property conditions we work with">
        <?php foreach (['Move-in ready', 'Tired &amp; dated', 'Needs full renovation', 'Empty or inherited', 'Damp or repair issues', 'Struggling to sell or let'] as $c): ?>
          <li><?= icon('check', 15) ?> <?= $c ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="prose">Whatever the state of your property, we assess it honestly and explain your options clearly.</p>
      <a class="link-arrow" href="contact.php?enquiry=Property%20in%20need%20of%20work">Tell us about your property <?= icon('arrow-right', 16) ?></a>
    </div>
  </div>
</section>

<!-- Other services -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div class="reveal">
        <p class="eyebrow">More services</p>
        <h2 class="h2">Lettings, sales and refurbishment.</h2>
      </div>
    </div>
    <div class="svc-list">
      <?php foreach ($others as $i => $svc): ?>
        <article class="svc" id="<?= $svc['id'] ?>">
          <header class="svc-head">
            <span class="svc-num">0<?= $i + 2 ?></span>
            <h2 class="svc-title"><?= $svc['title'] ?></h2>
          </header>
          <div class="svc-main">
            <div class="svc-media reveal">
              <img <?= img_attrs('assets/img/' . $svc['img'] . '.jpg', '(max-width: 860px) 100vw, 800px') ?> alt="<?= h($svc['alt']) ?>" width="1800" height="1013" loading="lazy">
            </div>
            <div class="svc-body reveal">
              <div>
                <p class="prose"><?= h($svc['intro']) ?></p>
                <a class="link-arrow" href="<?= $svc['cta'][0] ?>"><?= h($svc['cta'][1]) ?> <?= icon('arrow-right', 16) ?></a>
              </div>
              <ul class="checklist">
                <?php foreach ($svc['points'] as $pt): ?>
                  <li><?= icon('check', 16) ?><span><?= h($pt) ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
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
          ['Homeowners selling', 'Honest advice, accurate pricing and a well-run sale, so you can move on with confidence and without unnecessary stress.', 'sales', 'Residential sales'],
          ['Landlords', 'A fixed monthly rent with guaranteed rent, or reliable tenants through our lettings service, protecting both your income and your asset.', 'guaranteed-rent', 'Guaranteed rent'],
          ['Investors', 'Realistic analysis and disciplined decision-making, whether you are buying your first investment or expanding a portfolio.', 'investment', 'Investment &amp; refurbishment'],
          ['Developers &amp; value-add buyers', 'Refurbishment and repositioning grounded in market demand, with projects approached carefully and commercially.', 'investment', 'Investment &amp; refurbishment'],
      ];
      foreach ($who as $i => [$t, $d, $to, $label]): ?>
        <a class="tile tile-link reveal" href="#<?= $to ?>">
          <span class="tile-num">0<?= $i + 1 ?></span>
          <h3><?= $t ?></h3>
          <p><?= $d ?></p>
          <span class="tile-go"><?= $label ?> <?= icon('arrow-right', 15) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/cta.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
