<?php
/** @var I18n $i18n */
/** @var array $programs */
$pageTitle = $i18n->a('prog.title');
$old = $old ?? [];
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('prog.title')) ?></h1>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <div class="two-col">
    <section class="card">
      <h2 class="app-h2"><?= e($i18n->a('prog.new')) ?></h2>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">

        <label for="title"><?= e($i18n->a('prog.name')) ?></label>
        <input id="title" name="title" type="text" required maxlength="190" value="<?= e($old['title'] ?? '') ?>">

        <label for="norm"><?= e($i18n->a('prog.norm')) ?></label>
        <input id="norm" name="norm" type="text" maxlength="40" placeholder="NR-35" value="<?= e($old['norm'] ?? '') ?>">

        <label for="hours"><?= e($i18n->a('prog.hours')) ?></label>
        <input id="hours" name="hours" type="number" min="0" max="9999" value="<?= e($old['hours'] ?? '8') ?>">

        <label for="modality"><?= e($i18n->a('prog.modality')) ?></label>
        <select id="modality" name="modality">
          <?php foreach (['presencial', 'ead', 'hibrido'] as $m): ?>
            <option value="<?= e($m) ?>"<?= ($old['modality'] ?? '') === $m ? ' selected' : '' ?>><?= e($i18n->t('modality.' . $m)) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="check"><input type="checkbox" name="assessed" value="1"<?= !empty($old['assessed']) ? ' checked' : '' ?>> <?= e($i18n->a('prog.assessed')) ?></label>

        <label for="validity"><?= e($i18n->a('prog.validity')) ?></label>
        <input id="validity" name="validity_months" type="number" min="0" max="600" value="<?= e($old['validity_months'] ?? '24') ?>">
        <p class="field-hint"><?= e($i18n->a('prog.validity_hint')) ?></p>

        <button type="submit"><?= e($i18n->a('prog.submit')) ?></button>
      </form>
    </section>

    <section class="card">
      <h2 class="app-h2"><?= e($i18n->a('prog.title')) ?></h2>
      <?php if ($programs === []): ?>
        <p class="muted"><?= e($i18n->a('prog.empty')) ?></p>
      <?php else: ?>
        <table class="grid">
          <thead><tr>
            <th><?= e($i18n->a('prog.name')) ?></th>
            <th><?= e($i18n->t('label.hours')) ?></th>
            <th><?= e($i18n->t('label.modality')) ?></th>
            <th><?= e($i18n->a('prog.validity')) ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($programs as $p): ?>
            <tr>
              <td><?= e($p['title']) ?><?= $p['norm'] ? ' <span class="chip">' . e($p['norm']) . '</span>' : '' ?></td>
              <td><?= (int) $p['hours'] ?></td>
              <td><?= e($i18n->t('modality.' . $p['modality'])) ?></td>
              <td><?= $p['validity_months'] ? (int) $p['validity_months'] . ' m' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>
</div>
