<?php
/**
 * @var array $nums
 * @var list<array> $fila
 * @var array $mail
 */
$pageTitle = 'Visão';
?>
<div class="adm-wrap">
  <h1 class="adm-h1">Visão da plataforma</h1>
  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>

  <ul class="adm-tiles">
    <li><span class="adm-n"><?= (int) $nums['tenants'] ?></span><span class="adm-l">Contas</span></li>
    <li><span class="adm-n"><?= (int) $nums['ativos'] ?></span><span class="adm-l">Emitiram este mês</span></li>
    <li><span class="adm-n"><?= number_format((int) $nums['issuances'], 0, ',', '.') ?></span><span class="adm-l">Certificados no registro</span></li>
    <li><span class="adm-n"><?= number_format((int) $nums['mes'], 0, ',', '.') ?></span><span class="adm-l">Emissões no mês</span></li>
    <li><span class="adm-n"><?= number_format((int) $nums['verif30'], 0, ',', '.') ?></span><span class="adm-l">Verificações em 30 dias</span></li>
    <li<?= (int) $nums['fila'] > 0 ? ' class="adm-warn"' : '' ?>><span class="adm-n"><?= (int) $nums['fila'] ?></span><span class="adm-l">Na fila de verificação</span></li>
    <li><span class="adm-n"><?= (int) $nums['pendentes'] ?></span><span class="adm-l">E-mails por confirmar</span></li>
    <li><span class="adm-n"><?= (int) $nums['chaves'] ?></span><span class="adm-l">Chaves de API ativas</span></li>
  </ul>

  <section class="card adm-card">
    <h2 class="adm-h2">Fila de verificação</h2>
    <?php if ($fila === []): ?>
      <p class="muted">Ninguém esperando decisão.</p>
    <?php else: ?>
      <table class="grid">
        <thead><tr><th>Emissor</th><th>Conta</th><th>Emissões</th><th>Pedido em</th></tr></thead>
        <tbody>
        <?php foreach ($fila as $f): ?>
          <tr>
            <td><a href="/admin/fila"><?= e((string) $f['name']) ?></a></td>
            <td><a href="/admin/cliente/<?= (int) $f['tenant_id'] ?>"><?= e((string) $f['tenant']) ?></a></td>
            <td><?= (int) $f['emissoes'] ?></td>
            <td><?= e($i18n->datetime((string) $f['review_requested_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card adm-card">
    <h2 class="adm-h2">Fila de e-mail</h2>
    <?php
    $parado = (int) ($mail['queued'] ?? 0) > 0 && (int) ($mail['sent'] ?? 0) === 0;
    ?>
    <?php if ($parado): ?>
      <p class="flash flash-bad">
        Há <?= (int) $mail['queued'] ?> mensagem(ns) na fila e nenhuma enviada.
        É o cron de e-mail que não está configurado no hPanel — sem ele nenhuma
        conta nova confirma o endereço, e sem confirmar ninguém emite.
      </p>
    <?php endif; ?>
    <ul class="adm-tiles adm-tiles-sm">
      <li><span class="adm-n"><?= (int) ($mail['queued'] ?? 0) ?></span><span class="adm-l">Na fila</span></li>
      <li><span class="adm-n"><?= (int) ($mail['sent'] ?? 0) ?></span><span class="adm-l">Enviadas</span></li>
      <li><span class="adm-n"><?= (int) ($mail['failed'] ?? 0) ?></span><span class="adm-l">Falharam</span></li>
      <li><span class="adm-n"><?= (int) ($mail['suppression_list'] ?? 0) ?></span><span class="adm-l">Suprimidas</span></li>
    </ul>
  </section>
</div>
