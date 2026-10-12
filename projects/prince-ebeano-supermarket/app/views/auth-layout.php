<?php
declare(strict_types=1);

/** Full-screen centred card used by the login and first-time setup pages. */
function auth_start(string $title): void
{
    ?>
<!doctype html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Prince Ebeano</title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="admin">
<main id="main" class="login-wrap">
  <div class="login-card">
    <a class="brand" href="<?= e(url()) ?>">
      <img src="<?= e(url('assets/img/logo-mark.svg')) ?>" width="44" height="44" alt="">
      <span class="brand-text" style="display:block"><strong>Prince Ebeano</strong><small>Staff area</small></span>
    </a>
    <?php render_flashes(); ?>
<?php
}

function auth_end(): void
{
    ?>
  </div>
</main>
</body>
</html>
<?php
}
