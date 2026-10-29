<?php
return [
    'common.footer' => 'Recibes este mensaje porque {issuer} registró un certificado a tu nombre.',
    'common.validity_until' => 'La habilitación es válida hasta el {valid_until}.',
    'common.validity_none' => 'Este certificado no tiene fecha de caducidad.',
    'common.preheader' => 'Tu certificado en VerID4U.',

    'issuance_created.subject' => 'Tu certificado de {program}',
    'issuance_created.preheader' => 'Código {code}: comprueba la autenticidad cuando quieras.',
    'issuance_created.heading' => 'Tu certificado está listo',
    'issuance_created.p1' => '{issuer} ha emitido tu certificado de {program}, finalizado el {issued_on}.',
    'issuance_created.p2' => 'El código es {code}. Cualquiera puede comprobar su autenticidad con él, gratis y sin registrarse.',
    'issuance_created.p3' => '{validity}',
    'issuance_created.cta' => 'Ver mi certificado',

    'issuance_registered.subject' => 'Tu certificado de {program} ya se puede verificar',
    'issuance_registered.preheader' => 'Código {code}: comprueba la autenticidad cuando quieras.',
    'issuance_registered.heading' => 'Tu certificado ya se puede verificar',
    'issuance_registered.p1' => '{issuer} ha registrado en VerID4U tu certificado de {program}, finalizado el {issued_on}.',
    'issuance_registered.p2' => 'El documento sigue siendo el que emitió la entidad. Lo que cambia es que ahora tiene el código {code}, y cualquiera puede confirmar que es auténtico y no se ha alterado.',
    'issuance_registered.p3' => '{validity}',
    'issuance_registered.cta' => 'Ver mi certificado',

    'issuance_expiring.subject' => 'Tu habilitación en {program} caduca en {days} días',
    'issuance_expiring.preheader' => 'Caduca el {valid_until}. Toca programar el reciclaje.',
    'issuance_expiring.heading' => 'Toca renovar',
    'issuance_expiring.p1' => 'Tu habilitación en {program} caduca el {valid_until}: quedan {days} días.',
    'issuance_expiring.p2' => 'El certificado sigue siendo auténtico después de esa fecha; lo que caduca es la habilitación. Contacta con {issuer} para programar el reciclaje.',
    'issuance_expiring.p3' => '',
    'issuance_expiring.cta' => 'Ver la situación actual',

    'issuance_revoked.subject' => 'Tu certificado de {program} ha sido anulado',
    'issuance_revoked.preheader' => 'La entidad emisora anuló el certificado {code}.',
    'issuance_revoked.heading' => 'Este certificado ha sido anulado',
    'issuance_revoked.p1' => '{issuer} anuló el certificado {code}, de {program}.',
    'issuance_revoked.p2' => 'Motivo indicado: {reason}',
    'issuance_revoked.p3' => 'A partir de ahora, quien consulte este código verá que está revocado. Si crees que ha sido un error, contacta con la entidad.',
    'issuance_revoked.cta' => 'Ver la situación actual',
];
