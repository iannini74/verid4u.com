<?php
/** @var I18n $i18n */
/** @var array $record */
$code = Code::format($record['code']);
$verifyUrl = base_url() . $i18n->url('/v/' . $code);
?>
<div class="sheet-actions no-print">
  <button class="button ghost" type="button" onclick="window.print()"><?= e($i18n->a('cert.print')) ?></button>
  <a class="quiet-link" href="<?= e($i18n->url('/app/certificados')) ?>"><?= e($i18n->a('list.title')) ?></a>
</div>

<article class="sheet">
  <header class="sheet-head">
    <p class="sheet-issuer"><?= e($record['issuer_name']) ?></p>
    <?php if (!empty($record['issuer_cnpj'])): ?>
      <p class="sheet-legal">CNPJ <?= e($record['issuer_cnpj']) ?></p>
    <?php endif; ?>
  </header>

  <p class="sheet-kicker"><?= e($i18n->a('cert.title')) ?></p>
  <p class="sheet-line"><?= e($i18n->a('cert.declares')) ?></p>
  <p class="sheet-holder"><?= e($record['holder_name']) ?></p>
  <p class="sheet-line"><?= e($i18n->a('cert.completed')) ?></p>
  <p class="sheet-program"><?= e($record['program']) ?></p>

  <p class="sheet-detail">
    <?= e($i18n->a('cert.hours', ['h' => (int) $record['hours'], 'm' => $i18n->t('modality.' . $record['modality'])])) ?><?php
      if ($record['assessed']) { echo ', ' . e($i18n->a('cert.assessed')); }
    ?>,
    <?= e($i18n->a('cert.on', ['d' => $i18n->date($record['issued_on'])])) ?>.
  </p>

  <p class="sheet-validity">
    <?= $record['valid_until'] === null
        ? e($i18n->a('cert.lifetime'))
        : e($i18n->a('cert.valid_until', ['d' => $i18n->date($record['valid_until'])])) ?>
  </p>

  <!--
    A tarja. Ordem fixa: QR, marca, código, domínio. Fixa porque quem confere
    aprende o lugar de cada coisa uma vez — tarja que muda de arranjo obriga a
    reaprender, e quem está conferindo um certificado não está com paciência.

    À direita, o selo circular. É o único selo nosso que é redondo, e é essa a
    função do círculo: separar, de longe, "emitido aqui" de "registrado aqui".
  -->
  <footer class="sheet-strip">
    <div class="cert-qr" data-qr="<?= e($verifyUrl) ?>" aria-hidden="true"></div>
    <?= Brand::mark('fit', 'sheet-strip-mark') ?>
    <div class="sheet-strip-text">
      <p class="sheet-code"><code><?= e($code) ?></code></p>
      <p class="sheet-verify"><?= e($i18n->t('cert.strip.line')) ?></p>
      <p class="sheet-domain">VERID4U.COM</p>
    </div>
    <?= Brand::roundSeal($i18n->t('cert.seal.top'), $i18n->t('cert.seal.bottom'), 'cert-round-seal') ?>
  </footer>
</article>
