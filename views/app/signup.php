<?php
/**
 * Criar conta.
 *
 * Três campos obrigatórios e nenhum cartão. Cada campo a mais nesta tela é
 * uma fração dos visitantes que não entra — e tudo o que falta saber sobre o
 * cliente dá para perguntar depois, com ele já dentro e com um certificado
 * emitido.
 *
 * @var I18n $i18n
 * @var ?string $error
 * @var array $old
 */
$pageTitle = $i18n->a('signup.title');
?>
<div class="app-wrap signup-grid">

  <div>
    <h1 class="app-h1"><?= e($i18n->a('signup.title')) ?></h1>
    <p class="app-lead"><?= e($i18n->a('signup.lead', ['n' => Plan::FREE_MONTHLY])) ?></p>

    <?php if ($error !== null): ?>
      <p class="flash flash-bad" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e($i18n->url('/app/criar-conta')) ?>" class="form card" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">

      <label for="org"><?= e($i18n->a('signup.org')) ?></label>
      <input id="org" name="org" type="text" required maxlength="160" autocomplete="organization"
             value="<?= e($old['org'] ?? '') ?>">
      <p class="field-hint"><?= e($i18n->a('signup.org.hint')) ?></p>

      <label for="nome"><?= e($i18n->a('signup.name')) ?></label>
      <input id="nome" name="name" type="text" maxlength="160" autocomplete="name"
             value="<?= e($old['name'] ?? '') ?>">

      <label for="email"><?= e($i18n->a('signup.email')) ?></label>
      <input id="email" name="email" type="email" required maxlength="190" autocomplete="email"
             value="<?= e($old['email'] ?? '') ?>">
      <p class="field-hint"><?= e($i18n->a('signup.email.hint')) ?></p>

      <label for="senha"><?= e($i18n->a('signup.password')) ?></label>
      <input id="senha" name="password" type="password" required minlength="12" autocomplete="new-password">
      <p class="field-hint"><?= e($i18n->a('signup.password.hint')) ?></p>

      <label for="senha2"><?= e($i18n->a('signup.password2')) ?></label>
      <input id="senha2" name="password2" type="password" required minlength="12" autocomplete="new-password">

      <label class="check" for="aceite">
        <input id="aceite" type="checkbox" name="aceite" value="1" required>
        <span>
          <?= e($i18n->a('signup.terms.before')) ?>
          <a href="<?= e($i18n->url('/termos')) ?>" target="_blank" rel="noopener"><?= e($i18n->t('legal.termos')) ?></a>
          <?= e($i18n->a('signup.terms.and')) ?>
          <a href="<?= e($i18n->url('/privacidade')) ?>" target="_blank" rel="noopener"><?= e($i18n->t('legal.privacidade')) ?></a>.
        </span>
      </label>

      <button type="submit"><?= e($i18n->a('signup.submit')) ?></button>
    </form>

    <p class="auth-alt">
      <?= e($i18n->a('signup.has_account')) ?>
      <a class="quiet-link" href="<?= e($i18n->url('/app/entrar')) ?>"><?= e($i18n->t('nav.signin')) ?></a>
    </p>
  </div>

  <aside class="card signup-side">
    <p class="panel-kicker"><?= e($i18n->a('signup.side.title')) ?></p>
    <ul class="signup-points">
      <li><?= e($i18n->a('signup.side.1', ['n' => Plan::FREE_MONTHLY])) ?></li>
      <li><?= e($i18n->a('signup.side.2')) ?></li>
      <li><?= e($i18n->a('signup.side.3')) ?></li>
      <li><?= e($i18n->a('signup.side.4')) ?></li>
    </ul>
  </aside>

</div>
