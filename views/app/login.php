<?php
/** @var I18n $i18n */
$pageTitle = $i18n->a('login.title');
?>
<div class="app-narrow">
  <h1 class="app-h1"><?= e($i18n->a('login.title')) ?></h1>

  <?php if (!empty($error)): ?>
    <p class="flash flash-bad" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" class="form card">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
    <label for="email"><?= e($i18n->a('login.email')) ?></label>
    <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email ?? '') ?>">

    <label for="password"><?= e($i18n->a('login.password')) ?></label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>

    <button type="submit"><?= e($i18n->a('login.submit')) ?></button>
  </form>

  <p class="auth-alt">
    <?= e($i18n->a('login.no_account')) ?>
    <a class="quiet-link" href="<?= e($i18n->url('/app/criar-conta')) ?>"><?= e($i18n->a('signup.title')) ?></a>
  </p>
</div>
