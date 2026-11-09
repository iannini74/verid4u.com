<?php
/**
 * @var list<array> $linhas
 * @var string $comp
 */
$pageTitle = 'Clientes';
?>
<div class="adm-wrap">
  <h1 class="adm-h1">Clientes</h1>
  <p class="adm-lead">Competência <?= e($comp) ?>. As 200 contas mais recentes.</p>
  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>

  <table class="grid adm-grid">
    <thead><tr>
      <th>Conta</th><th>Plano</th><th>Nível</th><th>Mês</th><th>Total</th>
      <th>E-mail</th><th>Marca</th><th>Criada</th>
    </tr></thead>
    <tbody>
    <?php foreach ($linhas as $l): ?>
      <tr<?= (string) $l['status'] !== 'active' ? ' class="row-off"' : '' ?>>
        <td>
          <a href="/admin/cliente/<?= (int) $l['id'] ?>"><?= e((string) $l['name']) ?></a>
          <?php if ((string) $l['created_via'] === 'setup'): ?><span class="chip">dono</span><?php endif; ?>
        </td>
        <td><code><?= e((string) $l['plan']) ?></code></td>
        <td><?= $l['nivel'] === null ? '—' : (int) $l['nivel'] ?></td>
        <td class="num"><?= (int) $l['mes'] ?></td>
        <td class="num"><?= (int) $l['total'] ?></td>
        <td><?= (string) $l['dono_status'] === 'active'
              ? '<span class="pill pill-verified">ok</span>'
              : '<span class="pill pill-expired">pendente</span>' ?></td>
        <td><?= $l['brand_enabled_at'] === null ? '—' : 'sim' ?></td>
        <td><?= e(substr((string) $l['created_at'], 0, 10)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
