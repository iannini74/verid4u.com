<?php
/** @var I18n $i18n */
/** @var array $post */
$pageTitle = $post['titulo'] . ' · ' . APP_NAME;
$pageDesc = (string) ($post['resumo'] ?? '');
$url = base_url() . $i18n->url('/blog/' . $post['slug']);
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['titulo'],
    'description' => $post['resumo'] ?? '',
    'datePublished' => $post['publicado_em'],
    'dateModified' => $post['verificado_em'] ?? $post['publicado_em'],
    'author' => ['@type' => 'Organization', 'name' => $post['autor'] ?? APP_NAME],
    'publisher' => ['@type' => 'Organization', 'name' => APP_NAME],
    'mainEntityOfPage' => $url,
    'inLanguage' => $i18n->tag(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
?>
<article class="section">
  <div class="wrap-narrow post">
    <p class="overline"><a href="<?= e($i18n->url('/blog')) ?>"><?= e($i18n->t('blog.title')) ?></a> · <?= e($post['pilar'] ?? '') ?></p>
    <h1 class="display-l"><?= e($post['titulo']) ?></h1>
    <p class="lead measure"><?= e($post['resumo'] ?? '') ?></p>

    <p class="post-meta post-meta-top">
      <time datetime="<?= e($post['publicado_em']) ?>"><?= e($i18n->date($post['publicado_em'])) ?></time>
      <?php if (!empty($post['leitura'])): ?> · <?= e($i18n->t('blog.reading', ['n' => (int) $post['leitura']])) ?><?php endif; ?>
      <?php if (!empty($post['verificado_em'])): ?>
        · <span class="post-checked" title="<?= e($i18n->t('blog.checked.why')) ?>">
            <?= e($i18n->t('blog.checked', ['d' => $i18n->date($post['verificado_em'])])) ?>
          </span>
      <?php endif; ?>
    </p>

    <div class="post-body"><?= Blog::html((array) ($post['corpo'] ?? [])) ?></div>

    <aside class="post-cta">
      <p><strong><?= e($i18n->t('blog.cta.title')) ?></strong></p>
      <p><?= e($i18n->t('blog.cta.body')) ?></p>
      <a class="btn btn-primary" href="<?= e($i18n->url()) ?>"><?= e($i18n->t('blog.cta.button')) ?></a>
    </aside>
  </div>
</article>
