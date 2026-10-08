<?php $s = settings(); ?>
<section class="cta-band">
  <div class="container">
    <div class="reveal">
      <p class="eyebrow">Let's talk</p>
      <h2 class="h2">Thinking about letting, selling or investing?</h2>
    </div>
    <p class="lead reveal" data-delay="150">Tell us about your property and your goals. We'll give you straight, practical advice — no pressure and no obligation.</p>
    <div class="btn-row reveal" data-delay="250">
      <a class="btn btn-light" href="contact.php">Book a consultation <?= icon('arrow-right', 18) ?></a>
      <a class="btn btn-outline-light" href="<?= h(tel($s['phone'])) ?>"><?= icon('phone', 18) ?> <?= h($s['phone']) ?></a>
    </div>
  </div>
</section>
