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

  <footer class="sheet-strip">
    <div class="cert-qr" data-qr="<?= e($verifyUrl) ?>" aria-hidden="true"></div>
    <div class="sheet-strip-text">
      <p class="sheet-verify"><?= e($i18n->a('cert.verify')) ?> <strong>verid4u.com</strong></p>
      <p class="sheet-code"><code><?= e($code) ?></code></p>
    </div>
    <div class="cert-seal">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 3.6 5.2v6.6c0 5.3 3.6 8.6 8.4 9.9 4.8-1.3 8.4-4.6 8.4-9.9V5.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m8.2 12.1 2.8 2.9 5-5.6" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
  </footer>
</article>
