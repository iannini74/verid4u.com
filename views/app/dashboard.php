<?php
/** @var I18n $i18n */
/** @var array $stats */
/** @var array $expiring */
$pageTitle = $i18n->a('dash.title');
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('dash.title')) ?></h1>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>

  <?php if (!empty($pendente)): ?>
    <!-- Confirmar o e-mail é o que libera pôr registro no cadastro público.
         O aviso diz o que está travado e como destravar, na mesma frase. -->
    <div class="notice notice-warn">
      <div>
        <strong><?= e($i18n->a('signup.pending.title')) ?></strong>
        <p><?= e($i18n->a('signup.pending.body')) ?></p>
      </div>
      <form method="post" action="<?= e($i18n->url('/app/reenviar')) ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <button class="button ghost" type="submit"><?= e($i18n->a('signup.resend')) ?></button>
      </form>
    </div>
  <?php endif; ?>

  <?php if (!empty($plano) && empty($plano['unlimited'])): ?>
    <?php
    $usado = (int) $plano['used'];
    $limite = (int) $plano['limit'];
    $fracao = $limite > 0 ? min(1.0, $usado / $limite) : 0.0;
    $apertado = $fracao >= 0.8;
    ?>
    <section class="quota<?= $apertado ? ' quota-tight' : '' ?>">
      <div class="quota-head">
        <span class="quota-label"><?= e($i18n->a('plan.month')) ?></span>
        <span class="quota-count"><?= $usado ?> <span>/ <?= $limite ?></span></span>
      </div>
      <div class="quota-bar"><i style="width: <?= (int) round($fracao * 100) ?>%"></i></div>
      <p class="quota-note">
        <?= e($usado >= $limite
              ? $i18n->a('plan.exhausted', ['n' => $limite])
              : $i18n->a('plan.remaining', ['n' => $limite - $usado])) ?>
        <?= e($i18n->a('plan.verify_always_free')) ?>
      </p>
    </section>
  <?php endif; ?>

  <ul class="tiles">
    <li><span class="tile-n"><?= (int) $stats['total'] ?></span><span class="tile-l"><?= e($i18n->a('dash.total')) ?></span></li>
    <li class="tile-warn"><span class="tile-n"><?= (int) $stats['expiring'] ?></span><span class="tile-l"><?= e($i18n->a('dash.expiring')) ?></span></li>
    <li><span class="tile-n"><?= (int) $stats['expired'] ?></span><span class="tile-l"><?= e($i18n->a('dash.expired')) ?></span></li>
    <li><span class="tile-n"><?= (int) $stats['revoked'] ?></span><span class="tile-l"><?= e($i18n->a('dash.revoked')) ?></span></li>
    <li class="tile-ok"><span class="tile-n"><?= (int) $stats['verifications'] ?></span><span class="tile-l"><?= e($i18n->a('dash.verifications')) ?></span></li>
  </ul>

  <section class="card">
    <h2 class="app-h2"><?= e($i18n->a('dash.expiring_title')) ?></h2>
    <?php if ($expiring === []): ?>
      <p class="muted"><?= e($i18n->a($stats['total'] === 0 ? 'dash.first_steps' : 'dash.expiring_empty')) ?></p>
    <?php else: ?>
      <table class="grid">
        <thead><tr>
          <th><?= e($i18n->a('list.holder')) ?></th>
          <th><?= e($i18n->a('list.program')) ?></th>
          <th><?= e($i18n->t('label.validity')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($expiring as $row): ?>
          <tr>
            <td><?= e(Store::displayName($row)) ?></td>
            <td><?= e($row['program_title']) ?></td>
            <td><?= e($i18n->date($row['valid_until'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
