<?php
declare(strict_types=1);

const APP_NAME = 'VerID4U';
const APP_HOST = 'verid4u.com';
const APP_TZ = 'America/Sao_Paulo';

/**
 * Versão dos arquivos estáticos, para quebrar cache do navegador.
 *
 * Ficava escrita à mão em três layouts, e foi assim que o app.js ficou preso
 * duas versões atrás enquanto o CSS andava: um sed pegou o style.css e não
 * pegou o script. Agora é um número só, num lugar só.
 */
const ASSET_VERSION = '23';

date_default_timezone_set(APP_TZ);
mb_internal_encoding('UTF-8');

require __DIR__ . '/config.php';
require __DIR__ . '/crypto.php';
require __DIR__ . '/db.php';
require __DIR__ . '/code.php';
require __DIR__ . '/store.php';
require __DIR__ . '/brand.php';
require __DIR__ . '/logo.php';
require __DIR__ . '/receipt.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/plan.php';
require __DIR__ . '/issuer.php';
require __DIR__ . '/onboarding.php';
require __DIR__ . '/apikey.php';
require __DIR__ . '/api.php';
require __DIR__ . '/import.php';
require __DIR__ . '/hero.php';
require __DIR__ . '/legal.php';
require __DIR__ . '/blog.php';
require __DIR__ . '/smtp.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/panel.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/i18n.php';

/** Escape para HTML. Usada em absolutamente toda saída. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? APP_HOST;
    return $scheme . '://' . $host;
}

function security_headers(bool $noindex = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
    );
    if ($noindex) {
        // Decisão de projeto: páginas de verificação carregam dados pessoais
        // e são quase idênticas entre si. Não entram em buscador.
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
}

function client_fingerprint(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (is_string($ip) && str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return hash('sha256', (string) $ip . '|verid4u');
}

/**
 * Limite de taxa simples, por janela deslizante em arquivo.
 * Defesa contra enumeração da base pela página de verificação.
 * Falha para o lado permissivo: nunca pode derrubar a página.
 */
function rate_limit_exceeded(string $bucket, int $max, int $windowSeconds): bool
{
    try {
        $dir = sys_get_temp_dir() . '/verid4u-rl';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }
        $file = $dir . '/' . hash('sha256', $bucket . '|' . client_fingerprint());
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $hits = $decoded;
                }
            }
        }
        $hits = array_values(array_filter(
            $hits,
            static fn ($t): bool => is_int($t) && $t > $now - $windowSeconds
        ));
        if (count($hits) >= $max) {
            return true;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
        return false;
    } catch (Throwable) {
        return false;
    }
}

function redirect(string $location, int $status = 302): void
{
    if (!headers_sent()) {
        header('Location: ' . $location, true, $status);
    }
    exit;
}

/** Caminho equivalente desta mesma página em outro idioma. */
function localized_path(string $locale): string
{
    $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($rawPath) ? rawurldecode($rawPath) : '/';
    $segments = array_values(array_filter(
        explode('/', trim($path, '/')),
        static fn (string $s): bool => $s !== ''
    ));
    if (isset($segments[0]) && I18n::isLocale(strtolower($segments[0]))) {
        array_shift($segments);
    }
    return '/' . $locale . '/' . implode('/', $segments);
}

function render(string $view, array $data = [], string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require __DIR__ . '/../views/' . $layout . '.php';
}

/**
 * Esquema em dia. Depois da primeira execução isto custa a leitura de um
 * arquivo marcador — nenhuma consulta extra no caminho da verificação.
 * Falha aqui nunca derruba a landing: só a verificação depende do banco.
 */
try {
    Db::migrateIfNeeded();
} catch (Throwable $e) {
    error_log('[verid4u] migração: ' . $e->getMessage());
}
