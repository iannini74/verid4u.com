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
    // Token de uso único para ativar a conta do dono. Gere com:
    // php -r "echo bin2hex(random_bytes(18));"
    'setup_token' => '',

    // E-mail. driver: 'log' não envia nada (só registra), 'php' usa mail(),
    // 'smtp' usa o provedor configurado abaixo, 'off' desliga a fila.
    'mail' => [
        'driver'     => 'log',
        'host'       => '',
        'port'       => 587,
        'secure'     => 'tls',   // tls (STARTTLS, porta 587) | ssl (porta 465) | none
        'user'       => '',
        'pass'       => '',
        'from_email' => 'certificados@verid4u.com',
        'from_name'  => 'VerID4U',
        'reply_to'   => '',
        'cron_key'   => '',      // segredo que autoriza as rotas de cron
    ],

    // Enquanto true, os registros semeados aparecem marcados como demonstração.
    'demo' => true,
];
