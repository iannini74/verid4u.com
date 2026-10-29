<?php
/** @var I18n $i18n */
/** @var ?string $error */
/** @var string $typed */
$pageTitle = $i18n->t('meta.home.title');
$pageDesc = $i18n->t('meta.home.desc');
$sample = Code::format(Code::full(Store::showcase()[0]));
?>

<section class="hero">
  <div class="wrap hero-inner">
    <h1 class="hero-title"><?= e($i18n->t('hero.title')) ?></h1>
    <p class="hero-sub"><?= e($i18n->t('hero.sub')) ?></p>

    <form class="verifier<?= $error !== null ? ' has-error' : '' ?>" method="post" action="<?= e($i18n->url()) ?>" novalidate>
      <label class="verifier-label" for="codigo"><?= e($i18n->t('hero.label')) ?></label>
      <div class="verifier-row">
        <input
          id="codigo"
          name="codigo"
          type="text"
          inputmode="latin"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
          maxlength="20"
          placeholder="<?= e($i18n->t('hero.placeholder')) ?>"
          value="<?= e($typed) ?>"
          aria-describedby="codigo-hint<?= $error !== null ? ' codigo-error' : '' ?>"
          <?= $error !== null ? 'aria-invalid="true"' : '' ?>
          data-code-input
          autofocus>
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

    <div class="samples">
      <span><?= e($i18n->t('hero.examples')) ?></span>
      <ul>
        <?php foreach (Store::showcase() as $base): $code = Code::format(Code::full($base)); ?>
          <li><a href="<?= e($i18n->url('/v/' . $code)) ?>"><?= e($code) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="band band-pitch">
  <div class="wrap narrow">
    <h2 class="pitch-title"><?= e($i18n->t('pitch.title')) ?></h2>
    <p class="pitch-body"><?= e($i18n->t('pitch.body')) ?></p>
  </div>
</section>

<section class="band" id="como">
  <div class="wrap">
    <h2 class="section-title"><?= e($i18n->t('how.title')) ?></h2>
    <ol class="steps">
      <?php for ($n = 1; $n <= 3; $n++): ?>
        <li>
          <span class="step-n"><?= $n ?></span>
          <h3><?= e($i18n->t("how.$n.title")) ?></h3>
          <p><?= e($i18n->t("how.$n.body")) ?></p>
        </li>
      <?php endfor; ?>
    </ol>

    <figure class="cert-preview" aria-label="<?= e($i18n->t('how.2.title')) ?>">
      <div class="cert-paper">
        <p class="cert-kicker"><?= e($i18n->t('label.program')) ?></p>
        <p class="cert-program">NR-35 — Trabalho em Altura</p>
        <p class="cert-holder">Maria S. L.</p>
        <div class="cert-strip">
          <div class="cert-qr" data-qr="<?= e(base_url() . $i18n->url('/v/' . $sample)) ?>" aria-hidden="true"></div>
          <div class="cert-strip-text">
            <code><?= e($sample) ?></code>
            <span>verid4u.com</span>
          </div>
          <div class="cert-seal">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 3.6 5.2v6.6c0 5.3 3.6 8.6 8.4 9.9 4.8-1.3 8.4-4.6 8.4-9.9V5.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m8.2 12.1 2.8 2.9 5-5.6" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
        </div>
      </div>
      <figcaption><?= e($i18n->t('how.3.body')) ?></figcaption>
    </figure>
  </div>
</section>

<section class="band band-alt">
  <div class="wrap">
    <h2 class="section-title"><?= e($i18n->t('paths.title')) ?></h2>
    <div class="paths">
      <article class="path">
        <h3><?= e($i18n->t('paths.register.title')) ?></h3>
        <p><?= e($i18n->t('paths.register.body')) ?></p>
      </article>
      <article class="path">
        <h3><?= e($i18n->t('paths.issue.title')) ?></h3>
        <p><?= e($i18n->t('paths.issue.body')) ?></p>
      </article>
    </div>
  </div>
</section>

<section class="band" id="para-quem">
  <div class="wrap">
    <h2 class="section-title"><?= e($i18n->t('audiences.title')) ?></h2>
    <ul class="audiences">
      <?php foreach (['sst', 'schools', 'universities', 'hr', 'creators'] as $who): ?>
        <li>
          <h3><?= e($i18n->t("audiences.$who")) ?></h3>
          <p><?= e($i18n->t("audiences.$who.body")) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="band band-alt" id="duvidas">
  <div class="wrap narrow">
    <h2 class="section-title"><?= e($i18n->t('faq.title')) ?></h2>
    <div class="faq">
      <?php for ($n = 1; $n <= 3; $n++): ?>
        <details<?= $n === 1 ? ' open' : '' ?>>
          <summary><?= e($i18n->t("faq.$n.q")) ?></summary>
          <p><?= e($i18n->t("faq.$n.a")) ?></p>
        </details>
      <?php endfor; ?>
    </div>
  </div>
</section>

<section class="band band-cta">
  <div class="wrap narrow cta">
    <h2><?= e($i18n->t('cta.title')) ?></h2>
    <p><?= e($i18n->t('cta.body')) ?></p>
    <a class="button" href="#main"><?= e($i18n->t('cta.button')) ?></a>
  </div>
</section>
