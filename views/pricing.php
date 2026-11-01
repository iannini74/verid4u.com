<?php
/**
 * Preços.
 *
 * A dobra que faltava, e a que mais exige honestidade: é aqui que alguém
 * decide confiar dinheiro no produto. Três regras governam o texto desta
 * página, e as três estão no código antes de estarem aqui.
 *
 * @var I18n $i18n
 * @var list<array> $plans
 */
$pageTitle = $i18n->t('pricing.meta.title');
$pageDesc = $i18n->t('pricing.meta.desc');
?>

<section class="section">
  <div class="wrap">
    <p class="overline"><?= e($i18n->t('pricing.kicker')) ?></p>
    <h1 class="display-l measure stack-lead"><?= e($i18n->t('pricing.title')) ?></h1>
    <p class="lead measure"><?= e($i18n->t('pricing.lead', ['n' => Plan::FREE_MONTHLY])) ?></p>
  </div>
</section>

<section class="wrap">
  <div class="tiers">
    <?php foreach ($plans as $p):
      $mensal = Catalog::price($p, 'monthly');
      $anual = (int) ($p['yearly_cents'] ?? 0);
      $gratuito = $mensal['cents'] === 0;
      $destaque = (string) $p['code'] === 'pro';
    ?>
      <article class="tier<?= $destaque ? ' tier-pick' : '' ?>">
        <?php if ($destaque): ?><p class="tier-flag"><?= e($i18n->t('pricing.most')) ?></p><?php endif; ?>

        <h2 class="tier-name"><?= e((string) $p['name']) ?></h2>

        <p class="tier-price">
          <?php if ($gratuito): ?>
            <span class="tier-free"><?= e($i18n->t('pricing.free')) ?></span>
          <?php else: ?>
            <?php if ($mensal['promo']): ?>
              <s class="tier-was"><?= e(Catalog::money($mensal['full'])) ?></s>
            <?php endif; ?>
            <span class="tier-now"><?= e(Catalog::money($mensal['cents'])) ?></span>
            <span class="tier-per"><?= e($i18n->t('pricing.per_month')) ?></span>
          <?php endif; ?>
        </p>

        <?php if (!$gratuito && $anual > 0): ?>
          <p class="tier-year"><?= e($i18n->t('pricing.yearly', ['v' => Catalog::money($anual)])) ?></p>
        <?php endif; ?>

        <p class="tier-quota">
          <strong><?= e(number_format((int) $p['monthly_quota'], 0, ',', '.')) ?></strong>
          <?= e($i18n->t('pricing.quota')) ?>
        </p>

        <ul class="tier-list">
          <li><?= e($i18n->t('pricing.inc.verify')) ?></li>
          <li><?= e($i18n->t('pricing.inc.both')) ?></li>
          <li><?= e($i18n->t('pricing.inc.seal')) ?></li>
          <li><?= e($i18n->t('pricing.inc.api')) ?></li>
          <?php if ((int) $p['brand_included'] === 1): ?>
            <li class="tier-plus"><?= e($i18n->t('pricing.inc.brand', ['n' => Plan::BRAND_THRESHOLD])) ?></li>
          <?php endif; ?>
        </ul>

        <a class="btn <?= $destaque ? 'btn-primary' : 'btn-ghost' ?>"
           href="<?= e($i18n->url('/app/criar-conta')) ?>">
          <?= e($i18n->t($gratuito ? 'pricing.cta.free' : 'pricing.cta.paid')) ?>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section bg-sunken">
  <div class="wrap-narrow">
    <p class="overline"><?= e($i18n->t('pricing.promises')) ?></p>
    <h2 class="display-m stack-lead"><?= e($i18n->t('pricing.promises.title')) ?></h2>

    <div class="notproven">
      <ul>
        <li><strong><?= e($i18n->t('pricing.p1.t')) ?></strong> <?= e($i18n->t('pricing.p1.b')) ?></li>
        <li><strong><?= e($i18n->t('pricing.p2.t')) ?></strong> <?= e($i18n->t('pricing.p2.b')) ?></li>
        <li><strong><?= e($i18n->t('pricing.p3.t')) ?></strong> <?= e($i18n->t('pricing.p3.b')) ?></li>
        <li><strong><?= e($i18n->t('pricing.p4.t')) ?></strong> <?= e($i18n->t('pricing.p4.b')) ?></li>
      </ul>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap-narrow">
    <h2 class="display-m stack-lead"><?= e($i18n->t('pricing.faq')) ?></h2>
    <div class="faq">
      <?php
      $perguntas = [
          ['pricing.q1', $i18n->t('pricing.a1', ['n' => Plan::FREE_MONTHLY])],
          ['pricing.q2', $i18n->t('pricing.a2')],
          ['pricing.q3', $i18n->t('pricing.a3', ['n' => Plan::BRAND_THRESHOLD])],
          ['pricing.q4', $i18n->t('pricing.a4')],
      ];
      ?>
      <?php foreach ($perguntas as $i => [$chave, $resposta]): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($i18n->t($chave)) ?></summary>
          <p><?= e($resposta) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
