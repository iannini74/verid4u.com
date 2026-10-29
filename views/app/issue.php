<?php
/** @var I18n $i18n */
/** @var array $programs */
$pageTitle = $i18n->a('issue.title');
$old = $old ?? [];
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('issue.title')) ?></h1>

  <?php if (!empty($issued)): ?>
    <div class="flash flash-ok issued">
      <p><strong><?= e($i18n->a('issue.done')) ?></strong></p>
      <p class="issued-code"><code><?= e(Code::format($issued['code'])) ?></code></p>
      <p>
        <a class="button ghost" href="<?= e($i18n->url('/v/' . Code::format($issued['code']))) ?>"><?= e($i18n->a('list.view')) ?></a>
        <a class="button ghost" href="<?= e($i18n->url('/app/certificado/' . Code::format($issued['code']))) ?>"><?= e($i18n->a('list.print')) ?></a>
      </p>
    </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php if ($programs === []): ?>
    <p class="muted"><?= e($i18n->a('issue.no_programs')) ?> <a href="<?= e($i18n->url('/app/cursos')) ?>"><?= e($i18n->a('prog.new')) ?></a></p>
  <?php else: ?>
    <section class="card app-narrow-inner">
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">

        <label for="program_id"><?= e($i18n->a('issue.program')) ?></label>
        <select id="program_id" name="program_id" required>
          <?php foreach ($programs as $p): ?>
            <option value="<?= (int) $p['id'] ?>"<?= (string) ($old['program_id'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>>
              <?= e($p['title']) ?> — <?= (int) $p['hours'] ?> h
            </option>
          <?php endforeach; ?>
        </select>

        <label for="holder_name"><?= e($i18n->a('issue.holder')) ?></label>
        <input id="holder_name" name="holder_name" type="text" required maxlength="190" value="<?= e($old['holder_name'] ?? '') ?>">

        <label for="holder_email"><?= e($i18n->a('issue.email')) ?></label>
        <input id="holder_email" name="holder_email" type="email" maxlength="190" value="<?= e($old['holder_email'] ?? '') ?>">
        <p class="field-hint"><?= e($i18n->a('issue.email_hint')) ?></p>

        <label for="holder_display"><?= e($i18n->a('issue.display')) ?></label>
        <select id="holder_display" name="holder_display">
          <?php foreach (['partial', 'full', 'hidden'] as $mode): ?>
            <option value="<?= e($mode) ?>"<?= ($old['holder_display'] ?? 'partial') === $mode ? ' selected' : '' ?>><?= e($i18n->a('issue.display.' . $mode)) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="issued_on"><?= e($i18n->a('issue.date')) ?></label>
        <input id="issued_on" name="issued_on" type="date" required value="<?= e($old['issued_on'] ?? date('Y-m-d')) ?>">

        <button type="submit"><?= e($i18n->a('issue.submit')) ?></button>
      </form>
    </section>
  <?php endif; ?>
</div>
