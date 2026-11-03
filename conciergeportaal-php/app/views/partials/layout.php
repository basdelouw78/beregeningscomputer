<?php
$showNav = $user && $user['setup_done'];
$tabs = [
    ['/', 'Home', 'Home', 'home'],
    ['/materialen', 'Materialen', 'Materiaal', 'box'],
    ['/meetings', 'Meetings', 'Meetings', 'calendar'],
    ['/vragen', 'Vragen & antwoorden', 'Vragen', 'help'],
    ['/tutorials', 'Tutorials', 'Tutorials', 'tool'],
];
$extra = [
    ['/collegas', "Collega's", 'users'],
    ['/setup', 'Mijn locaties', 'pin'],
    ['/profiel', 'Mijn profiel', 'user'],
];
if ($user && $user['is_admin']) $extra[] = ['/admin/scholen', 'Beheer', 'shield'];
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f766e">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Conciërges">
  <title><?= $title ? e($title) . ' · ' : '' ?>Conciërgeportaal</title>
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
  <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= e(asset('icon.svg')) ?>">
  <?php if ($useMap): ?><link rel="stylesheet" href="<?= e(asset('leaflet/leaflet.css')) ?>"><?php endif ?>
  <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php require __DIR__ . '/icons.php' ?>
<header class="topbar">
  <a class="brand" href="<?= e(url('/')) ?>">
    <span class="brand-mark"><?= icon('school') ?></span>
    <span>Conciërgeportaal</span>
  </a>
  <span class="spacer"></span>
  <?php if ($user): ?>
    <a class="avatar" href="<?= e(url('/profiel')) ?>" title="Mijn profiel" aria-label="Mijn profiel"><?= e(initials($user['name'])) ?></a>
  <?php endif ?>
</header>
<div class="layout<?= $showNav ? '' : ' no-nav' ?>">
  <?php if ($showNav): ?>
  <nav class="sidebar" aria-label="Hoofdmenu">
    <?php foreach ($tabs as [$href, $label, , $ic]): ?>
      <a href="<?= e(url($href)) ?>" class="<?= is_active($href) ? 'active' : '' ?>"><?= icon($ic) ?> <?= e($label) ?></a>
    <?php endforeach ?>
    <div class="sep"></div>
    <?php foreach ($extra as [$href, $label, $ic]): ?>
      <a href="<?= e(url($href)) ?>" class="<?= is_active($href) ? 'active' : '' ?>"><?= icon($ic) ?> <?= e($label) ?></a>
    <?php endforeach ?>
    <form method="post" action="<?= e(url('/logout')) ?>">
      <?= csrf_field() ?>
      <button class="btn ghost block" type="submit"><?= icon('logout') ?> Uitloggen</button>
    </form>
  </nav>
  <?php endif ?>
  <main class="content">
    <?php foreach ($flash as $f): ?>
      <div class="flash <?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'error' ? 'alert' : 'check') ?> <span><?= e($f['message']) ?></span></div>
    <?php endforeach ?>
    <?= $content ?>
  </main>
</div>
<?php if ($showNav): ?>
<nav class="tabbar" aria-label="Snelmenu">
  <?php foreach ($tabs as [$href, , $short, $ic]): $active = is_active($href); ?>
    <a href="<?= e(url($href)) ?>" class="<?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($short) ?></span></a>
  <?php endforeach ?>
</nav>
<?php endif ?>
<?php if ($useMap): ?><script src="<?= e(asset('leaflet/leaflet.js')) ?>"></script><?php endif ?>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
