<?php
/**
 * Page header. Before including, set:
 *   $title  – page title (shown before " | Refugehomes")
 *   $desc   – meta description
 *   $active – nav key: home|about|services|properties|rentals|contact
 */
$s = settings();
$title = $title ?? '';
$desc = $desc ?? 'Refugehomes Ltd – guaranteed rent, lettings, sales and refurbishment across the UK. Built on trust.';
$active = $active ?? '';
$nav = [
    'home'       => ['index.php', 'Home'],
    'about'      => ['about.php', 'About'],
    'services'   => ['services.php', 'Services'],
    'properties' => ['properties.php', 'Our Properties'],
    'rentals'    => ['rentals.php', 'Rentals'],
];
$fullTitle = $title ? $title . ' | ' . $s['company'] : $s['company'] . ' | ' . $s['tagline'];
?><!doctype html>
<html lang="en-GB">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($fullTitle) ?></title>
  <meta name="description" content="<?= h($desc) ?>">
  <meta property="og:title" content="<?= h($fullTitle) ?>">
  <meta property="og:description" content="<?= h($desc) ?>">
  <meta property="og:type" content="website">
  <meta name="theme-color" content="#1a652e">
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400;1,6..72,500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=2">
  <script>document.documentElement.classList.add('js')</script>
</head>
<body class="page-<?= h($active ?: 'default') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" data-header>
  <div class="container header-inner">
    <a class="brand" href="index.php" aria-label="<?= h($s['company']) ?> – home">
      <img src="assets/img/logo.png" alt="<?= h($s['company']) ?>" width="402" height="151">
    </a>

    <nav class="primary-nav" id="primary-nav" aria-label="Main">
      <ul>
        <?php foreach ($nav as $key => [$href, $label]): ?>
          <li><a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a></li>
        <?php endforeach; ?>
        <li class="nav-cta-mobile"><a href="contact.php"<?= $active === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a></li>
      </ul>
      <div class="nav-mobile-foot">
        <a href="<?= h(tel($s['phone'])) ?>"><?= icon('phone', 18) ?> <?= h($s['phone']) ?></a>
        <a href="mailto:<?= h($s['email']) ?>"><?= icon('mail', 18) ?> <?= h($s['email']) ?></a>
      </div>
    </nav>

    <a class="btn btn-primary btn-sm header-cta" href="contact.php">Contact us</a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" data-nav-toggle>
      <span class="sr-only">Menu</span>
      <span class="nav-toggle-bars" aria-hidden="true"><span></span><span></span></span>
    </button>
  </div>
</header>

<main id="main">
