<?php
/**
 * Dados da empresa usados nos documentos legais.
 *
 * Campo em branco aparece destacado no site até ser preenchido — melhor um
 * aviso visível do que um documento que parece completo e não é.
 *
 * A comarca é só a cidade, então «Belo Horizonte» a completa de verdade. O
 * endereço não: logradouro, número e CEP são exigência do Decreto 7.962 para
 * quem vende pela internet, e cidade sozinha não cumpre. Por isso
 * 'endereco_completo' existe em separado — o documento passa a ler bem, e o
 * aviso continua cobrando o que de fato falta.
 */
return [
    'razao_social'      => 'INSPIRE4U GOVERNANÇA CORPORATIVA LTDA',
    'cnpj'              => '33.922.313/0001-56',
    'endereco'          => 'Belo Horizonte, Minas Gerais',
    'endereco_completo' => false,               // pendente: logradouro, número e CEP
    'email_dpo'         => 'privacidade@verid4u.com',
    'email_contato'     => 'contato@verid4u.com',
    'foro'              => 'Belo Horizonte',
    'atualizado'        => '2026-11-09',
];
