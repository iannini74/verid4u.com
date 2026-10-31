<?php
/**
 * Página pública do emissor: o destino do selo.
 *
 * Um selo que aponta para lugar nenhum é adesivo. Um selo que aponta para
 * uma página nossa, com o nome do emissor e os números do registro, é
 * verificação — quem clica sai sabendo mais do que sabia.
 *
 * Só agregados. Nenhum nome de titular sai daqui.
 *
 * @var I18n $i18n
 * @var array $issuer
 */
$pageTitle = $i18n->t('issuer.meta.title', ['nome' => $issuer['name']]);
$pageDesc = $i18n->t('issuer.meta.desc', ['nome' => $issuer['name']]);
$qualifier = Brand::qualifier($issuer['category'], $i18n);
$conferido = (int) $issuer['level'] >= 1;
$since = substr((string) ($issuer['first_on'] ?? $issuer['created_at'] ?? ''), 0, 10);
?>

<section class="section issuer-head">
  <div class="wrap-narrow">
    <p class="overline"><?= e($i18n->t('issuer.kicker')) ?></p>

    <div class="issuer-id">
      <span class="issuer-id-mark"><?= Brand::mark($conferido ? 'fit' : 'open', 'issuer-mark') ?></span>
      <div>
        <h1 class="display-m"><?= e($issuer['name']) ?></h1>
        <p class="issuer-qualifier"><?= e($qualifier) ?></p>
        <?php if (!empty($issuer['legal_id'])): ?>
          <p class="issuer-cnpj">CNPJ <?= e($issuer['legal_id']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <p class="issuer-level level-<?= (int) $issuer['level'] ?>">
      <span class="level-dot" aria-hidden="true"></span>
      <span><strong><?= e($i18n->t('label.level')) ?>:</strong> <?= e($i18n->t('level.' . (int) $issuer['level'])) ?></span>
    </p>
  </div>
</section>

<section class="wrap-narrow">
  <dl class="issuer-counts">
    <div>
      <dt><?= e($i18n->t('issuer.counts.total')) ?></dt>
      <dd><?= e(number_format((int) $issuer['total'], 0, ',', '.')) ?></dd>
    </div>
    <div>
      <dt><?= e($i18n->t('issuer.counts.issued')) ?></dt>
      <dd><?= e(number_format((int) $issuer['issued'], 0, ',', '.')) ?></dd>
    </div>
    <div>
      <dt><?= e($i18n->t('issuer.counts.registered')) ?></dt>
      <dd><?= e(number_format((int) $issuer['registered'], 0, ',', '.')) ?></dd>
    </div>
    <?php if ((int) $issuer['revoked'] > 0): ?>
      <div>
        <dt><?= e($i18n->t('issuer.counts.revoked')) ?></dt>
        <dd><?= e(number_format((int) $issuer['revoked'], 0, ',', '.')) ?></dd>
      </div>
    <?php endif; ?>
    <?php if ($since !== ''): ?>
      <div>
        <dt><?= e($i18n->t('issuer.since')) ?></dt>
        <dd class="issuer-since"><?= e($i18n->date($since)) ?></dd>
      </div>
    <?php endif; ?>
  </dl>
</section>

<section class="wrap-narrow panel">
  <p class="panel-kicker"><?= e($i18n->t('issuer.verify.title')) ?></p>
  <form method="post" action="<?= e($i18n->url()) ?>" class="verifier compact">
    <div class="verifier-row">
      <label class="sr-only" for="codigo-emissor"><?= e($i18n->t('hero.label')) ?></label>
      <input id="codigo-emissor" name="codigo" type="text" autocomplete="off" autocapitalize="characters"
             spellcheck="false" maxlength="20" placeholder="<?= e($i18n->t('hero.placeholder')) ?>" data-code-input>
      <button type="submit"><?= e($i18n->t('hero.button')) ?></button>
    </div>
    <p class="verifier-hint"><?= e($i18n->t('hero.hint')) ?></p>
  </form>
</section>

<section class="wrap-narrow">
  <div class="notproven">
    <h2><?= e($i18n->t('issuer.what.title')) ?></h2>
    <ul>
      <li><?= e($i18n->t('issuer.what.1')) ?></li>
      <li><?= e($i18n->t('issuer.what.2')) ?></li>
      <li><?= e($i18n->t('issuer.what.3')) ?></li>
    </ul>
  </div>

  <div class="issuer-privacy">
    <h2><?= e($i18n->t('issuer.privacy.title')) ?></h2>
    <p><?= e($i18n->t('issuer.privacy.body')) ?></p>
  </div>
</section>
