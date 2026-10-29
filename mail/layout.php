<?php
/**
 * Casca HTML das mensagens.
 *
 * Tabelas e estilo embutido de propósito: cliente de e-mail não é navegador,
 * e Outlook ignora folha de estilo externa. Nada de imagem — logotipo em
 * imagem é bloqueado por padrão em metade das caixas de entrada, e o que
 * sobra é um retângulo vazio onde deveria estar a marca.
 *
 * @var string $mailTitulo
 * @var list<string> $mailParagrafos
 * @var string $mailCta
 * @var string $mailUrl
 * @var string $mailRodape
 * @var string $mailPre
 */
$marca = '#1D5BE0';
$tinta = '#0A1020';
$suave = '#566376';
$linha = '#E2E8F0';
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($mailTitulo) ?></title>
</head>
<body style="margin:0;padding:0;background:#F4F7FB;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= e($mailPre) ?></div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F7FB;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:100%;">
        <tr>
          <td style="padding:0 0 20px 4px;font:700 19px/1 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;letter-spacing:-.5px;color:<?= $tinta ?>;">
            VerID<span style="color:<?= $marca ?>;">4U</span>
          </td>
        </tr>

        <tr>
          <td style="background:#FFFFFF;border:1px solid <?= $linha ?>;border-radius:14px;padding:36px 36px 32px;">
            <h1 style="margin:0 0 18px;font:700 23px/1.25 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;letter-spacing:-.4px;color:<?= $tinta ?>;">
              <?= e($mailTitulo) ?>
            </h1>

            <?php foreach ($mailParagrafos as $p): ?>
              <p style="margin:0 0 14px;font:400 15px/1.6 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:<?= $suave ?>;">
                <?= e($p) ?>
              </p>
            <?php endforeach; ?>

            <?php if ($mailUrl !== '' && $mailCta !== ''): ?>
              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
                <tr>
                  <td style="background:<?= $marca ?>;border-radius:999px;">
                    <a href="<?= e($mailUrl) ?>" style="display:inline-block;padding:14px 30px;font:600 15px/1 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#FFFFFF;text-decoration:none;">
                      <?= e($mailCta) ?>
                    </a>
                  </td>
                </tr>
              </table>
              <p style="margin:14px 0 0;font:400 12px/1.5 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#8795A8;word-break:break-all;">
                <?= e($mailUrl) ?>
              </p>
            <?php endif; ?>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 4px 0;font:400 12px/1.6 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#8795A8;">
            <?= e($mailRodape) ?><br>
            <a href="https://<?= e(APP_HOST) ?>" style="color:#8795A8;"><?= e(APP_HOST) ?></a>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>
</body>
</html>
