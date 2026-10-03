<?php
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$page_key = '404';
$page_title = 'Page not found';
$page_description = 'The page you were looking for could not be found.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Error 404</p>
    <h1>We couldn&rsquo;t find that page</h1>
    <p class="lead">It may have moved, or the property may no longer be listed.</p>
    <div class="btn-row">
      <a class="btn btn-primary" href="<?= e(url()) ?>">Back to home</a>
      <a class="btn btn-secondary" href="<?= e(url('rentals')) ?>">View rentals</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
