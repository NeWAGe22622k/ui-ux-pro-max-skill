<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$storeFilter = ($s = get_str('store')) !== '' ? branch_by_slug($s) : null;
$params = [];
$sql = 'SELECT j.*, b.name AS branch_name, b.city FROM jobs j LEFT JOIN branches b ON b.id = j.branch_id';
if ($storeFilter) {
    $sql .= ' WHERE j.branch_id = ?';
    $params[] = (int)$storeFilter['id'];
}
$sql .= ' ORDER BY j.is_open DESC, j.posted_at DESC';
$jobs = q_all($sql, $params);
$openCount = count(array_filter($jobs, fn($j) => (int)$j['is_open'] === 1));
$careersEmail = setting('careers_email', (string)config('store.email', ''));

page_start('Careers', [
    'active' => 'careers',
    'description' => 'Work at Prince Ebeano Supermarket. See open roles at our stores in Lagos and Abuja.',
]);
?>

<section class="page-hero page-hero-dark">
  <div class="container">
    <p class="eyebrow">Careers</p>
    <h1>Grow with Prince Ebeano.</h1>
    <p class="lede">We're building a thriving team of young Nigerians — with real training, paid employment and room to grow. Come shape the future of retail with us.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <nav class="chips" aria-label="Filter by store" style="margin:1rem 0 1.5rem">
      <a class="chip" href="<?= e(url('careers.php')) ?>" aria-current="<?= $storeFilter ? 'false' : 'true' ?>">All stores</a>
      <?php foreach (branches() as $b): ?>
        <a class="chip" href="<?= e(url('careers.php?store=' . urlencode($b['slug']))) ?>" aria-current="<?= $storeFilter && $storeFilter['id'] === $b['id'] ? 'true' : 'false' ?>"><?= e($b['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <p class="results-meta" style="margin:0 0 1rem"><?= $openCount ?> open role<?= $openCount === 1 ? '' : 's' ?><?= $storeFilter ? ' at ' . e($storeFilter['name']) : '' ?></p>

    <?php if (!$jobs): ?>
      <?= empty_state('briefcase', 'No openings right now', 'We don\'t have any roles listed at the moment. You can still send us your CV — we\'ll keep it on file for the next opening.',
          $careersEmail ? '<a class="btn btn-primary" href="mailto:' . e($careersEmail) . '?subject=' . rawurlencode('CV for future roles') . '">Send your CV</a>' : '') ?>
    <?php else: ?>
      <div class="job-list">
        <?php foreach ($jobs as $j): $open = (int)$j['is_open'] === 1; ?>
          <article class="job-card<?= $open ? '' : ' is-closed' ?>" data-reveal>
            <div>
              <h3><?= e($j['title']) ?></h3>
              <div class="job-meta">
                <span><?= icon('map-pin', 'icon icon-sm') ?> <?= e($j['branch_name'] ? 'Prince Ebeano ' . $j['branch_name'] : 'Any store') ?></span>
                <span><?= icon('calendar', 'icon icon-sm') ?> Posted <?= e(date('j M Y', strtotime($j['posted_at']))) ?></span>
                <?= $open ? '<span class="pill pill-in">' . icon('circle-check', 'icon icon-sm') . 'Open</span>' : '<span class="pill pill-out">' . icon('circle-x', 'icon icon-sm') . 'Closed</span>' ?>
              </div>
              <?php if ($j['summary']): ?><p><?= e($j['summary']) ?></p><?php endif; ?>
            </div>
            <?php if ($open && $careersEmail): ?>
              <a class="btn btn-primary" href="mailto:<?= e($careersEmail) ?>?subject=<?= rawurlencode('Application: ' . $j['title'] . ($j['branch_name'] ? ' — ' . $j['branch_name'] : '')) ?>">Apply by email</a>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php page_end();
