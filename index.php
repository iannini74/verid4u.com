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
    echo '</urlset>';
    exit;
}

// -------------------------------------------------------------------- API
if (($segments[0] ?? '') === 'api') {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Access-Control-Allow-Origin: *');

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

// --------------------------------------------------------------- páginas
if ($route === '') {
    security_headers();
    render('home', ['i18n' => $i18n, 'error' => null, 'typed' => '']);
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
