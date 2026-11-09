<?php
/**
 * Layout da área da plataforma.
 *
 * Deliberadamente escuro e diferente do painel do cliente. Quem administra
 * passa o dia alternando entre os dois, e confundir a tela em que se está é
 * como se acaba mexendo na conta errada — a diferença visual é controle, não
 * enfeite.
 *
 * @var I18n $i18n
 * @var string $content
 */
$user = $user ?? null;
$atual = $atual ?? '';
$nav = ['visao' => ['', 'Visão'], 'clientes' => ['clientes', 'Clientes'], 'fila' => ['fila', 'Fila']];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Plataforma') ?> · VerID4U</title>
<link rel="icon" href="/assets/brand/mark-solid.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/fonts.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="/assets/style.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="/assets/admin.css?v=<?= ASSET_VERSION ?>">
</head>
<body class="adm">
<header class="adm-bar">
  <div class="adm-bar-in">
    <a class="adm-brand" href="/admin">
      <?= Brand::mark('open', 'adm-mark') ?>
      <span>Plataforma</span>
    </a>
    <?php if ($user !== null && (string) ($user['role'] ?? '') === 'platform'): ?>
      <nav class="adm-nav">
        <?php foreach ($nav as $chave => [$caminho, $rotulo]): ?>
          <a href="/admin/<?= e($caminho) ?>"<?= $atual === $chave ? ' aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="adm-you">
        <span><?= e((string) $user['name']) ?></span>
        <a href="/pt-br/app/">Painel do cliente</a>
        <a href="/admin/sair">Encerrar o fator</a>
      </div>
    <?php endif; ?>
  </div>
</header>
<main class="adm-main"><?= $content ?></main>
</body>
</html>
