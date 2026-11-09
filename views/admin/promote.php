<?php /** @var ?string $erro */ $pageTitle = 'Primeiro administrador'; ?>
<div class="adm-wrap adm-narrow">
  <h1 class="adm-h1">Primeiro administrador</h1>
  <p class="adm-lead">
    O segredo é o <code>setup_token</code> do <code>lib/config.local.php</code>,
    que só existe no servidor. É o mesmo portão da ativação original — e é o
    único jeito de nascer um administrador quando ainda não há nenhum.
  </p>
  <?php if ($erro !== null): ?><p class="flash flash-bad" role="alert"><?= e($erro) ?></p><?php endif; ?>
  <form method="post" action="/admin/promover" class="form card">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
    <label for="token">Segredo de instalação</label>
    <input id="token" name="token" type="password" autocomplete="off" required>
    <button type="submit">Promover esta conta</button>
  </form>
</div>
