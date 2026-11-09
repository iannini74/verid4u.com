<?php
/**
 * Fila de verificação do emissor.
 *
 * O nível é o que separa «Registra no VerID4U» de «Emissor verificado» no
 * selo que vai no site do cliente. É a nossa palavra — por isso a base é
 * obrigatória, e fica guardada junto com quem decidiu.
 *
 * @var list<array> $fila
 * @var list<array> $recentes
 * @var ?string $erro
 */
$pageTitle = 'Fila';
$niveis = [1 => 'Nível 1 — e-mail e domínio conferidos', 2 => 'Nível 2 — pessoa jurídica verificada', 3 => 'Nível 3 — credenciamento conferido'];
?>
<div class="adm-wrap">
  <h1 class="adm-h1">Fila de verificação</h1>
  <p class="adm-lead">
    Nível não é campo que se edita: é decisão registrada, com quem decidiu e
    com base em quê. Sem base, vira opinião — e opinião não sustenta um selo.
  </p>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if ($erro !== null): ?><p class="flash flash-bad" role="alert"><?= e($erro) ?></p><?php endif; ?>

  <?php if ($fila === []): ?>
    <p class="muted">Ninguém esperando decisão.</p>
  <?php else: ?>
    <?php foreach ($fila as $f): ?>
      <section class="card adm-card">
        <h2 class="adm-h2"><?= e((string) $f['name']) ?></h2>
        <dl class="facts">
          <div><dt>Conta</dt><dd><a href="/admin/cliente/<?= (int) $f['tenant_id'] ?>"><?= e((string) $f['tenant']) ?></a></dd></div>
          <div><dt>CNPJ declarado</dt><dd><?= $f['legal_id'] === null ? '—' : e((string) $f['legal_id']) ?></dd></div>
          <div><dt>Ramo</dt><dd><?= e((string) $f['category']) ?></dd></div>
          <div><dt>Emissões</dt><dd><?= (int) $f['emissoes'] ?></dd></div>
          <div><dt>Pedido em</dt><dd><?= e($i18n->datetime((string) $f['review_requested_at'])) ?></dd></div>
          <div class="wide"><dt>Endereço público</dt>
            <dd><a href="/pt-br/e/<?= e((string) $f['slug']) ?>" target="_blank" rel="noopener">/e/<?= e((string) $f['slug']) ?></a></dd></div>
          <?php if (!empty($f['review_note'])): ?>
            <div class="wide"><dt>O que o cliente escreveu</dt><dd><?= e((string) $f['review_note']) ?></dd></div>
          <?php endif; ?>
        </dl>

        <form method="post" class="form">
          <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
          <input type="hidden" name="issuer" value="<?= (int) $f['id'] ?>">
          <label for="nivel-<?= (int) $f['id'] ?>">Conceder</label>
          <select id="nivel-<?= (int) $f['id'] ?>" name="nivel">
            <?php foreach ($niveis as $n => $rotulo): ?>
              <option value="<?= $n ?>"><?= e($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="base-<?= (int) $f['id'] ?>">Com base em quê</label>
          <input id="base-<?= (int) $f['id'] ?>" name="base" type="text" maxlength="190" required
                 placeholder="CNPJ conferido na Receita, domínio batendo com o e-mail do dono">
          <div class="adm-actions">
            <button class="button" type="submit" name="decisao" value="aprovar">Aprovar</button>
            <button class="button ghost" type="submit" name="decisao" value="recusar">Recusar</button>
          </div>
        </form>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="card adm-card">
    <h2 class="adm-h2">Decisões recentes</h2>
    <?php if ($recentes === []): ?>
      <p class="muted">Nenhuma decisão registrada ainda.</p>
    <?php else: ?>
      <table class="grid">
        <thead><tr><th>Emissor</th><th>Conta</th><th>Nível</th><th>Base</th><th>Quando</th></tr></thead>
        <tbody>
        <?php foreach ($recentes as $r): ?>
          <tr>
            <td><?= e((string) $r['name']) ?></td>
            <td><?= e((string) $r['tenant']) ?></td>
            <td><strong><?= (int) $r['level'] ?></strong></td>
            <td class="adm-basis"><?= e((string) ($r['level_basis'] ?? '')) ?></td>
            <td><?= e(substr((string) $r['level_set_at'], 0, 10)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
