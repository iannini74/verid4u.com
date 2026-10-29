<?php
/** @var I18n $i18n */
$pageTitle = $i18n->a('reg.title');
$old = $old ?? [];
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('reg.title')) ?></h1>
  <p class="app-lead" style="max-width: 70ch;"><?= e($i18n->a('reg.intro')) ?></p>

  <?php if (!empty($registered)): ?>
    <div class="flash flash-ok issued">
      <p><strong><?= e($i18n->a('reg.done')) ?></strong></p>
      <p class="issued-code"><code><?= e(Code::format($registered['code'])) ?></code></p>
      <p>
        <a class="btn btn-ghost btn-sm" href="<?= e($i18n->url('/v/' . Code::format($registered['code']))) ?>"><?= e($i18n->a('list.view')) ?></a>
      </p>
    </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <section class="card app-narrow-inner">
    <form method="post" class="form" data-hash-form>
      <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
      <input type="hidden" name="source_sha256" value="" data-hash-value>
      <input type="hidden" name="source_filename" value="" data-hash-name>

      <p class="panel-kicker" style="margin-top:0"><?= e($i18n->a('reg.group.doc')) ?></p>

      <label for="file"><?= e($i18n->a('reg.file')) ?></label>
      <input id="file" type="file" accept=".pdf,.png,.jpg,.jpeg" data-hash-input>
      <p class="field-hint"><?= e($i18n->a('reg.file_hint')) ?></p>
      <p class="hash-state" data-hash-state hidden></p>

      <label for="issuer_name"><?= e($i18n->a('reg.issuer')) ?></label>
      <input id="issuer_name" name="issuer_name" type="text" required maxlength="190" value="<?= e($old['issuer_name'] ?? '') ?>">
      <p class="field-hint"><?= e($i18n->a('reg.issuer_hint')) ?></p>

      <p class="panel-kicker"><?= e($i18n->a('reg.group.course')) ?></p>

      <label for="program_title"><?= e($i18n->a('prog.name')) ?></label>
      <input id="program_title" name="program_title" type="text" required maxlength="190" value="<?= e($old['program_title'] ?? '') ?>">

      <label for="program_norm"><?= e($i18n->a('prog.norm')) ?></label>
      <input id="program_norm" name="program_norm" type="text" maxlength="40" placeholder="NR-35" value="<?= e($old['program_norm'] ?? '') ?>">

      <label for="hours"><?= e($i18n->a('prog.hours')) ?></label>
      <input id="hours" name="hours" type="number" min="0" max="9999" value="<?= e($old['hours'] ?? '8') ?>">

      <label for="modality"><?= e($i18n->a('prog.modality')) ?></label>
      <select id="modality" name="modality">
        <?php foreach (['presencial', 'ead', 'hibrido'] as $m): ?>
          <option value="<?= e($m) ?>"<?= ($old['modality'] ?? '') === $m ? ' selected' : '' ?>><?= e($i18n->t('modality.' . $m)) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="check"><input type="checkbox" name="assessed" value="1"<?= !empty($old['assessed']) ? ' checked' : '' ?>> <?= e($i18n->a('prog.assessed')) ?></label>

      <p class="panel-kicker"><?= e($i18n->a('reg.group.holder')) ?></p>

      <label for="holder_name"><?= e($i18n->a('issue.holder')) ?></label>
      <input id="holder_name" name="holder_name" type="text" required maxlength="190" value="<?= e($old['holder_name'] ?? '') ?>">

      <label for="holder_email"><?= e($i18n->a('issue.email')) ?></label>
      <input id="holder_email" name="holder_email" type="email" maxlength="190" value="<?= e($old['holder_email'] ?? '') ?>">

      <label for="holder_display"><?= e($i18n->a('issue.display')) ?></label>
      <select id="holder_display" name="holder_display">
        <?php foreach (['partial', 'full', 'hidden'] as $mode): ?>
          <option value="<?= e($mode) ?>"<?= ($old['holder_display'] ?? 'partial') === $mode ? ' selected' : '' ?>><?= e($i18n->a('issue.display.' . $mode)) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="issued_on"><?= e($i18n->a('issue.date')) ?></label>
      <input id="issued_on" name="issued_on" type="date" required value="<?= e($old['issued_on'] ?? date('Y-m-d')) ?>">

      <label for="valid_until"><?= e($i18n->a('reg.valid_until')) ?></label>
      <input id="valid_until" name="valid_until" type="date" value="<?= e($old['valid_until'] ?? '') ?>">
      <p class="field-hint"><?= e($i18n->a('reg.valid_until_hint')) ?></p>

      <button type="submit"><?= e($i18n->a('reg.submit')) ?></button>
    </form>
  </section>
</div>
