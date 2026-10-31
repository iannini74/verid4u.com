<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($rawPath) ? rawurldecode($rawPath) : '/';
$segments = array_values(array_filter(
    explode('/', trim($path, '/')),
    static fn (string $s): bool => $s !== ''
));

// ---------------------------------------------------------------- sitemap
if (($segments[0] ?? '') === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $base = base_url();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach (array_keys(I18n::LOCALES) as $loc) {
        echo "  <url>\n    <loc>{$base}/{$loc}/</loc>\n";
        foreach (I18n::LOCALES as $alt => $meta) {
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$meta['tag']}\" href=\"{$base}/{$alt}/\"/>\n";
        }
        echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$base}/\"/>\n  </url>\n";
    }
    foreach (array_keys(I18n::LOCALES) as $loc) {
        foreach (Legal::DOCS as $doc) {
            echo "  <url>\n    <loc>{$base}/{$loc}/{$doc}</loc>\n  </url>\n";
        }
    }
    echo '</urlset>';
    exit;
}

// -------------------------------------------------------------------- API
if (($segments[0] ?? '') === 'api') {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Access-Control-Allow-Origin: *');

    // Rotinas agendadas. Protegidas por um segredo do config, nunca
    // alcançáveis por um visitante. Chamadas pelo cron da hospedagem.
    if (($segments[1] ?? '') === 'v1' && ($segments[2] ?? '') === 'cron') {
        // Se o config não carrega, não há segredo para comparar — e também
        // não há nada a vazar. Nesse caso o diagnóstico responde sem chave,
        // devolvendo só o erro de sintaxe, que é o que destrava a correção.
        $chave = '';
        $configQuebrado = null;
        try {
            $chave = (string) Config::get('mail.cron_key', '');
        } catch (Throwable $e) {
            $configQuebrado = mb_substr($e->getMessage(), 0, 300);
        }
        if ($configQuebrado !== null) {
            http_response_code(500);
            echo json_encode([
                'task' => 'diag',
                'config_quebrado' => true,
                'erro' => $configQuebrado,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        $enviada = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
        if ($chave === '' || !hash_equals($chave, $enviada)) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $tarefa = (string) ($segments[3] ?? '');
        try {
            if ($tarefa === 'mail') {
                echo json_encode(['task' => 'mail'] + Mailer::runQueue(40), JSON_UNESCAPED_UNICODE);
                exit;
            }
            // Invalida o cache de opcode da configuração. Necessário porque
            // uma edição feita direto no servidor pode continuar invisível
            // enquanto o PHP servir a versão compilada antiga.
            if ($tarefa === 'opcache') {
                $arquivo = __DIR__ . '/lib/config.local.php';
                $feito = ['invalidate' => false, 'reset' => false];
                if (function_exists('opcache_invalidate')) {
                    $feito['invalidate'] = (bool) @opcache_invalidate($arquivo, true);
                }
                if ($feito['invalidate'] === false && function_exists('opcache_reset')) {
                    $feito['reset'] = (bool) @opcache_reset();
                }
                clearstatcache(true, $arquivo);
                echo json_encode(['task' => 'opcache'] + $feito, JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Teste de ponta a ponta: enfileira e despacha.
            // Não aceita destinatário por parâmetro — manda sempre para o
            // e-mail do dono da conta. Assim esta rota não vira um canal de
            // envio para terceiros caso o segredo do cron vaze.
            if ($tarefa === 'mailtest') {
                $dono = Db::one("SELECT name, email_enc, tenant_id FROM users WHERE role = 'owner' ORDER BY id ASC LIMIT 1");
                if ($dono === null) {
                    http_response_code(409);
                    echo json_encode(['error' => 'sem_dono'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $para = Crypto::decrypt($dono['email_enc']) ?? '';
                if ($para === '') {
                    http_response_code(409);
                    echo json_encode(['error' => 'endereco_ilegivel'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $id = Mailer::queue(
                    'issuance_created',
                    $para,
                    (string) $dono['name'],
                    'pt-br',
                    [
                        'program'   => 'Teste de configuração',
                        'issuer'    => 'VerID4U',
                        'code'      => 'TEST-' . strtoupper(bin2hex(random_bytes(2))) . '-0000',
                        'issued_on' => (new I18n('pt-br'))->date(date('Y-m-d')),
                        'validity'  => 'Mensagem de teste: nenhum certificado foi emitido.',
                        'url'       => base_url() . '/pt-br/',
                    ],
                    (int) $dono['tenant_id']
                );
                $resultado = Mailer::runQueue(5);
                echo json_encode([
                    'task' => 'mailtest',
                    'enfileirado' => $id,
                    'destino_mascarado' => preg_replace('/(?<=.).(?=[^@]*@)/u', '*', $para),
                    'fila' => $resultado,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }

            if ($tarefa === 'diag') {
                // Estrutura do config e saúde do banco. Só nomes de chaves e
                // mensagens de erro — nenhum valor é devolvido.
                try {
                    $arquivo = __DIR__ . '/lib/config.local.php';
                    $carregado = is_file($arquivo) ? @include $arquivo : null;
                    $estrutura = [
                        'arquivo_existe' => is_file($arquivo),
                        'bytes'          => is_file($arquivo) ? filesize($arquivo) : 0,
                        'mtime'          => is_file($arquivo) ? date('c', (int) filemtime($arquivo)) : null,
                        'retorna_array'  => is_array($carregado),
                        'chaves_topo'    => is_array($carregado) ? implode(',', array_keys($carregado)) : '-',
                        'db_chaves'      => is_array($carregado) && isset($carregado['db']) && is_array($carregado['db'])
                                            ? implode(',', array_keys($carregado['db'])) : '(ausente)',
                        'mail_chaves'    => is_array($carregado) && isset($carregado['mail']) && is_array($carregado['mail'])
                                            ? implode(',', array_keys($carregado['mail'])) : '(ausente)',
                    ];
                    $db = ['ok' => false, 'erro' => null];
                    try {
                        Db::one('SELECT 1 AS t');
                        $db['ok'] = true;
                    } catch (Throwable $e) {
                        $db['erro'] = mb_substr($e->getMessage(), 0, 200);
                    }
                    echo json_encode([
                        'task'      => 'diag',
                        'estrutura' => $estrutura,
                        'banco'     => $db,
                        'mail'      => [
                            'driver'   => (string) Config::get('mail.driver', ''),
                            'host'     => (string) Config::get('mail.host', ''),
                            'user_set' => ((string) Config::get('mail.user', '')) !== '',
                            'pass_set' => ((string) Config::get('mail.pass', '')) !== '',
                            'fora_do_lugar' => Config::misplacedMailKeys(),
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } catch (Throwable $e) {
                    http_response_code(500);
                    echo json_encode(['task' => 'diag', 'fatal' => mb_substr($e->getMessage(), 0, 300)], JSON_UNESCAPED_UNICODE);
                }
                exit;
            }

            if ($tarefa === 'diag_v1') {
                // Diagnóstico do arquivo de configuração. Conta ocorrências e
                // testa presença de literais — NUNCA devolve valor nenhum, para
                // que a rota não vire um vazamento de credencial.
                $arquivo = __DIR__ . '/lib/config.local.php';
                $bruto = is_file($arquivo) ? (string) @file_get_contents($arquivo) : '';
                // Lê o MESMO caminho numa segunda vez, sem passar pelo Config,
                // para saber se o problema é o arquivo ou a camada que o lê.
                $fresco = is_file($arquivo) ? (require $arquivo) : [];
                $pistas = [
                    'caminho_diag'   => realpath($arquivo) ?: null,
                    'caminho_config' => realpath(__DIR__ . '/lib/') . '/config.local.php',
                    'fresco_driver'  => is_array($fresco) ? (string) ($fresco['mail']['driver'] ?? '(ausente)') : '(nao-array)',
                    'fresco_host'    => is_array($fresco) ? (((string) ($fresco['mail']['host'] ?? '')) !== '' ? 'preenchido' : 'vazio') : '-',
                    'fresco_chaves'  => is_array($fresco) ? implode(',', array_keys($fresco)) : '-',
                    'config_bytes'   => strlen($bruto),
                    'config_mtime'   => is_file($arquivo) ? date('c', (int) filemtime($arquivo)) : null,
                    'blocos_mail'    => substr_count($bruto, "'mail'"),
                    'ocorr_driver'   => preg_match_all('/[\'"]driver[\'"]\s*=>\s*[\'"]([a-z]+)[\'"]/i', $bruto, $mm) ? implode(',', $mm[1]) : '(nenhuma)',
                    'tem_driver_smtp'=> str_contains($bruto, "'driver' => 'smtp'") || str_contains($bruto, "'driver'=>'smtp'"),
                    'tem_resend'     => str_contains($bruto, 'smtp.resend.com'),
                    'opcache'        => function_exists('opcache_get_status'),
                    'chaves_fora_do_lugar' => Config::misplacedMailKeys(),
                ];
                echo json_encode(['task' => 'diag', 'arquivo' => $pistas] + [
                    'driver'     => (string) Config::get('mail.driver', ''),
                    'host'       => (string) Config::get('mail.host', ''),
                    'user_set'   => ((string) Config::get('mail.user', '')) !== '',
                    'pass_set'   => ((string) Config::get('mail.pass', '')) !== '',
                    'from_email' => (string) Config::get('mail.from_email', ''),
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            if ($tarefa === 'diag_antigo') {
                // Forma da configuração de e-mail, sem nenhum segredo:
                // diz se os campos estão preenchidos, nunca o que contêm.
                $host = (string) Config::get('mail.host', '');
                $user = (string) Config::get('mail.user', '');
                $pass = (string) Config::get('mail.pass', '');
                echo json_encode([
                    'task' => 'diag',
                    'driver' => (string) Config::get('mail.driver', 'log'),
                    'host' => $host,
                    'port' => (int) Config::get('mail.port', 0),
                    'secure' => (string) Config::get('mail.secure', ''),
                    'from_email' => (string) Config::get('mail.from_email', ''),
                    'user_set' => $user !== '',
                    'pass_set' => $pass !== '',
                    'ready' => (string) Config::get('mail.driver', 'log') === 'smtp' && $host !== '' && $pass !== '',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($tarefa === 'expiry') {
                echo json_encode(['task' => 'expiry'] + Mailer::scheduleExpiryWarnings(), JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (Throwable $e) {
            error_log('[verid4u] cron ' . $tarefa . ': ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(404);
        echo json_encode(['error' => 'unknown_task'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Saúde do serviço. Não devolve nenhum dado: só diz se o banco responde
    // e em que versão de esquema a aplicação está. Serve ao monitoramento e
    // à futura página de status.
    if (($segments[1] ?? '') === 'v1' && ($segments[2] ?? '') === 'health') {
        $schema = null;
        $ok = false;
        try {
            $row = Db::one('SELECT MAX(version) AS v FROM schema_migrations');
            $schema = (int) ($row['v'] ?? 0);
            $ok = $schema > 0;
        } catch (Throwable $e) {
            error_log('[verid4u] health: ' . $e->getMessage());
        }
        http_response_code($ok ? 200 : 503);
        echo json_encode([
            'status' => $ok ? 'ok' : 'degraded',
            'schema' => $schema,
            'expected' => Db::SCHEMA_VERSION,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Busca reversa: acha o registro pela impressão digital do arquivo.
    // O arquivo nunca é enviado; o navegador manda só o SHA-256.
    if (($segments[1] ?? '') === 'v1' && ($segments[2] ?? '') === 'lookup') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (rate_limit_exceeded('lookup', 40, 600)) {
            http_response_code(429);
            echo json_encode(['error' => 'rate_limited'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $sha = strtolower(trim((string) ($_POST['sha256'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_hash'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $found = Store::findBySourceHash($sha);
        } catch (Throwable $e) {
            error_log('[verid4u] lookup: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['error' => 'unavailable'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($found === null) {
            http_response_code(404);
            echo json_encode(['status' => 'not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        Store::recordVerification($found['base'], (string) $found['state'], (int) $found['id'], 'api', 'file');
        echo json_encode([
            'status' => 'found',
            'code' => Code::format($found['code']),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isVerifyRoute = ($segments[1] ?? '') === 'v1' && ($segments[2] ?? '') === 'verify';
    if (!$isVerifyRoute) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found', 'message' => 'Unknown endpoint.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (rate_limit_exceeded('api', 120, 600)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited', 'message' => 'Too many requests.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $normalized = Code::normalize((string) ($segments[3] ?? ''));
    if (!Code::isValid($normalized)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_code', 'message' => 'Malformed certificate code.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $record = Store::find(Code::base($normalized));
    } catch (Throwable $e) {
        error_log('[verid4u] api: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'unavailable', 'message' => 'Register temporarily unreachable.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    Store::recordVerification(
        Code::base($normalized),
        $record === null ? 'notfound' : (string) $record['state'],
        isset($record['id']) ? (int) $record['id'] : null,
        'api',
        'api'
    );
    if ($record === null) {
        http_response_code(404);
        echo json_encode([
            'code' => Code::format($normalized),
            'status' => 'not_found',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    echo json_encode([
        'code' => Code::format($record['code']),
        'status' => $record['state'],
        'demo' => (bool) $record['is_demo'],
        'holder' => Store::displayName($record),
        'program' => $record['program'],
        'norm' => $record['norm'],
        'hours' => $record['hours'],
        'modality' => $record['modality'],
        'assessed' => $record['assessed'],
        'origin' => $record['origin'],
        'issuer' => [
            'name' => $record['issuer_name'],
            'verification_level' => $record['issuer_level'],
            'registered_by' => $record['registered_by'],
        ],
        'source_sha256' => $record['source_sha256'],
        'issued_on' => $record['issued_on'],
        'valid_until' => $record['valid_until'],
        'revoked_on' => $record['revoked_on'],
        'days_left' => $record['days_left'],
        'sha256' => $record['hash'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ----------------------------------------------------------------- idioma
$localeInUrl = null;
if (isset($segments[0]) && I18n::isLocale(strtolower($segments[0]))) {
    $localeInUrl = strtolower($segments[0]);
    array_shift($segments);
}
$i18n = new I18n($localeInUrl ?? I18n::detect());

$route = $segments[0] ?? '';

// ------------------------------------------------------------ formulário
if ($route !== 'app' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    security_headers();
    $typed = (string) ($_POST['codigo'] ?? '');
    $normalized = Code::normalize($typed);

    if ($normalized === '') {
        http_response_code(400);
        render('home', ['i18n' => $i18n, 'error' => $i18n->t('error.empty'), 'typed' => $typed]);
        exit;
    }
    if (!Code::isValid($normalized)) {
        http_response_code(404);
        render('home', ['i18n' => $i18n, 'error' => $i18n->t('error.format'), 'typed' => $typed]);
        exit;
    }
    $daDobra = (string) ($_POST['hv'] ?? '');
    if ($daDobra !== '') {
        Hero::recordConversion($daDobra, $i18n->locale());
    }
    redirect($i18n->url('/v/' . Code::format($normalized)), 303);
}

// Sem idioma na URL: leva para a versão localizada, preservando o caminho.
if ($localeInUrl === null) {
    redirect('/' . $i18n->locale() . '/' . implode('/', $segments) . ($segments === [] ? '' : ''));
}

// --------------------------------------------- área do emissor
if ($route === 'app') {
    Panel::handle($i18n, array_slice($segments, 1));
    exit;
}

// ------------------------------------------------- documentos legais
if (in_array($route, Legal::DOCS, true)) {
    security_headers();
    $doc = Legal::doc($route, $i18n->locale());
    if ($doc === null) {
        http_response_code(404);
        render('home', ['i18n' => $i18n, 'error' => null, 'typed' => '']);
        exit;
    }
    render('legal', ['i18n' => $i18n, 'doc' => $doc, 'slug' => $route]);
    exit;
}

// --------------------------------------------------------------- páginas
if ($route === '') {
    security_headers();
    $hv = Hero::current($i18n->locale());
    if ($hv !== null) {
        Hero::recordImpression((string) ($hv['slug'] ?? ''), $i18n->locale());
    }
    render('home', ['i18n' => $i18n, 'error' => null, 'typed' => '', 'hv' => $hv]);
    exit;
}

if ($route === 'v') {
    security_headers(true);

    $normalized = Code::normalize((string) ($segments[1] ?? ''));
    $state = 'notfound';
    $record = null;

    if (!Code::isValid($normalized)) {
        http_response_code(404);
    } elseif (rate_limit_exceeded('verify', 60, 600)) {
        http_response_code(429);
        $state = 'throttled';
    } else {
        try {
            $record = Store::find(Code::base($normalized));
        } catch (Throwable $e) {
            error_log('[verid4u] verificacao: ' . $e->getMessage());
            http_response_code(503);
            $state = 'unavailable';
        }
        if ($state !== 'unavailable') {
            if ($record === null) {
                http_response_code(404);
            } else {
                $state = $record['state'];
            }
            Store::recordVerification(
                Code::base($normalized),
                $state,
                isset($record['id']) ? (int) $record['id'] : null,
                $i18n->locale()
            );
        }
    }

    render('verify', [
        'i18n' => $i18n,
        'record' => $record,
        'state' => $state,
        'typed' => $normalized === '' ? (string) ($segments[1] ?? '') : Code::format($normalized),
    ]);
    exit;
}

http_response_code(404);
security_headers();
render('home', ['i18n' => $i18n, 'error' => $i18n->t('error.format'), 'typed' => '']);
