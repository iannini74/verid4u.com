<?php
/** @var I18n $i18n */
/** @var string $content */
$title = $pageTitle ?? $i18n->t('meta.home.title');
$description = $pageDesc ?? $i18n->t('meta.home.desc');
$isNoindex = $pageNoindex ?? false;
$base = base_url();
?>
<!doctype html>
<html lang="<?= e($i18n->tag()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($isNoindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<link rel="canonical" href="<?= e($base . localized_path($i18n->locale())) ?>">
<?php foreach (I18n::LOCALES as $loc => $meta): ?>
<link rel="alternate" hreflang="<?= e($meta['tag']) ?>" href="<?= e($base . '/' . $loc . '/') ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e($base) ?>/">
<?php endif; ?>
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="<?= e(str_replace('-', '_', $i18n->tag())) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#1D5BE0">
<link rel="icon" href="/assets/brand/mark-solid.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/inter-tight-normal-600-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/inter-normal-400-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/fonts.css?v=18">
<script>document.documentElement.classList.add("js");</script>
<?= $extraHead ?? '' ?>
<link rel="stylesheet" href="/assets/style.css?v=19">
</head>
<body>
<a class="skip" href="#main"><?= e($i18n->t('hero.title')) ?></a>

<header class="site-header" data-sticky-header>
  <div class="wrap header-row">
    <a class="brand" href="<?= e($i18n->url()) ?>">
      <span style="color: var(--brand); display: inline-flex;"><?php require __DIR__ . '/partials/mark.php'; ?></span>
      <span class="brand-text">VerID<span class="brand-accent">4U</span></span>
    </a>

    <nav class="site-nav" aria-label="<?= e($i18n->t('nav.how')) ?>">
      <a href="<?= e($i18n->url()) ?>#como"><?= e($i18n->t('nav.how')) ?></a>
      <a href="<?= e($i18n->url()) ?>#para-quem"><?= e($i18n->t('nav.audiences')) ?></a>
      <a href="<?= e($i18n->url()) ?>#duvidas"><?= e($i18n->t('nav.faq')) ?></a>
      <a href="<?= e($i18n->url('/blog')) ?>"><?= e($i18n->t('blog.title')) ?></a>
    </nav>

    <div class="header-actions">
      <a class="btn btn-ghost btn-sm" href="<?= e($i18n->url('/app/entrar')) ?>"><?= e($i18n->t('nav.signin')) ?></a>
      <a class="btn btn-primary btn-sm" href="<?= e($i18n->url('/app/ativar')) ?>"><?= e($i18n->t('nav.start')) ?></a>
    </div>

    <div class="lang" role="group" aria-label="Idioma">
      <?php foreach (I18n::LOCALES as $loc => $meta): ?>
        <a href="<?= e(localized_path($loc)) ?>" hreflang="<?= e($meta['tag']) ?>" title="<?= e($meta['name']) ?>"
           <?= $loc === $i18n->locale() ? 'aria-current="true"' : '' ?>><?= e($meta['short']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<main id="main"><?= $content ?></main>

<footer class="site-footer">
  <div class="wrap footer-row">
    <p class="footer-brand">VerID<span class="brand-accent">4U</span> — <?= e($i18n->t('site.tagline')) ?></p>
    <nav class="footer-links" aria-label="<?= e($i18n->t('legal.termos')) ?>">
      <a href="<?= e($i18n->url('/termos')) ?>"><?= e($i18n->t('legal.termos')) ?></a>
      <a href="<?= e($i18n->url('/privacidade')) ?>"><?= e($i18n->t('legal.privacidade')) ?></a>
    </nav>
    <p class="footer-meta"><?= e($i18n->t('footer.by')) ?></p>
  </div>
</footer>

<script src="/assets/app.js?v=18" defer></script>
</body>
</html>
