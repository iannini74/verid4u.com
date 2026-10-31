<?php
/** @var I18n $i18n */
/** @var array $doc */
/** @var string $slug */
$empresa = Legal::empresa();
$pendencias = Legal::pendencias();
$pageTitle = $doc['titulo'] . ' · ' . APP_NAME;
$pageDesc = $doc['resumo'];
?>
<section class="section">
  <div class="wrap-narrow legal">
    <p class="overline"><?= e(APP_NAME) ?></p>
    <h1 class="display-l"><?= e($doc['titulo']) ?></h1>
    <p class="lead measure"><?= e($doc['resumo']) ?></p>
    <p class="legal-meta">
      <?= e($i18n->t('legal.updated')) ?>
      <time datetime="<?= e((string) ($empresa['atualizado'] ?? '')) ?>"><?= e($i18n->date((string) ($empresa['atualizado'] ?? ''))) ?></time>
    </p>

    <?php if ($pendencias !== []): ?>
      <p class="legal-pending" role="note">
        <strong><?= e($i18n->t('legal.pending.title')) ?></strong>
        <?= e($i18n->t('legal.pending.body', ['campos' => implode(', ', $pendencias)])) ?>
      </p>
    <?php endif; ?>

    <nav class="legal-nav" aria-label="<?= e($doc['titulo']) ?>">
      <?php foreach ($doc['secoes'] as $n => $s): ?>
        <a href="#s<?= $n ?>"><?= e(Legal::fill($s['h'])) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php foreach ($doc['secoes'] as $n => $s): ?>
      <section class="legal-sec" id="s<?= $n ?>">
        <h2><?= e(Legal::fill($s['h'])) ?></h2>
        <?php foreach ($s['p'] as $par): ?>
          <p><?= Legal::html($par) ?></p>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    <p class="legal-foot">
      <?php foreach (Legal::DOCS as $outro): if ($outro === $slug) continue; ?>
        <a href="<?= e($i18n->url('/' . $outro)) ?>"><?= e($i18n->t('legal.' . $outro)) ?></a>
      <?php endforeach; ?>
      <a href="<?= e($i18n->url()) ?>"><?= e($i18n->t('verify.back')) ?></a>
    </p>
  </div>
</section>
