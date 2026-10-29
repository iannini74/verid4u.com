<?php
/** @var I18n $i18n */
/** @var ?string $error */
/** @var string $typed */
$pageTitle = $i18n->t('meta.home.title');
$pageDesc = $i18n->t('meta.home.desc');
$showcase = Store::showcase();
$sample = $showcase !== [] ? Code::format(Code::full($showcase[0])) : 'K7M2-93FQ-5XB0';
$counters = Store::counters();
$states = [
    'verified' => 'check',
    'expiring' => 'clock',
    'expired'  => 'clock',
    'revoked'  => 'cross',
];
?>

<section class="hero">
  <div class="wrap hero-inner">
    <div class="hero-grid">
      <div>
        <h1 class="display-xl"><?= e($i18n->t('hero.title')) ?></h1>
        <p class="hero-sub"><?= e($i18n->t('hero.sub')) ?></p>

        <form class="verifier<?= $error !== null ? ' has-error' : '' ?>" method="post" action="<?= e($i18n->url()) ?>" novalidate>
          <label class="verifier-label" for="codigo"><?= e($i18n->t('hero.label')) ?></label>
          <div class="verifier-row">
            <input id="codigo" name="codigo" type="text" inputmode="latin" autocomplete="off"
                   autocapitalize="characters" spellcheck="false" maxlength="20"
                   placeholder="<?= e($i18n->t('hero.placeholder')) ?>" value="<?= e($typed) ?>"
                   aria-describedby="codigo-hint<?= $error !== null ? ' codigo-error' : '' ?>"
                   <?= $error !== null ? 'aria-invalid="true"' : '' ?> data-code-input>
            <button type="submit"><?= e($i18n->t('hero.button')) ?></button>
          </div>
          <?php if ($error !== null): ?>
            <p class="verifier-error" id="codigo-error" role="alert">
              <svg class="icon" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10 5.6v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="14" r="1.1" fill="currentColor"/></svg>
              <?= e($error) ?>
            </p>
          <?php endif; ?>
          <p class="verifier-hint" id="codigo-hint"><?= e($i18n->t('hero.hint')) ?></p>
        </form>

        <?php if ($showcase !== []): ?>
          <div class="samples">
            <span><?= e($i18n->t('hero.examples')) ?></span>
            <ul>
              <?php foreach ($showcase as $b): $c = Code::format(Code::full($b)); ?>
                <li><a href="<?= e($i18n->url('/v/' . $c)) ?>"><?= e($c) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="lookup" data-lookup
             data-url="<?= e($i18n->url('/v/')) ?>"
             data-working="<?= e($i18n->t('lookup.working')) ?>"
             data-notfound="<?= e($i18n->t('lookup.notfound')) ?>"
             data-error="<?= e($i18n->t('filecheck.error')) ?>">
          <p class="lookup-title"><?= e($i18n->t('lookup.title')) ?></p>
          <p class="lookup-body"><?= e($i18n->t('lookup.body')) ?></p>
          <label class="lookup-pick">
            <span><?= e($i18n->t('lookup.button')) ?></span>
            <input type="file" accept=".pdf,.png,.jpg,.jpeg" hidden data-lookup-input>
          </label>
          <p class="lookup-state" data-lookup-state hidden></p>
          <p class="lookup-privacy"><?= e($i18n->t('filecheck.privacy')) ?></p>
        </div>
      </div>

      <aside class="hero-card" aria-hidden="true">
        <div class="hero-card-top">
          <?php $sealClass = 'hero-card-seal'; $sealGlyph = 'check'; require __DIR__ . '/partials/seal.php'; ?>
          <div>
            <div class="hero-card-verdict"><?= e($i18n->t('status.verified')) ?></div>
            <div class="hero-card-code"><?= e($sample) ?></div>
          </div>
        </div>
        <dl>
          <div><dt><?= e($i18n->t('label.program')) ?></dt><dd>NR-35 — Trabalho em Altura</dd></div>
          <div><dt><?= e($i18n->t('label.hours')) ?></dt><dd><?= e($i18n->t('value.hours', ['h' => 8])) ?></dd></div>
          <div><dt><?= e($i18n->t('label.modality')) ?></dt><dd><?= e($i18n->t('modality.presencial')) ?></dd></div>
          <div><dt><?= e($i18n->t('label.validity')) ?></dt><dd><?= e($i18n->date('2028-03-10')) ?></dd></div>
        </dl>
        <p class="hero-card-foot">verid4u.com/v/<?= e($sample) ?></p>
      </aside>
    </div>
  </div>
</section>

<section class="section-tight bg-sunken">
  <div class="wrap counters" data-reveal>
    <div>
      <span class="counter-n" data-count-to="<?= (int) $counters['certificates'] ?>">0</span>
      <span class="counter-l"><?= e($i18n->t('counters.certificates')) ?></span>
    </div>
    <div>
      <span class="counter-n" data-count-to="<?= (int) $counters['verifications'] ?>">0</span>
      <span class="counter-l"><?= e($i18n->t('counters.verifications')) ?></span>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap-narrow">
    <p class="overline" data-reveal><?= e($i18n->t('nav.how')) ?></p>
    <h2 class="display-m measure" data-reveal data-reveal-delay="1"><?= e($i18n->t('pitch.title')) ?></h2>
    <p class="lead" data-reveal data-reveal-delay="2" style="margin-top: var(--space-5); max-width: 62ch;"><?= e($i18n->t('pitch.body')) ?></p>
  </div>
</section>

<section class="section bg-sunken" id="como">
  <div class="wrap">
    <h2 class="display-m stack-lead" data-reveal><?= e($i18n->t('how.title')) ?></h2>
    <ol class="steps">
      <?php for ($n = 1; $n <= 3; $n++): ?>
        <li data-reveal data-reveal-delay="<?= $n - 1 ?>">
          <span class="step-n">0<?= $n ?></span>
          <h3><?= e($i18n->t("how.$n.title")) ?></h3>
          <p><?= e($i18n->t("how.$n.body")) ?></p>
        </li>
      <?php endfor; ?>
    </ol>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <h2 class="display-m stack-lead" data-reveal><?= e($i18n->t('showcase.title')) ?></h2>
    <div class="verdicts">
      <?php $i = 0; foreach ($states as $state => $glyph): ?>
        <article class="verdict-card vc-<?= e($state) ?>" data-reveal data-reveal-delay="<?= min(3, $i++) ?>">
          <?php $sealClass = 'vc-seal'; $sealGlyph = $glyph; require __DIR__ . '/partials/seal.php'; ?>
          <div class="vc-name"><?= e($i18n->t('status.' . $state)) ?></div>
          <p class="vc-desc"><?= e($i18n->t('showcase.' . $state . '.desc')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-sunken">
  <div class="wrap">
    <h2 class="display-m stack-lead" data-reveal><?= e($i18n->t('paths.title')) ?></h2>
    <div class="cards-2">
      <article class="card card-interactive" data-reveal>
        <h3><?= e($i18n->t('paths.register.title')) ?></h3>
        <p><?= e($i18n->t('paths.register.body')) ?></p>
      </article>
      <article class="card card-interactive" data-reveal data-reveal-delay="1">
        <h3><?= e($i18n->t('paths.issue.title')) ?></h3>
        <p><?= e($i18n->t('paths.issue.body')) ?></p>
      </article>
    </div>
  </div>
</section>

<section class="section" id="para-quem">
  <div class="wrap">
    <h2 class="display-m stack-lead" data-reveal><?= e($i18n->t('audiences.title')) ?></h2>
    <ul class="audiences">
      <?php $i = 0; foreach (['sst', 'schools', 'universities', 'hr', 'creators'] as $who): ?>
        <li data-reveal data-reveal-delay="<?= min(3, $i++) ?>">
          <h3><?= e($i18n->t("audiences.$who")) ?></h3>
          <p><?= e($i18n->t("audiences.$who.body")) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section bg-sunken" id="duvidas">
  <div class="wrap-narrow">
    <h2 class="display-m stack-lead" data-reveal><?= e($i18n->t('faq.title')) ?></h2>
    <div class="faq" data-reveal>
      <?php for ($n = 1; $n <= 3; $n++): ?>
        <details<?= $n === 1 ? ' open' : '' ?>>
          <summary><?= e($i18n->t("faq.$n.q")) ?></summary>
          <p><?= e($i18n->t("faq.$n.a")) ?></p>
        </details>
      <?php endfor; ?>
    </div>
  </div>
</section>

<section class="section bg-ink">
  <div class="wrap-narrow cta" data-reveal>
    <h2 class="display-l"><?= e($i18n->t('cta.title')) ?></h2>
    <p class="lead" style="color: rgba(255,255,255,.84); max-width: 44ch; margin-inline: auto;"><?= e($i18n->t('cta.body')) ?></p>
    <a class="btn btn-invert" href="<?= e($i18n->url('/app/entrar')) ?>"><?= e($i18n->t('cta.button')) ?></a>
  </div>
</section>
