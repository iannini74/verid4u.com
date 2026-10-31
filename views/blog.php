<?php
/** @var I18n $i18n */
/** @var list<array> $posts */
$pageTitle = $i18n->t('blog.title') . ' · ' . APP_NAME;
$pageDesc = $i18n->t('blog.desc');
?>
<section class="section">
  <div class="wrap-narrow">
    <p class="overline"><?= e($i18n->t('blog.kicker')) ?></p>
    <h1 class="display-l"><?= e($i18n->t('blog.title')) ?></h1>
    <p class="lead measure"><?= e($i18n->t('blog.desc')) ?></p>

    <?php if ($posts === []): ?>
      <p class="muted" style="margin-top:var(--space-7)"><?= e($i18n->t('blog.empty')) ?></p>
    <?php else: ?>
      <ol class="post-list">
        <?php foreach ($posts as $p): ?>
          <li>
            <a href="<?= e($i18n->url('/blog/' . $p['slug'])) ?>">
              <p class="post-pilar"><?= e($p['pilar'] ?? '') ?></p>
              <h2><?= e($p['titulo']) ?></h2>
              <p class="post-resumo"><?= e($p['resumo'] ?? '') ?></p>
              <p class="post-meta">
                <time datetime="<?= e($p['publicado_em']) ?>"><?= e($i18n->date($p['publicado_em'])) ?></time>
                <?php if (!empty($p['leitura'])): ?>
                  · <?= e($i18n->t('blog.reading', ['n' => (int) $p['leitura']])) ?>
                <?php endif; ?>
              </p>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</section>
