<?php
/**
 * Comprovante de autenticação — o quarto selo.
 *
 * O carimbo aqui não diz "verificação ativa", e isso é deliberado. No papel
 * o resultado é de um instante passado: o que o documento pode afirmar com
 * honestidade é a data, a hora com fuso, o código próprio e o convite a
 * reconsultar. Dizer "ativo" num papel seria mentir no dia seguinte a uma
 * revogação — o dia em que o comprovante mais importa.
 *
 * Revogado e não encontrado voltam à cunha solta: nada encaixou.
 *
 * @var I18n $i18n
 * @var ?array $receipt
 * @var string $typed
 */
$pageTitle = $i18n->t('receipt.meta.title');

if ($receipt === null) {
    ?>
    <article class="sheet sheet-receipt">
      <h1 class="receipt-title"><?= e($i18n->t('receipt.notfound.title')) ?></h1>
      <p class="receipt-lead"><?= e($i18n->t('receipt.notfound.body')) ?></p>
      <p class="receipt-typed"><code><?= e($typed) ?></code></p>
      <p class="no-print"><a class="button ghost" href="/"><?= e($i18n->t('verify.back')) ?></a></p>
    </article>
    <?php
    return;
}

$snap = $receipt['snapshot'];
$outcome = $receipt['outcome'];
$encaixou = in_array($outcome, ['verified', 'expiring', 'expired'], true);
$verifyPath = '/' . $receipt['locale'] . '/v/' . Code::format($receipt['code']);
$verifyUrl = base_url() . $verifyPath;
$stampKey = in_array($outcome, ['verified', 'expiring', 'expired', 'revoked'], true)
    ? $outcome
    : 'notfound';
?>

<div class="sheet-actions no-print">
  <button class="button ghost" type="button" onclick="window.print()"><?= e($i18n->t('receipt.print')) ?></button>
  <a class="quiet-link" href="<?= e($verifyPath) ?>"><?= e($i18n->t('receipt.recheck.button')) ?></a>
</div>

<article class="sheet sheet-receipt">
  <header class="receipt-head">
    <span class="receipt-brand">
      <?= Brand::mark('fit', 'receipt-mark') ?>
      <span>VerID<span class="brand-accent">4U</span></span>
    </span>
    <p class="receipt-kicker"><?= e($i18n->t('receipt.kicker')) ?></p>
  </header>

  <h1 class="receipt-title"><?= e($i18n->t('receipt.title')) ?></h1>

  <?php if (!empty($snap['is_demo'])): ?>
    <p class="demo-note"><strong>Demo</strong> <?= e($i18n->t('demo.banner')) ?></p>
  <?php endif; ?>

  <!-- O carimbo. Quatro resultados, a cunha encaixa só nos três que batem. -->
  <div class="stamp stamp-<?= e($stampKey) ?>">
    <?= Brand::mark($encaixou ? 'fit' : 'open', 'stamp-mark') ?>
    <div class="stamp-text">
      <p class="stamp-verdict"><?= e($i18n->t('receipt.stamp.' . $stampKey)) ?></p>
      <p class="stamp-line"><?= e($i18n->t('receipt.kicker')) ?></p>
      <p class="stamp-when">
        <?= e($i18n->t('receipt.checked_at')) ?> <?= e($i18n->datetime($receipt['checked_at'])) ?>
      </p>
      <p class="stamp-code">
        <code><?= e(Receipt::display($receipt['base'])) ?></code>
        <span><?= e($i18n->t('receipt.recheck')) ?></span>
      </p>
    </div>
  </div>

  <?php if ($snap !== []): ?>
    <p class="receipt-section"><?= e($i18n->t('receipt.subject')) ?></p>
    <dl class="facts">
      <div><dt><?= e($i18n->t('label.code')) ?></dt><dd><code><?= e(Code::format($receipt['code'])) ?></code></dd></div>
      <div><dt><?= e($i18n->t('label.holder')) ?></dt><dd><?= e((string) ($snap['holder'] ?? '')) ?></dd></div>
      <div class="wide"><dt><?= e($i18n->t('label.program')) ?></dt><dd><?= e((string) ($snap['program'] ?? '')) ?></dd></div>
      <div><dt><?= e($i18n->t('label.hours')) ?></dt><dd><?= e($i18n->t('value.hours', ['h' => (int) ($snap['hours'] ?? 0)])) ?></dd></div>
      <div><dt><?= e($i18n->t('label.modality')) ?></dt><dd><?= e($i18n->t('modality.' . (string) ($snap['modality'] ?? 'presencial'))) ?></dd></div>
      <div><dt><?= e($i18n->t('label.issued')) ?></dt><dd><?= e($i18n->date($snap['issued_on'] ?? null)) ?></dd></div>
      <div>
        <dt><?= e($i18n->t('label.validity')) ?></dt>
        <dd><?= ($snap['valid_until'] ?? null) === null
              ? e($i18n->t('value.lifetime'))
              : e($i18n->date((string) $snap['valid_until'])) ?></dd>
      </div>
      <div class="wide"><dt><?= e($i18n->t('label.issuer')) ?></dt>
        <dd><?= e((string) ($snap['issuer_name'] ?? '')) ?><?php
          if (!empty($snap['issuer_cnpj'])) { echo ' — CNPJ ' . e((string) $snap['issuer_cnpj']); }
        ?></dd>
      </div>
      <div class="wide"><dt><?= e($i18n->t('origin.' . (string) ($snap['origin'] ?? 'issued'))) ?></dt>
        <dd><?= e($i18n->t('origin.' . (string) ($snap['origin'] ?? 'issued') . '.desc')) ?></dd>
      </div>
      <?php if (!empty($snap['revoked_on'])): ?>
        <div><dt><?= e($i18n->t('label.revoked')) ?></dt><dd><?= e($i18n->date((string) $snap['revoked_on'])) ?></dd></div>
        <div class="wide"><dt><?= e($i18n->t('label.reason')) ?></dt><dd><?= e((string) ($snap['revoke_reason'] ?? '')) ?></dd></div>
      <?php endif; ?>
      <?php if (!empty($snap['hash'])): ?>
        <div class="wide"><dt><?= e($i18n->t('proof.hash')) ?></dt><dd><code class="hash"><?= e((string) $snap['hash']) ?></code></dd></div>
      <?php endif; ?>
    </dl>
  <?php else: ?>
    <p class="receipt-lead"><?= e($i18n->t('error.notfound.body')) ?></p>
    <p class="receipt-typed"><code><?= e(Code::format($receipt['code'])) ?></code></p>
  <?php endif; ?>

  <footer class="receipt-foot">
    <div class="cert-qr" data-qr="<?= e($verifyUrl) ?>" aria-hidden="true"></div>
    <div>
      <p class="receipt-foot-url"><?= e($verifyUrl) ?></p>
      <p class="receipt-foot-note"><?= e($i18n->t('receipt.why.title')) ?> <?= e($i18n->t('receipt.why.body')) ?></p>
    </div>
  </footer>
</article>
