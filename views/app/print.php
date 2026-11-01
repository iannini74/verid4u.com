<?php
/** @var I18n $i18n */
/** @var string $content */
?>
<!doctype html>
<html lang="<?= e($i18n->tag()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? $i18n->a('cert.title')) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="/assets/brand/mark-solid.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/inter-tight-normal-600-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/inter-normal-400-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/fonts.css?v=18">
<link rel="stylesheet" href="/assets/style.css?v=19">
</head>
<body class="print-body">
<?= $content ?>
<script src="/assets/app.js?v=18" defer></script>
</body>
</html>
