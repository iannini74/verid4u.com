<?php
/** @var I18n $i18n */
/** @var ?array $record */
/** @var string $state */
/** @var string $typed */
$pageNoindex = true;
$pageTitle = $i18n->t('meta.verify.title') . ' · ' . APP_NAME;
$pageDesc = $i18n->t('meta.home.desc');

$icons = [
    'verified' => '<path d="m6 12.4 4 4.1 8-9" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>',
    'expiring' => '<circle cx="12" cy="12" r="8.4" fill="none" stroke="currentColor" stroke-width="2.1"/><path d="M12 7.2V12l3.2 2" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/>',
    'expired'  => '<circle cx="12" cy="12" r="8.4" fill="none" stroke="currentColor" stroke-width="2.1"/><path d="M12 7.2V12l3.2 2" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/><path d="M4.6 4.6 19.4 19.4" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/>',
    'revoked'  => '<circle cx="12" cy="12" r="8.4" fill="none" stroke="currentColor" stroke-width="2.1"/><path d="M6.4 6.4 17.6 17.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
    'notfound' => '<circle cx="12" cy="12" r="8.4" fill="none" stroke="currentColor" stroke-width="2.1"/><path d="M9.3 9.2a2.8 2.8 0 1 1 3.3 3.3v1.3" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/><circle cx="12.4" cy="17" r="1.2" fill="currentColor"/>',
];
$icons['throttled'] = $icons['notfound'];

$verdict = $i18n->t('status.' . ($state === 'throttled' ? 'notfound' : $state));
$shareUrl = base_url() . $i18n->url('/v/' . $typed);
?>

<section class="verdict verdict-<?= e($state) ?>">
  <div class="wrap narrow">
    <p class="verdict-code"><?= e($i18n->t('label.code')) ?> <code><?= e($typed) ?></code></p>

    <h1 class="verdict-headline">
      <span class="verdict-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><?= $icons[$state] ?? $icons['notfound'] ?></svg>
      </span>
      <span><?= e($verdict) ?></span>
    </h1>

    <?php if ($state === 'throttled'): ?>
      <p class="verdict-explain">Muitas consultas em pouco tempo. Aguarde alguns minutos e tente de novo.</p>
    <?php elseif ($record === null): ?>
      <p class="verdict-explain"><?= e($i18n->t('error.notfound.body')) ?></p>
    <?php else: ?>
      <p class="verdict-explain">
        <?= e($i18n->t('status.' . $state . '.explain', ['days' => abs((int) ($record['days_left'] ?? 0))])) ?>
      </p>
    <?php endif; ?>
  </div>
</section>

<?php if ($record !== null): ?>

  <?php if (Store::DEMO): ?>
    <div class="wrap narrow">
      <p class="demo-note">
        <strong>Demo</strong> <?= e($i18n->t('demo.banner')) ?>
      </p>
    </div>
  <?php endif; ?>

  <section class="wrap narrow panel">
    <dl class="facts">
      <div><dt><?= e($i18n->t('label.holder')) ?></dt><dd><?= e(Store::displayName($record)) ?></dd></div>
      <div><dt><?= e($i18n->t('label.program')) ?></dt><dd><?= e($record['program']) ?></dd></div>
      <div><dt><?= e($i18n->t('label.hours')) ?></dt><dd><?= e($i18n->t('value.hours', ['h' => (int) $record['hours']])) ?></dd></div>
      <div><dt><?= e($i18n->t('label.modality')) ?></dt><dd><?= e($i18n->t('modality.' . $record['modality'])) ?></dd></div>
      <div><dt><?= e($i18n->t('label.assessed')) ?></dt><dd><?= e($i18n->t($record['assessed'] ? 'value.assessed' : 'value.not_assessed')) ?></dd></div>
      <div><dt><?= e($i18n->t('label.issued')) ?></dt><dd><?= e($i18n->date($record['issued_on'])) ?></dd></div>
      <div>
        <dt><?= e($i18n->t('label.validity')) ?></dt>
        <dd><?= $record['valid_until'] === null ? e($i18n->t('value.lifetime')) : e($i18n->date($record['valid_until'])) ?></dd>
      </div>
      <?php if (!empty($record['revoked_on'])): ?>
        <div><dt><?= e($i18n->t('label.revoked')) ?></dt><dd><?= e($i18n->date($record['revoked_on'])) ?></dd></div>
        <div class="wide"><dt><?= e($i18n->t('label.reason')) ?></dt><dd><?= e($record['revoke_reason']) ?></dd></div>
      <?php endif; ?>
    </dl>
  </section>

  <section class="wrap narrow panel issuer">
    <p class="panel-kicker"><?= e($i18n->t('label.issuer')) ?></p>
    <p class="issuer-name"><?= e($record['issuer_name']) ?></p>
    <p class="issuer-cnpj">CNPJ <?= e($record['issuer_cnpj']) ?></p>
    <p class="issuer-level level-<?= (int) $record['issuer_level'] ?>">
      <span class="level-dot" aria-hidden="true"></span>
      <span><strong><?= e($i18n->t('label.level')) ?>:</strong> <?= e($i18n->t('level.' . (int) $record['issuer_level'])) ?></span>
    </p>
  </section>

  <section class="wrap narrow panel share">
    <p class="panel-kicker"><?= e($i18n->t('share.title')) ?></p>
    <div class="share-row">
      <div class="share-qr" data-qr="<?= e($shareUrl) ?>" aria-hidden="true"></div>
      <div>
        <p class="share-hint"><?= e($i18n->t('share.qr')) ?></p>
        <button class="button ghost" type="button" data-copy="<?= e($shareUrl) ?>" data-copied="<?= e($i18n->t('share.copied')) ?>">
          <?= e($i18n->t('share.copy')) ?>
        </button>
      </div>
    </div>
  </section>

  <section class="wrap narrow">
    <details class="proof">
      <summary><?= e($i18n->t('proof.title')) ?></summary>
      <dl class="facts">
        <div class="wide"><dt><?= e($i18n->t('proof.hash')) ?></dt><dd><code class="hash"><?= e($record['hash']) ?></code></dd></div>
        <div><dt><?= e($i18n->t('proof.anchored')) ?></dt><dd><?= e($i18n->date($record['anchored_on'])) ?></dd></div>
      </dl>
      <p class="muted"><?= e($i18n->t('proof.note')) ?></p>
    </details>

    <div class="notproven">
      <h2><?= e($i18n->t('notproven.title')) ?></h2>
      <ul>
        <li><?= e($i18n->t('notproven.1')) ?></li>
        <li><?= e($i18n->t('notproven.2')) ?></li>
        <li><?= e($i18n->t('notproven.3')) ?></li>
      </ul>
    </div>
  </section>

<?php endif; ?>

<section class="wrap narrow again">
  <form method="post" action="<?= e($i18n->url()) ?>" class="verifier compact">
    <label class="verifier-label" for="codigo2"><?= e($i18n->t('verify.another')) ?></label>
    <div class="verifier-row">
      <input id="codigo2" name="codigo" type="text" autocomplete="off" autocapitalize="characters"
             spellcheck="false" maxlength="20" placeholder="<?= e($i18n->t('hero.placeholder')) ?>" data-code-input>
      <button type="submit"><?= e($i18n->t('hero.button')) ?></button>
    </div>
  </form>
  <p><a class="quiet-link" href="<?= e($i18n->url()) ?>"><?= e($i18n->t('verify.back')) ?></a></p>
</section>
