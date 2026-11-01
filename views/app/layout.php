<?php
/** @var I18n $i18n */
/** @var string $content */
$user = $user ?? null;
$current = $current ?? '';
$title = ($pageTitle ?? APP_NAME) . ' · ' . APP_NAME;
$nav = [
    'dashboard'  => ['', 'nav.dashboard'],
    'programs'   => ['cursos', 'nav.programs'],
    'issue'      => ['emitir', 'nav.issue'],
    'register'   => ['registrar', 'nav.register'],
    'import'     => ['importar', 'nav.import'],
    'email'      => ['email', 'nav.email'],
    'selo'       => ['selo', 'nav.seal'],
    'dobra'      => ['dobra', 'nav.hero'],
    'issuances'  => ['certificados', 'nav.issuances'],
];
?>
<!doctype html>
<html lang="<?= e($i18n->tag()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="icon" href="/assets/brand/mark-solid.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/inter-tight-normal-600-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/inter-normal-400-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/fonts.css?v=18">
<link rel="stylesheet" href="/assets/style.css?v=20">
</head>
<body class="app">
<header class="app-bar">
  <div class="app-bar-inner">
    <a class="brand" href="<?= e($i18n->url('/app/')) ?>">
      <?= Brand::mark('open', 'brand-mark') ?>
      <span class="brand-text">VerID<span class="brand-accent">4U</span></span>
    </a>

    <?php if ($user !== null): ?>
      <nav class="app-nav">
        <?php foreach ($nav as $key => [$path, $label]): ?>
          <a href="<?= e($i18n->url('/app/' . $path)) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= e($i18n->a($label)) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="app-user">
        <a class="quiet-link" href="<?= e($i18n->url()) ?>"><?= e($i18n->a('nav.site')) ?></a>
        <form method="post" action="<?= e($i18n->url('/app/sair')) ?>">
          <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
          <button class="linklike" type="submit"><?= e($i18n->a('nav.logout')) ?></button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</header>

<main class="app-main"><?= $content ?></main>
<script src="/assets/app.js?v=18" defer></script>
</body>
</html>
