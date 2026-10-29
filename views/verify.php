<?php
/** @var I18n $i18n */
/** @var ?array $record */
/** @var string $state */
/** @var string $typed */
$pageNoindex = true;
$pageTitle = $i18n->t('meta.verify.title') . ' · ' . APP_NAME;
$pageDesc = $i18n->t('meta.home.desc');

$glyphs = [
    'verified'    => 'check',
    'expiring'    => 'clock',
    'expired'     => 'clock',
    'revoked'     => 'cross',
    'notfound'    => 'question',
    'throttled'   => 'question',
    'unavailable' => 'question',
];

$verdict = $i18n->t('status.' . (in_array($state, ['throttled', 'unavailable'], true) ? 'notfound' : $state));
$shareUrl = base_url() . $i18n->url('/v/' . $typed);
?>

<section class="verdict verdict-<?= e($state) ?>">
  <div class="wrap narrow">
    <p class="verdict-code"><?= e($i18n->t('label.code')) ?> <code><?= e($typed) ?></code></p>

    <h1 class="verdict-headline">
      <?php $sealClass = 'verdict-seal'; $sealGlyph = $glyphs[$state] ?? 'question'; require __DIR__ . '/partials/seal.php'; ?>
      <span><?= e($verdict) ?></span>
    </h1>

    <?php if ($state === 'throttled'): ?>
      <p class="verdict-explain"><?= e($i18n->t('error.throttled')) ?></p>
    <?php elseif ($state === 'unavailable'): ?>
      <p class="verdict-explain"><?= e($i18n->t('error.unavailable')) ?></p>
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

  <?php if (Store::isDemo()): ?>
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
