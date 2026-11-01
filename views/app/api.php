<?php
/**
 * Chaves da API e o manual mínimo para usá-las.
 *
 * A documentação mora ao lado da chave de propósito: quem acabou de criar
 * uma chave é exatamente quem precisa do primeiro exemplo, e mandá-lo
 * procurar num site de docs é perder a pessoa no momento em que ela estava
 * disposta.
 *
 * @var I18n $i18n
 * @var list<array> $keys
 * @var ?array $nova
 * @var ?string $error
 */
$pageTitle = $i18n->a('api.title');
$base = base_url();
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('api.title')) ?></h1>
  <p class="app-lead"><?= e($i18n->a('api.lead')) ?></p>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php if ($nova !== null): ?>
    <section class="card keynew">
      <p class="panel-kicker"><?= e($i18n->a('api.new.title')) ?></p>
      <p class="keynew-warn"><?= e($i18n->a('api.new.once')) ?></p>
      <pre class="snippet"><code><?= e($nova['key']) ?></code></pre>
      <button class="button ghost" type="button"
              data-copy="<?= e($nova['key']) ?>"
              data-copied="<?= e($i18n->a('seal.copied')) ?>"><?= e($i18n->a('api.copy')) ?></button>
    </section>
  <?php endif; ?>

  <section class="card">
    <h2 class="app-h2"><?= e($i18n->a('api.keys')) ?></h2>

    <?php if ($keys === []): ?>
      <p class="muted"><?= e($i18n->a('api.none')) ?></p>
    <?php else: ?>
      <table class="grid">
        <thead><tr>
          <th><?= e($i18n->a('api.col.name')) ?></th>
          <th><?= e($i18n->a('api.col.prefix')) ?></th>
          <th><?= e($i18n->a('api.col.used')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($keys as $k): ?>
          <tr<?= $k['revoked_at'] !== null ? ' class="row-off"' : '' ?>>
            <td><?= e((string) $k['name']) ?></td>
            <td><code><?= e((string) $k['prefix']) ?>…</code></td>
            <td><?= $k['last_used_at'] === null
                  ? '<span class="muted">' . e($i18n->a('api.never')) . '</span>'
                  : e($i18n->datetime((string) $k['last_used_at'])) ?></td>
            <td>
              <?php if ($k['revoked_at'] === null): ?>
                <form method="post" action="<?= e($i18n->url('/app/api')) ?>">
                  <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
                  <input type="hidden" name="acao" value="revogar">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <button class="linklike" type="submit"><?= e($i18n->a('api.revoke')) ?></button>
                </form>
              <?php else: ?>
                <span class="pill pill-revoked"><?= e($i18n->a('api.revoked_at')) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="post" action="<?= e($i18n->url('/app/api')) ?>" class="form form-narrow">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
      <label for="kname"><?= e($i18n->a('api.name')) ?></label>
      <input id="kname" name="name" type="text" maxlength="80"
             placeholder="<?= e($i18n->a('api.name.example')) ?>">
      <p class="field-hint"><?= e($i18n->a('api.name.hint')) ?></p>
      <button class="button" type="submit"><?= e($i18n->a('api.create')) ?></button>
    </form>
  </section>

  <!-- ------------------------------------------------------ o manual -->
  <section class="card">
    <h2 class="app-h2"><?= e($i18n->a('api.doc.title')) ?></h2>
    <p class="field-hint"><?= e($i18n->a('api.doc.lead')) ?></p>

    <h3 class="app-h3"><?= e($i18n->a('api.doc.idem')) ?></h3>
    <p class="field-hint"><?= e($i18n->a('api.doc.idem.body')) ?></p>

    <h3 class="app-h3"><?= e($i18n->a('api.doc.register')) ?></h3>
    <p class="field-hint"><?= e($i18n->a('api.doc.register.body')) ?></p>
    <pre class="snippet"><code>curl -X POST <?= e($base) ?>/api/v1/registrations \
  -H "Authorization: Bearer vk_live_…" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "holder_name":   "Maria Souza Lima",
    "holder_email":  "maria@exemplo.com",
    "program_title": "NR-35 — Trabalho em Altura",
    "norm":          "NR-35",
    "hours":         8,
    "modality":      "presencial",
    "assessed":      true,
    "issued_on":     "2026-11-01",
    "valid_until":   "2028-11-01"
  }'</code></pre>

    <h3 class="app-h3"><?= e($i18n->a('api.doc.issue')) ?></h3>
    <p class="field-hint"><?= e($i18n->a('api.doc.issue.body')) ?></p>
    <pre class="snippet"><code>curl <?= e($base) ?>/api/v1/programs -H "Authorization: Bearer vk_live_…"

curl -X POST <?= e($base) ?>/api/v1/issuances \
  -H "Authorization: Bearer vk_live_…" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"program_id": 1, "holder_name": "Maria Souza Lima"}'</code></pre>

    <h3 class="app-h3"><?= e($i18n->a('api.doc.rest')) ?></h3>
    <pre class="snippet"><code>GET  /api/v1/me                       <?= e($i18n->a('api.ep.me')) ?>

GET  /api/v1/programs                 <?= e($i18n->a('api.ep.programs')) ?>

POST /api/v1/programs                 <?= e($i18n->a('api.ep.program_new')) ?>

POST /api/v1/issuances                <?= e($i18n->a('api.ep.issue')) ?>

POST /api/v1/registrations            <?= e($i18n->a('api.ep.register')) ?>

POST /api/v1/issuances/{codigo}/revoke  <?= e($i18n->a('api.ep.revoke')) ?>

GET  /api/v1/verify/{codigo}          <?= e($i18n->a('api.ep.verify')) ?></code></pre>

    <h3 class="app-h3"><?= e($i18n->a('api.doc.errors')) ?></h3>
    <p class="field-hint"><?= e($i18n->a('api.doc.errors.body')) ?></p>

    <div class="seal-rules">
      <ul>
        <li><?= e($i18n->a('api.rule.1')) ?></li>
        <li><?= e($i18n->a('api.rule.2')) ?></li>
        <li><?= e($i18n->a('api.rule.3')) ?></li>
        <li><?= e($i18n->a('api.rule.4')) ?></li>
      </ul>
    </div>
  </section>
</div>
