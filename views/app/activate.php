<?php
/** @var I18n $i18n */
$pageTitle = $i18n->a('activate.title');
$old = $old ?? [];
?>
<div class="app-narrow">
  <h1 class="app-h1"><?= e($i18n->a('activate.title')) ?></h1>
  <p class="app-lead"><?= e($i18n->a('activate.intro')) ?></p>

  <?php if (!empty($error)): ?>
    <p class="flash flash-bad" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" class="form card">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">

    <label for="token"><?= e($i18n->a('activate.token')) ?></label>
    <input id="token" name="token" type="text" required autocomplete="off" spellcheck="false" class="mono">

    <label for="org"><?= e($i18n->a('activate.org')) ?></label>
    <input id="org" name="org" type="text" required value="<?= e($old['org'] ?? '') ?>">

    <label for="legal"><?= e($i18n->a('activate.legal')) ?></label>
    <input id="legal" name="legal" type="text" value="<?= e($old['legal'] ?? '') ?>">

    <label for="name"><?= e($i18n->a('activate.name')) ?></label>
    <input id="name" name="name" type="text" required value="<?= e($old['name'] ?? '') ?>">

    <label for="email"><?= e($i18n->a('activate.email')) ?></label>
    <input id="email" name="email" type="email" required autocomplete="username" value="<?= e($old['email'] ?? '') ?>">

    <label for="password"><?= e($i18n->a('activate.password')) ?></label>
    <input id="password" name="password" type="password" required autocomplete="new-password" minlength="12">

    <label for="password2"><?= e($i18n->a('activate.password2')) ?></label>
    <input id="password2" name="password2" type="password" required autocomplete="new-password" minlength="12">

    <button type="submit"><?= e($i18n->a('activate.submit')) ?></button>
  </form>
</div>
