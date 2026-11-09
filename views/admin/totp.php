<?php
/**
 * Matrícula no segundo fator.
 *
 * @var string $segredo
 * @var string $uri
 * @var ?string $erro
 */
$pageTitle = 'Segundo fator';
?>
<div class="adm-wrap adm-narrow">
  <h1 class="adm-h1">Ligue o segundo fator</h1>
  <p class="adm-lead">
    Quem entra aqui vê a conta de todo mundo. Senha sozinha não basta para
    esse papel — e esta é a única parte do produto em que exigimos isso, porque
    exigir de todo cliente afastaria cliente.
  </p>

  <?php if ($erro !== null): ?><p class="flash flash-bad" role="alert"><?= e($erro) ?></p><?php endif; ?>

  <div class="card adm-totp">
    <div class="adm-qr" data-qr="<?= e($uri) ?>" aria-hidden="true"></div>
    <div>
      <p>Leia o QR Code no seu aplicativo autenticador. Se preferir digitar:</p>
      <pre class="snippet"><code><?= e(trim(chunk_split($segredo, 4, ' '))) ?></code></pre>
      <p class="field-hint">
        Guarde este segredo em lugar seguro. Perdê-lo com o celular junto
        significa voltar aqui pelo segredo de instalação.
      </p>
    </div>
  </div>

  <form method="post" action="/admin/fator" class="form card">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
    <label for="codigo">Código de seis dígitos</label>
    <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code"
           maxlength="7" required spellcheck="false">
    <button type="submit">Confirmar</button>
  </form>
</div>
