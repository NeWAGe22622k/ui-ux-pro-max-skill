<?php
/** @var string $page_key  @var string $page_title  @var string $page_description */
$nav = [
    'home'       => ['Home', ''],
    'about'      => ['About us', 'about'],
    'services'   => ['Services', 'services'],
    'properties' => ['Our properties', 'properties'],
    'rentals'    => ['Rentals', 'rentals'],
];
$company = setting('company_name', 'Refugehomes Ltd');
$full_title = $page_key === 'home' ? "$company | $page_title" : "$page_title | $company";
?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($full_title) ?></title>
<meta name="description" content="<?= e($page_description) ?>">
<meta property="og:title" content="<?= e($full_title) ?>">
<meta property="og:description" content="<?= e($page_description) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#1B6A2D">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('assets/img/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</head>
<body class="page-<?= e($page_key) ?>" data-base="<?= e(base_path()) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" data-header>
  <div class="container header-inner">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($company) ?> - home">
      <img src="<?= e(url('assets/img/logo.png')) ?>" alt="<?= e($company) ?>" width="406" height="155">
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
      <span class="nav-toggle-open"><?= icon('menu', 24) ?></span>
      <span class="nav-toggle-close"><?= icon('x', 24) ?></span>
      <span class="sr-only">Menu</span>
    </button>
    <nav class="site-nav" id="site-nav" aria-label="Main" data-nav>
      <ul>
        <?php foreach ($nav as $key => [$label, $path]): ?>
          <li><a href="<?= e(url($path)) ?>"<?= $key === $page_key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn btn-primary btn-sm" href="<?= e(url('contact')) ?>"<?= $page_key === 'contact' ? ' aria-current="page"' : '' ?>>Contact us</a>
    </nav>
  </div>
</header>
<main id="main">
