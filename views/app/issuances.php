<?php
/** @var I18n $i18n */
/** @var array $issuances */
$pageTitle = $i18n->a('list.title');
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('list.title')) ?></h1>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <section class="card">
    <?php if ($issuances === []): ?>
      <p class="muted"><?= e($i18n->a('list.empty')) ?></p>
    <?php else: ?>
      <table class="grid">
        <thead><tr>
          <th><?= e($i18n->a('list.code')) ?></th>
          <th><?= e($i18n->a('list.holder')) ?></th>
          <th><?= e($i18n->a('list.program')) ?></th>
          <th><?= e($i18n->a('list.state')) ?></th>
          <th><?= e($i18n->a('list.actions')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($issuances as $row):
            $state = Store::state($row);
            $code = Code::format(Code::full((string) $row['code_base']));
        ?>
          <tr>
            <td><code class="small"><?= e($code) ?></code><?= $row['is_demo'] ? ' <span class="chip chip-demo">demo</span>' : '' ?></td>
            <td><?= e(Store::displayName($row)) ?></td>
            <td><?= e($row['program_title']) ?></td>
            <td><span class="pill pill-<?= e($state) ?>"><?= e($i18n->t('status.' . $state)) ?></span></td>
            <td class="row-actions">
              <a href="<?= e($i18n->url('/v/' . $code)) ?>"><?= e($i18n->a('list.view')) ?></a>
              <a href="<?= e($i18n->url('/app/certificado/' . $code)) ?>"><?= e($i18n->a('list.print')) ?></a>
              <?php if (empty($row['revoked_on'])): ?>
                <details class="revoke">
                  <summary><?= e($i18n->a('list.revoke')) ?></summary>
                  <form method="post" action="<?= e($i18n->url('/app/revogar')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <label for="r<?= (int) $row['id'] ?>"><?= e($i18n->a('list.revoke_reason')) ?></label>
                    <input id="r<?= (int) $row['id'] ?>" name="reason" type="text" required maxlength="255">
                    <button class="danger" type="submit"><?= e($i18n->a('list.revoke_confirm')) ?></button>
                  </form>
                </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
