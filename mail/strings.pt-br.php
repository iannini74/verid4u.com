<?php
/**
 * Textos das mensagens.
 *
 * Regra de tom: o titular não escolheu o VerID4U — quem escolheu foi a
 * instituição. Então a mensagem fala do certificado dele, não da plataforma,
 * e nunca pede nada em troca.
 */
return [
    'common.footer' => 'Você recebeu esta mensagem porque {issuer} registrou um certificado em seu nome.',
    'common.validity_until' => 'A habilitação é válida até {valid_until}.',
    'common.validity_none' => 'Este certificado não tem prazo de validade.',
    'common.preheader' => 'Seu certificado no VerID4U.',

    'issuance_created.subject' => 'Seu certificado de {program}',
    'issuance_created.preheader' => 'Código {code} — confira a autenticidade a qualquer momento.',
    'issuance_created.heading' => 'Seu certificado está pronto',
    'issuance_created.p1' => '{issuer} emitiu o seu certificado de {program}, concluído em {issued_on}.',
    'issuance_created.p2' => 'O código é {code}. Qualquer pessoa pode conferir a autenticidade com ele, de graça e sem cadastro — inclusive um RH, numa entrevista.',
    'issuance_created.p3' => '{validity}',
    'issuance_created.cta' => 'Ver meu certificado',

    'issuance_registered.subject' => 'Seu certificado de {program} agora é verificável',
    'issuance_registered.preheader' => 'Código {code} — confira a autenticidade a qualquer momento.',
    'issuance_registered.heading' => 'Seu certificado agora é verificável',
    'issuance_registered.p1' => '{issuer} registrou no VerID4U o seu certificado de {program}, concluído em {issued_on}.',
    'issuance_registered.p2' => 'O documento continua sendo o que a instituição emitiu. O que mudou é que agora ele tem o código {code}, e qualquer pessoa pode confirmar que ele é autêntico e não foi alterado.',
    'issuance_registered.p3' => '{validity}',
    'issuance_registered.cta' => 'Ver meu certificado',

    'issuance_expiring.subject' => 'Sua habilitação em {program} vence em {days} dias',
    'issuance_expiring.preheader' => 'Vence em {valid_until}. Hora de agendar a reciclagem.',
    'issuance_expiring.heading' => 'Hora de renovar',
    'issuance_expiring.p1' => 'Sua habilitação em {program} vence em {valid_until} — faltam {days} dias.',
    'issuance_expiring.p2' => 'O certificado continua autêntico depois dessa data; o que expira é a habilitação. Procure {issuer} para agendar a reciclagem.',
    'issuance_expiring.p3' => '',
    'issuance_expiring.cta' => 'Ver a situação atual',

    'issuance_revoked.subject' => 'Seu certificado de {program} foi cancelado',
    'issuance_revoked.preheader' => 'O emissor cancelou o certificado {code}.',
    'issuance_revoked.heading' => 'Este certificado foi cancelado',
    'issuance_revoked.p1' => '{issuer} cancelou o certificado {code}, de {program}.',
    'issuance_revoked.p2' => 'Motivo informado: {reason}',
    'issuance_revoked.p3' => 'A partir de agora, quem consultar este código verá que ele foi revogado. Se você acredita que houve engano, procure a instituição.',
    'issuance_revoked.cta' => 'Ver a situação atual',
];
