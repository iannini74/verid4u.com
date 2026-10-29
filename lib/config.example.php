<?php
/**
 * Modelo de configuração. Copie para config.local.php e preencha.
 * config.local.php NUNCA é versionado nem servido.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'nome_do_banco',
        'user' => 'usuario_do_banco',
        'pass' => 'senha_do_banco',
    ],
    // Chave de 32 bytes em base64, usada para cifrar dados de contato em repouso.
    // Gere com: openssl rand -base64 32
    'app_key' => '',
    // Enquanto true, os registros semeados aparecem marcados como demonstração.
    'demo' => true,
];
