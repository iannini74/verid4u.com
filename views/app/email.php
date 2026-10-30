<?php
/** @var I18n $i18n */
/** @var array $stats */
/** @var array $recent */
$pageTitle = $i18n->a('mail.title');
$ativo = $driver !== 'log' && $driver !== 'off';
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('mail.title')) ?></h1>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <section class="card" style="margin-bottom:var(--space-5)">
    <h2 class="app-h2"><?= e($i18n->a('mail.status')) ?></h2>
    <dl class="facts">
      <div>
        <dt><?= e($i18n->a('mail.driver')) ?></dt>
        <dd>
          <span class="pill pill-<?= $ativo ? 'verified' : 'expired' ?>"><?= e($driver) ?></span>
          <?php if (!$ativo): ?><span class="chip"><?= e($i18n->a('mail.driver_off')) ?></span><?php endif; ?>
        </dd>
      </div>
      <div><dt><?= e($i18n->a('mail.from')) ?></dt><dd><?= e($fromEmail !== '' ? $fromEmail : '—') ?></dd></div>
      <div><dt><?= e($i18n->a('mail.host')) ?></dt><dd><?= e($smtpHost !== '' ? $smtpHost : '—') ?></dd></div>
      <div>
        <dt><?= e($i18n->a('mail.cron')) ?></dt>
        <dd><span class="pill pill-<?= $cronSet ? 'verified' : 'revoked' ?>"><?= e($i18n->a($cronSet ? 'mail.cron_yes' : 'mail.cron_no')) ?></span></dd>
      </div>
    </dl>
    <?php $forasDoLugar = Config::misplacedMailKeys(); ?>
    <?php if ($forasDoLugar !== []): ?>
      <p class="flash flash-bad" style="margin:var(--space-4) 0 0" role="alert">
        <?= e($i18n->a('mail.misplaced', ['chaves' => implode(', ', $forasDoLugar)])) ?>
      </p>
    <?php elseif (!$ativo): ?>
      <p class="field-hint" style="margin-top:var(--space-4)"><?= e($i18n->a('mail.how_to_enable')) ?></p>
    <?php endif; ?>
  </section>

  <ul class="tiles">
    <li><span class="tile-n"><?= (int) $stats['queued'] ?></span><span class="tile-l"><?= e($i18n->a('mail.queued')) ?></span></li>
    <li class="tile-ok"><span class="tile-n"><?= (int) $stats['sent'] ?></span><span class="tile-l"><?= e($i18n->a('mail.sent')) ?></span></li>
    <li class="tile-warn"><span class="tile-n"><?= (int) $stats['failed'] ?></span><span class="tile-l"><?= e($i18n->a('mail.failed')) ?></span></li>
    <li><span class="tile-n"><?= (int) $stats['suppression_list'] ?></span><span class="tile-l"><?= e($i18n->a('mail.suppressed')) ?></span></li>
  </ul>

  <div class="two-col">
    <section class="card">
      <h2 class="app-h2"><?= e($i18n->a('mail.test')) ?></h2>
      <p class="field-hint" style="margin:0 0 var(--space-2)"><?= e($i18n->a('mail.test_hint')) ?></p>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="acao" value="teste">
        <label for="destino"><?= e($i18n->a('mail.test_to')) ?></label>
        <input id="destino" name="destino" type="email" required autocomplete="email">
        <button type="submit"><?= e($i18n->a('mail.test_send')) ?></button>
      </form>

      <h2 class="app-h2" style="margin-top:var(--space-6)"><?= e($i18n->a('mail.release')) ?></h2>
      <p class="field-hint" style="margin:0 0 var(--space-2)"><?= e($i18n->a('mail.release_hint')) ?></p>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="acao" value="liberar">
        <label for="email"><?= e($i18n->a('mail.release_to')) ?></label>
        <input id="email" name="email" type="email" required>
        <button type="submit"><?= e($i18n->a('mail.release_do')) ?></button>
      </form>
    </section>

    <section class="card">
      <h2 class="app-h2"><?= e($i18n->a('mail.recent')) ?></h2>
      <?php if ($recent === []): ?>
        <p class="muted"><?= e($i18n->a('mail.recent_empty')) ?></p>
      <?php else: ?>
        <table class="grid">
          <thead><tr>
            <th><?= e($i18n->a('mail.to')) ?></th>
            <th><?= e($i18n->a('mail.template')) ?></th>
            <th><?= e($i18n->a('list.state')) ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($recent as $m):
              $cor = ['sent' => 'verified', 'queued' => 'expiring', 'failed' => 'revoked', 'suppressed' => 'expired'][$m['status']] ?? 'expired';
          ?>
            <tr>
              <td><?= e($m['to_email']) ?></td>
              <td><code class="small"><?= e($m['template']) ?></code></td>
              <td>
                <span class="pill pill-<?= e($cor) ?>"><?= e($m['status']) ?></span>
                <?php if (!empty($m['last_error'])): ?>
                  <div class="field-hint" style="margin-top:4px"><?= e(mb_substr((string) $m['last_error'], 0, 90)) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>
</div>
