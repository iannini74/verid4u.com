<?php
/**
 * Central do selo.
 *
 * A tela entrega três coisas e esconde uma quarta.
 *
 * Entrega: o selo do site (servido por nós, nunca baixável), a tarja para
 * colar no certificado que o emissor já produz, e o endereço público para
 * onde o selo aponta.
 *
 * Esconde: o texto do selo. Não há campo para editar "Emissor verificado",
 * nem o qualificador, nem a linha de verificação ativa. Se houvesse, o selo
 * deixaria de ser prova e passaria a ser arte — e arte qualquer um faz no
 * Canva. O emissor escolhe o ramo; o resto sai do estado dele no registro.
 *
 * @var I18n $i18n
 * @var ?array $issuer
 * @var string $slug
 * @var ?string $error
 */
$pageTitle = $i18n->a('seal.title');
$base = base_url();
?>
<div class="app-wrap">
  <h1 class="app-h1"><?= e($i18n->a('seal.title')) ?></h1>
  <p class="app-lead"><?= e($i18n->a('seal.intro')) ?></p>

  <?php if (!empty($flash)): ?><p class="flash flash-ok"><?= e($flash) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p class="flash flash-bad" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php if ($issuer === null): ?>
    <p class="flash flash-bad"><?= e($i18n->a('seal.no_issuer')) ?></p>
  <?php else: ?>

    <?php
    $urlSelo = $base . '/selo/' . $slug . '.svg';
    $urlEmissor = $base . $i18n->url('/e/' . $slug);
    $conferido = (int) $issuer['level'] >= 1;
    $ramo = (string) $issuer['category'];
    ?>

    <!-- ------------------------------------------- 1. o selo do site -->
    <section class="card seal-card">
      <h2 class="app-h2"><?= e($i18n->a('seal.site.title')) ?></h2>
      <p class="field-hint"><?= e($i18n->a('seal.site.body')) ?></p>

      <div class="seal-stage">
        <figure>
          <img src="<?= e($urlSelo . '?l=' . $i18n->locale()) ?>" alt="<?= e($i18n->a('seal.preview.alt')) ?>" height="44">
          <figcaption><?= e($i18n->a('seal.variant.horizontal')) ?> · <?= e($i18n->a('seal.min', ['px' => 44])) ?></figcaption>
        </figure>
        <figure class="seal-stage-dark">
          <img src="<?= e($urlSelo . '?l=' . $i18n->locale() . '&t=escuro') ?>" alt="<?= e($i18n->a('seal.preview.alt')) ?>" height="48">
          <figcaption><?= e($i18n->a('seal.variant.dark')) ?></figcaption>
        </figure>
        <figure>
          <img src="<?= e($urlSelo . '?l=' . $i18n->locale() . '&v=compacto') ?>" alt="<?= e($i18n->a('seal.preview.alt')) ?>" height="88">
          <figcaption><?= e($i18n->a('seal.variant.compact')) ?> · <?= e($i18n->a('seal.min', ['px' => 88])) ?></figcaption>
        </figure>
      </div>

      <p class="field-hint"><?= e($i18n->a('seal.min.why')) ?></p>
      <p class="field-hint"><?= e($i18n->a('seal.compact.note')) ?></p>

      <h3 class="app-h3"><?= e($i18n->a('seal.embed.title')) ?></h3>
      <p class="field-hint"><?= e($i18n->a('seal.embed.body')) ?></p>
      <?php
      $snippet = '<a href="' . $urlEmissor . '" target="_blank" rel="noopener">' . "\n"
               . '  <img src="' . $urlSelo . '" alt="' . $i18n->t('badge.verified') . ' — ' . $issuer['name'] . '" height="48">' . "\n"
               . '</a>';
      ?>
      <pre class="snippet"><code><?= e($snippet) ?></code></pre>
      <button class="button ghost" type="button"
              data-copy="<?= e($snippet) ?>"
              data-copied="<?= e($i18n->a('seal.copied')) ?>"><?= e($i18n->a('seal.copy')) ?></button>

      <div class="seal-rules">
        <h3 class="app-h3"><?= e($i18n->a('seal.rules.title')) ?></h3>
        <ul>
          <li><?= e($i18n->a('seal.rules.1')) ?></li>
          <li><?= e($i18n->a('seal.rules.2')) ?></li>
          <li><?= e($i18n->a('seal.rules.3')) ?></li>
          <li><?= e($i18n->a('seal.rules.4')) ?></li>
        </ul>
      </div>
    </section>

    <!-- --------------------------------- 2. a tarja do certificado -->
    <section class="card seal-card">
      <h2 class="app-h2"><?= e($i18n->a('seal.strip.title')) ?></h2>
      <p class="field-hint"><?= e($i18n->a('seal.strip.body')) ?></p>

      <?php
      // Código de demonstração, que existe de verdade no registro: a tarja de
      // exemplo tem de levar a uma verificação que funciona, senão ensina a
      // montar tarja que não leva a lugar nenhum.
      $exemplo = 'K7M2-93FQ-5XB0';
      ?>
      <div class="strip-demo">
        <div class="cert-qr" data-qr="<?= e($base . $i18n->url('/v/' . $exemplo)) ?>" aria-hidden="true"></div>
        <?= Brand::mark('open', 'sheet-strip-mark') ?>
        <div class="sheet-strip-text">
          <p class="sheet-code"><code><?= e($exemplo) ?></code></p>
          <p class="sheet-verify"><?= e($i18n->t('cert.strip.line')) ?></p>
          <p class="sheet-domain">VERID4U.COM</p>
        </div>
      </div>

      <p class="field-hint"><?= e($i18n->a('seal.strip.rules')) ?></p>
      <p>
        <a class="quiet-link" href="<?= e($i18n->url('/app/certificados')) ?>"><?= e($i18n->a('seal.strip.where')) ?></a>
      </p>
    </section>

    <!-- --------------------------------------- 3. a logo do emissor -->
    <section class="card seal-card">
      <h2 class="app-h2"><?= e($i18n->a('logo.title')) ?></h2>
      <p class="field-hint"><?= e($i18n->a('logo.body')) ?></p>

      <?php if (($logo ?? '') !== ''): ?>
        <div class="logo-stage">
          <img src="<?= e($base . '/marca/' . $slug) ?>?v=<?= e(substr(md5($logo), 0, 8)) ?>"
               alt="<?= e($i18n->a('logo.current')) ?>" height="56">
          <form method="post" action="<?= e($i18n->url('/app/selo')) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="acao" value="logo_remover">
            <button class="button ghost" type="submit"><?= e($i18n->a('logo.remove')) ?></button>
          </form>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e($i18n->url('/app/selo')) ?>"
            enctype="multipart/form-data" class="form-narrow logo-form">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="acao" value="logo">
        <?php
        /* Sem onchange inline: a CSP do site é script-src 'self', e atributo
           de evento é script inline — seria recusado em silêncio e o botão
           não faria nada. Campo visível e botão de enviar funcionam sempre. */
        ?>
        <label for="logo-arq" class="field-label"><?= e($i18n->a(($logo ?? '') !== '' ? 'logo.replace' : 'logo.choose')) ?></label>
        <input id="logo-arq" type="file" name="logo" accept="image/png,image/jpeg" required>
        <button class="button" type="submit"><?= e($i18n->a('logo.send')) ?></button>
      </form>

      <p class="field-hint"><?= e($i18n->a('logo.rules', ['px' => Logo::MIN_WIDTH, 'mb' => 1])) ?></p>
      <p class="field-hint"><?= e($i18n->a('logo.no_svg')) ?></p>
    </section>

    <!-- -------------------------------------------- 4. o ramo declarado -->
    <section class="card seal-card">
      <h2 class="app-h2"><?= e($i18n->a('seal.ramo.title')) ?></h2>
      <p class="field-hint"><?= e($i18n->a('seal.ramo.body')) ?></p>

      <form method="post" action="<?= e($i18n->url('/app/selo')) ?>" class="form-narrow">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <fieldset class="choices">
          <legend class="sr-only"><?= e($i18n->a('seal.ramo.title')) ?></legend>
          <?php foreach (Brand::CATEGORIES as $cat): ?>
            <label class="choice">
              <input type="radio" name="ramo" value="<?= e($cat) ?>" <?= $ramo === $cat ? 'checked' : '' ?>>
              <span class="choice-label"><?= e($i18n->a('seal.ramo.' . $cat)) ?></span>
              <span class="choice-sample"><?= e($i18n->t('badge.qualifier.' . $cat)) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <button class="button" type="submit"><?= e($i18n->a('seal.save')) ?></button>
      </form>
    </section>

    <!-- ------------------------ 5. a sua marca na verificação -->
    <section class="card seal-card">
      <h2 class="app-h2"><?= e($i18n->a('skin.title')) ?></h2>
      <p class="field-hint"><?= e($i18n->a('skin.body', ['n' => Plan::BRAND_THRESHOLD])) ?></p>

      <?php
      $corAtual = Skin::normalizeHex((string) ($skin['brand_color'] ?? '')) ?? '#1D5BE0';
      $ligada = !empty($skin['brand_enabled_at']);
      $medida = Skin::check($corAtual);
      ?>

      <?php if (!$skinOk): ?>
        <p class="flash flash-warn">
          <?= e($i18n->a('skin.locked', [
                'n' => Plan::BRAND_THRESHOLD,
                'pico' => (int) $uso['used'],
                'mes' => $uso['competencia'],
          ])) ?>
        </p>
      <?php endif; ?>

      <div class="skin-preview" style="--demo: <?= e($corAtual) ?>">
        <div class="skin-preview-band">
          <span class="skin-preview-issuer">
            <?php if (($logo ?? '') !== ''): ?>
              <img src="<?= e($base . '/marca/' . $slug) ?>" alt="" height="22">
            <?php endif; ?>
            <?= e((string) $issuer['name']) ?>
          </span>
          <span class="skin-preview-by">
            <?= Brand::mark('fit', 'skin-preview-mark') ?> <?= e($i18n->t('skin.registered_at')) ?>
          </span>
        </div>
        <div class="skin-preview-body">
          <span class="skin-preview-verdict"><?= e($i18n->t('status.verified')) ?></span>
          <code><?= e($exemplo ?? 'K7M2-93FQ-5XB0') ?></code>
          <span class="skin-preview-link"><?= e($i18n->a('skin.preview.link')) ?></span>
        </div>
      </div>

      <form method="post" action="<?= e($i18n->url('/app/selo')) ?>" class="form form-narrow">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="acao" value="marca">
        <label for="cor"><?= e($i18n->a('skin.color')) ?></label>
        <input id="cor" name="cor" type="text" maxlength="7" value="<?= e($corAtual) ?>"
               placeholder="#1D5BE0" spellcheck="false" <?= $skinOk ? '' : 'disabled' ?>>
        <p class="field-hint">
          <?= e($i18n->a('skin.measured', [
                'r' => number_format($medida['ratio'], 2, ',', ''),
                'min' => '4,5',
          ])) ?>
        </p>
        <button class="button" type="submit" <?= $skinOk ? '' : 'disabled' ?>><?= e($i18n->a('skin.save')) ?></button>
      </form>

      <?php if ($ligada): ?>
        <form method="post" action="<?= e($i18n->url('/app/selo')) ?>">
          <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
          <input type="hidden" name="acao" value="marca_desligar">
          <button class="linklike" type="submit"><?= e($i18n->a('skin.turn_off')) ?></button>
        </form>
      <?php endif; ?>

      <div class="seal-rules">
        <h3 class="app-h3"><?= e($i18n->a('skin.rules')) ?></h3>
        <ul>
          <li><?= e($i18n->a('skin.r1')) ?></li>
          <li><?= e($i18n->a('skin.r2')) ?></li>
          <li><?= e($i18n->a('skin.r3')) ?></li>
        </ul>
      </div>
    </section>

    <!-- ----------------------------------- 6. o endereço público -->
    <section class="card seal-card">
      <h2 class="app-h2"><?= e($i18n->a('seal.page.title')) ?></h2>
      <p class="field-hint"><?= e($i18n->a('seal.page.body')) ?></p>
      <p class="seal-url">
        <a href="<?= e($i18n->url('/e/' . $slug)) ?>" target="_blank" rel="noopener"><?= e($urlEmissor) ?></a>
      </p>
      <dl class="facts">
        <div>
          <dt><?= e($i18n->a('seal.state')) ?></dt>
          <dd>
            <span class="pill pill-<?= $conferido ? 'verified' : 'expired' ?>">
              <?= e($i18n->t($conferido ? 'badge.verified' : 'badge.registers')) ?>
            </span>
          </dd>
        </div>
        <div><dt><?= e($i18n->t('label.level')) ?></dt><dd><?= e($i18n->t('level.' . (int) $issuer['level'])) ?></dd></div>
        <div><dt><?= e($i18n->t('issuer.counts.total')) ?></dt><dd><?= (int) $issuer['total'] ?></dd></div>
      </dl>
      <?php if (!$conferido): ?>
        <p class="field-hint"><?= e($i18n->a('seal.level0')) ?></p>
      <?php endif; ?>

      <?php /* O pedido de verificação. O nível nunca é decidido aqui. */ ?>
      <?php if (($nivel ?? 0) === 0): ?>
        <div class="seal-rules">
          <h3 class="app-h3"><?= e($i18n->a('review.title')) ?></h3>
          <?php if (($review ?? 'none') === 'pending'): ?>
            <p class="field-hint"><?= e($i18n->a('review.pending')) ?></p>
          <?php else: ?>
            <p class="field-hint"><?= e($i18n->a('review.body')) ?></p>
            <form method="post" action="<?= e($i18n->url('/app/selo')) ?>" class="form form-narrow">
              <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
              <input type="hidden" name="acao" value="pedir_verificacao">
              <label for="nota"><?= e($i18n->a('review.note')) ?></label>
              <input id="nota" name="nota" type="text" maxlength="255"
                     placeholder="<?= e($i18n->a('review.note.example')) ?>">
              <button class="button" type="submit"><?= e($i18n->a('review.send')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>


    </section>

  <?php endif; ?>
</div>
