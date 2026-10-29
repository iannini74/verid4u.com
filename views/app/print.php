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
<title><?= e($i18n->a('cert.title')) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="/assets/brand/mark-solid.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="/assets/style.css?v=8">
</head>
<body class="print-body">
<?= $content ?>
<script src="/assets/app.js?v=8" defer></script>
</body>
</html>
