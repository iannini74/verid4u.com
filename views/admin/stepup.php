<?php /** @var ?string $erro */ $pageTitle = 'Confirmação'; ?>
<div class="adm-wrap adm-narrow">
  <h1 class="adm-h1">Confirme que é você</h1>
  <p class="adm-lead">
    O código vale por <?= (int) Admin::STEP_UP_HOURS ?> horas nesta sessão.
    Depois disso pedimos de novo — sessão longa aberta numa máquina esquecida
    é o jeito mais comum de essa porta ser atravessada.
  </p>
  <?php if ($erro !== null): ?><p class="flash flash-bad" role="alert"><?= e($erro) ?></p><?php endif; ?>
  <form method="post" action="/admin" class="form card">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
    <label for="codigo">Código do autenticador</label>
    <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code"
           maxlength="7" required spellcheck="false" autofocus>
    <button type="submit">Entrar</button>
  </form>
</div>
