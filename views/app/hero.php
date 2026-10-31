<?php
/** @var I18n $i18n */
/** @var array $variants */
/** @var array $perf */
/** @var ?array $atual */
$pageTitle = $i18n->a('hero.title');
$porSlug = [];
foreach ($perf as $p) { $porSlug[$p['variant_slug']] = $p; }
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('hero.title')) ?></h1>
  <p class="app-lead" style="max-width:72ch"><?= e($i18n->a('hero.intro', ['dias' => Hero::DIAS_POR_CICLO])) ?></p>

  <section class="card" style="margin-bottom:var(--space-5)">
    <h2 class="app-h2"><?= e($i18n->a('hero.current')) ?></h2>
    <?php if ($atual === null): ?>
      <p class="muted"><?= e($i18n->a('hero.none')) ?></p>
    <?php else: ?>
      <?php if (($atual['kicker'] ?? '') !== ''): ?>
        <p class="panel-kicker"><?= e($atual['kicker']) ?></p>
      <?php endif; ?>
      <p style="font-family:var(--font-display);font-size:var(--text-heading-l);font-weight:700;letter-spacing:-.025em;margin:0 0 var(--space-2)">
        <?= e($atual['title']) ?>
      </p>
      <p class="muted" style="margin:0 0 var(--space-4)"><?= e($atual['sub']) ?></p>
      <dl class="facts">
        <div><dt><?= e($i18n->a('hero.slug')) ?></dt><dd><code class="small"><?= e($atual['slug']) ?></code></dd></div>
        <div><dt><?= e($i18n->a('hero.audience')) ?></dt><dd><?= e($atual['alvo'] ?? '—') ?></dd></div>
        <div><dt><?= e($i18n->a('hero.cycle')) ?></dt><dd>#<?= Hero::cycle() ?></dd></div>
        <div>
          <dt><?= e($i18n->a('hero.next')) ?></dt>
          <dd><?= e($i18n->a('hero.in_days', ['n' => Hero::daysLeft()])) ?></dd>
        </div>
      </dl>
      <?php if (!empty($atual['fixada'])): ?>
        <p class="flash flash-ok" style="margin-top:var(--space-4)"><?= e($i18n->a('hero.pinned')) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="app-h2"><?= e($i18n->a('hero.performance')) ?></h2>
    <p class="field-hint" style="margin:0 0 var(--space-4)"><?= e($i18n->a('hero.perf_hint')) ?></p>
    <table class="grid">
      <thead><tr>
        <th><?= e($i18n->a('hero.variant')) ?></th>
        <th><?= e($i18n->a('hero.audience')) ?></th>
        <th><?= e($i18n->a('hero.impressions')) ?></th>
        <th><?= e($i18n->a('hero.conversions')) ?></th>
        <th><?= e($i18n->a('hero.rate')) ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($variants as $pos => $v):
          $d = $porSlug[$v['slug']] ?? null;
          $emUso = $atual !== null && $v['slug'] === $atual['slug'];
      ?>
        <tr<?= $emUso ? ' style="background:var(--brand-tint)"' : '' ?>>
          <td>
            <code class="small"><?= e($v['slug']) ?></code>
            <?= $emUso ? ' <span class="chip">' . e($i18n->a('hero.now')) . '</span>' : '' ?>
            <div class="field-hint" style="margin-top:4px"><?= e(mb_substr($v['title'], 0, 64)) ?></div>
          </td>
          <td><?= e($v['alvo'] ?? '—') ?></td>
          <td><?= $d ? (int) $d['impressions'] : 0 ?></td>
          <td><?= $d ? (int) $d['conversions'] : 0 ?></td>
          <td><?= $d && $d['impressions'] > 0 ? e(number_format((float) $d['taxa'], 2, ',', '.')) . '%' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
