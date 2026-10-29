<?php
/** @var I18n $i18n */
/** @var array $stats */
/** @var array $expiring */
$pageTitle = $i18n->a('dash.title');
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('dash.title')) ?></h1>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>

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
