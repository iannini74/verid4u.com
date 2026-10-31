<?php
/**
 * Demonstração em três passos.
 *
 * Um espaço vazio esperando vídeo é pior que nenhuma seção: o visitante vê
 * um retângulo quebrado. Então aqui há uma demonstração real, montada com
 * os mesmos componentes do produto. Quando houver vídeo, basta preencher
 * 'video' em content/demo.<locale>.php — a demonstração sai e o vídeo entra.
 *
 * @var I18n $i18n
 * @var string $sample
 */
$video = '';
$arqDemo = __DIR__ . '/../../content/demo.' . $i18n->locale() . '.php';
if (is_file($arqDemo)) {
    $cfg = require $arqDemo;
    $video = is_array($cfg) ? (string) ($cfg['video'] ?? '') : '';
}
$verifyUrl = base_url() . $i18n->url('/v/' . $sample);
?>
<section class="section bg-sunken" id="demonstracao">
  <div class="wrap">
    <p class="overline" data-reveal><?= e($i18n->t('demo.kicker')) ?></p>
    <h2 class="display-m measure stack-lead" data-reveal><?= e($i18n->t('demo.title')) ?></h2>

    <?php if ($video !== ''): ?>
      <div class="demo-video" data-reveal>
        <iframe src="<?= e($video) ?>" title="<?= e($i18n->t('demo.title')) ?>"
                loading="lazy" allowfullscreen referrerpolicy="no-referrer"></iframe>
      </div>
    <?php else: ?>
      <div class="demo" data-demo data-reveal>
        <ol class="demo-steps" role="tablist" aria-label="<?= e($i18n->t('demo.title')) ?>">
          <?php for ($n = 1; $n <= 3; $n++): ?>
            <li role="presentation">
              <button type="button" role="tab" id="demo-tab-<?= $n ?>"
                      aria-controls="demo-panel-<?= $n ?>"
                      aria-selected="<?= $n === 1 ? 'true' : 'false' ?>"
                      tabindex="<?= $n === 1 ? '0' : '-1' ?>"
                      data-demo-tab="<?= $n ?>">
                <span class="demo-n">0<?= $n ?></span>
                <span class="demo-label"><?= e($i18n->t("demo.$n.label")) ?></span>
                <span class="demo-hint"><?= e($i18n->t("demo.$n.hint")) ?></span>
              </button>
            </li>
          <?php endfor; ?>
        </ol>

        <div class="demo-stage">

          <figure class="demo-panel" id="demo-panel-1" role="tabpanel" aria-labelledby="demo-tab-1" data-demo-panel="1">
            <div class="demo-sheet">
              <p class="demo-sheet-kicker"><?= e($i18n->t('cert.title')) ?></p>
              <p class="demo-sheet-holder">Maria S. L.</p>
              <p class="demo-sheet-program">NR-35 — Trabalho em Altura</p>
              <p class="demo-sheet-detail">8 <?= e($i18n->t('label.hours')) ?> · <?= e($i18n->t('modality.presencial')) ?></p>
              <div class="demo-strip">
                <div class="demo-strip-qr" data-qr="<?= e($verifyUrl) ?>" aria-hidden="true"></div>
                <?= Brand::mark('open', 'demo-strip-mark') ?>
                <div>
                  <code><?= e($sample) ?></code>
                  <span>verid4u.com</span>
                </div>
              </div>
            </div>
          </figure>

          <figure class="demo-panel" id="demo-panel-2" role="tabpanel" aria-labelledby="demo-tab-2" data-demo-panel="2" hidden>
            <div class="demo-phone">
              <div class="demo-phone-screen">
                <div class="demo-phone-qr" data-qr="<?= e($verifyUrl) ?>" aria-hidden="true"></div>
                <p class="demo-phone-url"><?= e($verifyUrl) ?></p>
              </div>
            </div>
          </figure>

          <figure class="demo-panel" id="demo-panel-3" role="tabpanel" aria-labelledby="demo-tab-3" data-demo-panel="3" hidden>
            <div class="hero-card demo-verdict">
              <div class="hero-card-top">
                <?php $sealClass = 'hero-card-seal'; $sealGlyph = 'check'; require __DIR__ . '/seal.php'; ?>
                <div>
                  <div class="hero-card-verdict"><?= e($i18n->t('status.verified')) ?></div>
                  <div class="hero-card-code"><?= e($sample) ?></div>
                </div>
              </div>
              <dl>
                <div><dt><?= e($i18n->t('label.holder')) ?></dt><dd>Maria S. L.</dd></div>
                <div><dt><?= e($i18n->t('label.program')) ?></dt><dd>NR-35</dd></div>
                <div><dt><?= e($i18n->t('label.modality')) ?></dt><dd><?= e($i18n->t('modality.presencial')) ?></dd></div>
                <div><dt><?= e($i18n->t('label.validity')) ?></dt><dd><?= e($i18n->date('2028-03-10')) ?></dd></div>
              </dl>
              <p class="hero-card-foot"><?= e($i18n->t('demo.3.foot')) ?></p>
            </div>
          </figure>

        </div>
      </div>

      <p class="demo-try" data-reveal>
        <?= e($i18n->t('demo.try')) ?>
        <a href="<?= e($i18n->url('/v/' . $sample)) ?>"><?= e($sample) ?></a>
      </p>
    <?php endif; ?>
  </div>
</section>
