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
<link rel="icon" href="/assets/mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="/assets/style.css?v=3">
</head>
<body class="app">
<header class="app-bar">
  <div class="app-bar-inner">
    <a class="brand" href="<?= e($i18n->url('/app/')) ?>">
      <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2.5 3.5 7v9.2c0 7.4 5 12 12.5 13.8 7.5-1.8 12.5-6.4 12.5-13.8V7Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="m10.5 16.2 4 4.1 7.2-8" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
<script src="/assets/app.js?v=3" defer></script>
</body>
</html>
