<?php
/**
 * Uma conta, por inteiro.
 *
 * @var array $t
 * @var list<array> $emissores
 * @var list<array> $usuarios
 * @var list<array> $uso
 * @var array $stats
 * @var list<array> $planos
 * @var ?string $erro
 */
$pageTitle = (string) $t['name'];
$temRegistro = (int) ($stats['total'] ?? 0) > 0;
?>
<div class="adm-wrap">
  <p class="adm-back"><a href="/admin/clientes">← Clientes</a></p>
  <h1 class="adm-h1"><?= e((string) $t['name']) ?></h1>
  <p class="adm-lead">
    <code><?= e((string) $t['slug']) ?></code> ·
    criada em <?= e(substr((string) $t['created_at'], 0, 10)) ?> ·
    <?= (string) $t['created_via'] === 'setup' ? 'conta do dono da plataforma' : 'cadastro próprio' ?>
  </p>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if ($erro !== null): ?><p class="flash flash-bad" role="alert"><?= e($erro) ?></p><?php endif; ?>

  <ul class="adm-tiles adm-tiles-sm">
    <li><span class="adm-n"><?= (int) ($stats['total'] ?? 0) ?></span><span class="adm-l">No registro</span></li>
    <li><span class="adm-n"><?= (int) ($stats['expiring'] ?? 0) ?></span><span class="adm-l">A vencer</span></li>
    <li><span class="adm-n"><?= (int) ($stats['revoked'] ?? 0) ?></span><span class="adm-l">Revogados</span></li>
    <li><span class="adm-n"><?= (int) ($stats['verifications'] ?? 0) ?></span><span class="adm-l">Verificações</span></li>
  </ul>

  <section class="card adm-card">
    <h2 class="adm-h2">Emissores</h2>
    <table class="grid">
      <thead><tr><th>Nome</th><th>Endereço</th><th>Ramo</th><th>Nível</th><th>Com base em</th></tr></thead>
      <tbody>
      <?php foreach ($emissores as $e): ?>
        <tr>
          <td><?= e((string) $e['name']) ?></td>
          <td><?php if ($e['slug'] !== null): ?>
                <a href="/pt-br/e/<?= e((string) $e['slug']) ?>" target="_blank" rel="noopener"><?= e((string) $e['slug']) ?></a>
              <?php else: ?>—<?php endif; ?></td>
          <td><?= e((string) ($e['category'] ?? '')) ?></td>
          <td><strong><?= (int) $e['level'] ?></strong>
              <?php if ((string) $e['review_status'] === 'pending'): ?>
                <span class="chip">na fila</span>
              <?php endif; ?></td>
          <td class="adm-basis">
            <?= $e['level_basis'] === null
                  ? '<span class="muted">nunca decidido</span>'
                  : e((string) $e['level_basis']) . ' <span class="muted">(' . e(substr((string) $e['level_set_at'], 0, 10)) . ')</span>' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card adm-card">
    <h2 class="adm-h2">Uso por competência</h2>
    <?php if ($uso === []): ?>
      <p class="muted">Nenhuma emissão ainda.</p>
    <?php else: ?>
      <table class="grid">
        <thead><tr><th>Mês</th><th>Emitidos</th><th>Registrados</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($uso as $u): ?>
          <tr>
            <td><?= e((string) $u['competencia']) ?></td>
            <td class="num"><?= (int) $u['issuances'] ?></td>
            <td class="num"><?= (int) $u['registrations'] ?></td>
            <td class="num"><strong><?= (int) $u['issuances'] + (int) $u['registrations'] ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card adm-card">
    <h2 class="adm-h2">Plano</h2>
    <form method="post" class="form form-narrow">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
      <input type="hidden" name="acao" value="plano">
      <label for="plano">Plano desta conta</label>
      <select id="plano" name="plano">
        <?php foreach ($planos as $p): ?>
          <option value="<?= e((string) $p['code']) ?>" <?= (string) $t['plan'] === (string) $p['code'] ? 'selected' : '' ?>>
            <?= e((string) $p['name']) ?> — <?= (int) $p['monthly_quota'] ?>/mês
          </option>
        <?php endforeach; ?>
        <option value="unlimited" <?= (string) $t['plan'] === 'unlimited' ? 'selected' : '' ?>>
          Ilimitado (liberação manual)
        </option>
      </select>
      <p class="field-hint">
        «Ilimitado» também libera a marca do cliente na página de verificação,
        sem esperar o volume. É a conta da Inspire4U e as liberações a dedo.
      </p>
      <button type="submit">Salvar plano</button>
    </form>
  </section>

  <section class="card adm-card">
    <h2 class="adm-h2">Situação</h2>
    <p class="field-hint">
      Suspender bloqueia a escrita da conta, inclusive pela API. Não toca no
      que já está no registro: o que foi emitido continua conferível, de graça,
      para sempre.
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
      <input type="hidden" name="acao" value="<?= (string) $t['status'] === 'active' ? 'suspender' : 'reativar' ?>">
      <button class="button ghost" type="submit">
        <?= (string) $t['status'] === 'active' ? 'Suspender a conta' : 'Reativar a conta' ?>
      </button>
    </form>
  </section>

  <section class="card adm-card adm-danger">
    <h2 class="adm-h2">Apagar</h2>
    <?php if ($temRegistro): ?>
      <p class="field-hint">
        Esta conta tem <?= (int) $stats['total'] ?> certificado(s) no registro, e por
        isso não pode ser apagada daqui. Quem recebeu um certificado não tem nada
        a ver com a conta de quem emitiu — apagar o registro quebraria a promessa
        que está escrita na página de preços.
      </p>
    <?php else: ?>
      <p class="field-hint">
        Sem certificado nenhum no registro, dá para apagar. Serve para limpar
        conta de teste. Para confirmar, escreva o nome exato da conta.
      </p>
      <form method="post" class="form form-narrow">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="acao" value="apagar">
        <label for="confirma">Nome da conta</label>
        <input id="confirma" name="confirma" type="text" autocomplete="off" required
               placeholder="<?= e((string) $t['name']) ?>">
        <button class="button danger" type="submit">Apagar esta conta</button>
      </form>
    <?php endif; ?>
  </section>
</div>
