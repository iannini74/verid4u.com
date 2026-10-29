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
<meta name="theme-color" content="#123E6B">
<link rel="icon" href="/assets/mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="/assets/style.css?v=3">
</head>
<body>
<a class="skip" href="#main"><?= e($i18n->t('hero.title')) ?></a>

<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="<?= e($i18n->url()) ?>">
      <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
        <path d="M16 2.5 3.5 7v9.2c0 7.4 5 12 12.5 13.8 7.5-1.8 12.5-6.4 12.5-13.8V7Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
        <path d="m10.5 16.2 4 4.1 7.2-8" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="brand-text">VerID<span class="brand-accent">4U</span></span>
    </a>

    <nav class="site-nav" aria-label="<?= e($i18n->t('nav.how')) ?>">
      <a href="<?= e($i18n->url()) ?>#como"><?= e($i18n->t('nav.how')) ?></a>
      <a href="<?= e($i18n->url()) ?>#para-quem"><?= e($i18n->t('nav.audiences')) ?></a>
      <a href="<?= e($i18n->url()) ?>#duvidas"><?= e($i18n->t('nav.faq')) ?></a>
    </nav>

    <div class="lang" role="group" aria-label="Idioma">
      <?php foreach (I18n::LOCALES as $loc => $meta): ?>
        <a
          href="<?= e(localized_path($loc)) ?>"
          hreflang="<?= e($meta['tag']) ?>"
          title="<?= e($meta['name']) ?>"
          <?= $loc === $i18n->locale() ? 'aria-current="true"' : '' ?>
        ><?= e($meta['short']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<main id="main"><?= $content ?></main>

<footer class="site-footer">
  <div class="wrap footer-row">
    <p class="footer-brand">VerID<span class="brand-accent">4U</span> — <?= e($i18n->t('site.tagline')) ?></p>
    <p class="footer-meta"><?= e($i18n->t('footer.by')) ?></p>
  </div>
</footer>

<script src="/assets/app.js?v=3" defer></script>
</body>
</html>
