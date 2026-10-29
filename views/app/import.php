<?php
/** @var I18n $i18n */
/** @var array $programs */
/** @var ?array $batch */
/** @var ?array $previa */
/** @var ?array $resultado */
$pageTitle = $i18n->a('imp.title');
$aberto = $batch !== null && $batch['status'] === 'parsed';
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('imp.title')) ?></h1>

  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php if ($resultado !== null): ?>
    <!-- ───────────────────────────── passo 3: o que aconteceu ─────────── -->
    <div class="flash flash-ok">
      <p><strong><?= e($i18n->a('imp.done')) ?></strong></p>
    </div>
    <ul class="tiles">
      <li class="tile-ok"><span class="tile-n"><?= (int) $resultado['created'] ?></span><span class="tile-l"><?= e($i18n->a('imp.created')) ?></span></li>
      <li><span class="tile-n"><?= (int) $resultado['skip'] ?></span><span class="tile-l"><?= e($i18n->a('imp.skipped')) ?></span></li>
      <li class="tile-warn"><span class="tile-n"><?= (int) $resultado['fail'] ?></span><span class="tile-l"><?= e($i18n->a('imp.failed')) ?></span></li>
    </ul>
    <p>
      <a class="btn btn-ghost btn-sm" href="<?= e($i18n->url('/app/certificados')) ?>"><?= e($i18n->a('list.title')) ?></a>
      <?php if ((int) $resultado['skip'] + (int) $resultado['fail'] > 0): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e($i18n->url('/app/importar/erros/' . (int) $batch['id'])) ?>"><?= e($i18n->a('imp.download_errors')) ?></a>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="<?= e($i18n->url('/app/importar')) ?>"><?= e($i18n->a('imp.another')) ?></a>
    </p>

  <?php elseif ($aberto): ?>
    <!-- ───────────────────── passo 2: mapear colunas e simular ────────── -->
    <p class="app-lead" style="max-width:72ch"><?= e($i18n->a('imp.step2')) ?></p>

    <p class="muted" style="font-size:var(--text-body-s)">
      <?= e($i18n->a('imp.file_facts', [
          'nome' => $batch['filename'],
          'linhas' => (int) $batch['total_rows'],
          'sep' => $batch['delimiter_used'] === ';' ? 'ponto e vírgula' : ($batch['delimiter_used'] === "\t" ? 'tabulação' : 'vírgula'),
          'enc' => $batch['encoding_used'],
      ])) ?>
    </p>

    <form method="post" class="form">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
      <input type="hidden" name="lote" value="<?= (int) $batch['id'] ?>">

      <section class="card" style="margin-bottom:var(--space-5)">
        <h2 class="app-h2"><?= e($i18n->a('imp.mapping')) ?></h2>
        <div class="two-col" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
          <?php foreach (Importer::fields((string) $batch['mode']) as $campo => $regra): ?>
            <div>
              <label for="map-<?= e($campo) ?>">
                <?= e($i18n->a('imp.field.' . $campo)) ?><?= $regra['required'] ? ' *' : '' ?>
              </label>
              <select id="map-<?= e($campo) ?>" name="map[<?= e($campo) ?>]">
                <option value="">—</option>
                <?php foreach ($batch['header'] as $i => $coluna): ?>
                  <option value="<?= (int) $i ?>"<?= (string) ($batch['mapping'][$campo] ?? '') === (string) $i ? ' selected' : '' ?>>
                    <?= e($coluna !== '' ? $coluna : 'Coluna ' . ($i + 1)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="submit" name="acao" value="simular"><?= e($i18n->a('imp.simulate')) ?></button>
      </section>

      <?php if ($previa !== null): ?>
        <section class="card">
          <h2 class="app-h2"><?= e($i18n->a('imp.preview')) ?></h2>
          <ul class="tiles">
            <li class="tile-ok"><span class="tile-n"><?= (int) $previa['summary']['create'] ?></span><span class="tile-l"><?= e($i18n->a('imp.will_create')) ?></span></li>
            <li><span class="tile-n"><?= (int) $previa['summary']['skip'] ?></span><span class="tile-l"><?= e($i18n->a('imp.will_skip')) ?></span></li>
            <li class="tile-warn"><span class="tile-n"><?= (int) $previa['summary']['fail'] ?></span><span class="tile-l"><?= e($i18n->a('imp.will_fail')) ?></span></li>
          </ul>

          <table class="grid">
            <thead><tr>
              <th><?= e($i18n->a('imp.line')) ?></th>
              <th><?= e($i18n->a('imp.field.holder_name')) ?></th>
              <th><?= e($i18n->a('imp.field.program_title')) ?></th>
              <th><?= e($i18n->a('imp.field.issued_on')) ?></th>
              <th><?= e($i18n->a('imp.outcome')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach (array_slice($previa['rows'], 0, 25) as $linha): ?>
              <tr>
                <td><?= (int) $linha['line_no'] ?></td>
                <td><?= e($linha['data']['holder_name'] !== '' ? $linha['data']['holder_name'] : '—') ?></td>
                <td><?= e($linha['data']['program_title'] !== '' ? $linha['data']['program_title'] : '—') ?></td>
                <td><?= e($linha['data']['issued_on'] ?? '—') ?></td>
                <td>
                  <span class="pill pill-<?= $linha['outcome'] === 'create' ? 'verified' : ($linha['outcome'] === 'skip' ? 'expired' : 'revoked') ?>">
                    <?= e($i18n->a('imp.outcome.' . $linha['outcome'])) ?>
                  </span>
                  <?php if ($linha['reason'] !== null): ?>
                    <span class="chip"><?= e($i18n->a('imp.reason.' . $linha['reason'])) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php if (count($previa['rows']) > 25): ?>
            <p class="field-hint"><?= e($i18n->a('imp.more_rows', ['n' => count($previa['rows']) - 25])) ?></p>
          <?php endif; ?>

          <div class="row-actions" style="margin-top:var(--space-5);gap:var(--space-4)">
            <button type="submit" name="acao" value="gravar"<?= (int) $previa['summary']['create'] === 0 ? ' disabled' : '' ?>>
              <?= e($i18n->a('imp.commit', ['n' => (int) $previa['summary']['create']])) ?>
            </button>
            <button type="submit" name="acao" value="descartar" class="linklike" style="align-self:center">
              <?= e($i18n->a('imp.discard')) ?>
            </button>
          </div>
        </section>
      <?php endif; ?>
    </form>

  <?php else: ?>
    <!-- ────────────────────────── passo 1: enviar a planilha ──────────── -->
    <p class="app-lead" style="max-width:72ch"><?= e($i18n->a('imp.step1')) ?></p>

    <section class="card app-narrow-inner">
      <form method="post" class="form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="acao" value="enviar">

        <label><?= e($i18n->a('imp.mode')) ?></label>
        <label class="check"><input type="radio" name="mode" value="register" checked data-mode> <?= e($i18n->a('imp.mode.register')) ?></label>
        <label class="check"><input type="radio" name="mode" value="issue" data-mode<?= $programs === [] ? ' disabled' : '' ?>> <?= e($i18n->a('imp.mode.issue')) ?></label>
        <?php if ($programs === []): ?>
          <p class="field-hint"><?= e($i18n->a('issue.no_programs')) ?></p>
        <?php else: ?>
          <div data-mode-issue hidden>
            <label for="program_id"><?= e($i18n->a('issue.program')) ?></label>
            <select id="program_id" name="program_id">
              <?php foreach ($programs as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?> — <?= (int) $p['hours'] ?> h</option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <label for="arquivo"><?= e($i18n->a('imp.file')) ?></label>
        <input id="arquivo" name="arquivo" type="file" accept=".csv,.tsv,.txt" required>
        <p class="field-hint"><?= e($i18n->a('imp.file_hint')) ?></p>

        <button type="submit"><?= e($i18n->a('imp.upload')) ?></button>
      </form>
    </section>

    <p style="margin-top:var(--space-5)">
      <a class="quiet-link" href="/assets/demo/modelo-importacao.csv" download><?= e($i18n->a('imp.template')) ?></a>
    </p>
  <?php endif; ?>
</div>
