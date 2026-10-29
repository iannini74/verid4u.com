<?php
declare(strict_types=1);

/** Área do emissor: entrada, ativação, cursos, emissão e revogação. */
final class Panel
{
    public static function handle(I18n $i18n, array $sub): void
    {
        security_headers(true);
        $action = $sub[0] ?? '';
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $user = Auth::user();

        if ($action === 'ativar') {
            self::activate($i18n, $isPost);
            return;
        }
        if ($action === 'entrar') {
            if ($user !== null) {
                redirect($i18n->url('/app/'));
            }
            self::login($i18n, $isPost);
            return;
        }
        if ($user === null) {
            redirect($i18n->url(Auth::ownerExists() ? '/app/entrar' : '/app/ativar'));
            return;
        }
        if ($isPost && !Auth::checkCsrf($_POST['csrf'] ?? null)) {
            http_response_code(400);
            redirect($i18n->url('/app/'));
            return;
        }

        match ($action) {
            'sair'         => self::logout($i18n),
            'cursos'       => self::programs($i18n, $user, $isPost),
            'emitir'       => self::issue($i18n, $user, $isPost),
            'registrar'    => self::registerExternal($i18n, $user, $isPost),
            'certificados' => self::issuances($i18n, $user),
            'revogar'      => self::revoke($i18n, $user, $isPost),
            'certificado'  => self::certificate($i18n, $user, (string) ($sub[1] ?? '')),
            default        => self::dashboard($i18n, $user),
        };
    }

    private static function flash(I18n $i18n): ?string
    {
        $key = $_GET['ok'] ?? null;
        return is_string($key) && $key !== '' && preg_match('/^[a-z.]+$/', $key) === 1
            ? $i18n->a($key)
            : null;
    }

    private static function shell(I18n $i18n, string $view, array $data, ?array $user, string $current): void
    {
        render($view, $data + ['i18n' => $i18n, 'user' => $user, 'current' => $current], 'app/layout');
    }

    /* ---------------------------------------------------------- ativação */

    private static function activate(I18n $i18n, bool $isPost): void
    {
        if (Auth::ownerExists()) {
            self::shell($i18n, 'app/login', ['error' => $i18n->a('activate.closed')], null, '');
            return;
        }
        $expected = (string) Config::get('setup_token', '');
        $old = [];
        $error = null;

        if ($isPost) {
            $old = [
                'org' => trim((string) ($_POST['org'] ?? '')),
                'legal' => trim((string) ($_POST['legal'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
            ];
            $token = (string) ($_POST['token'] ?? '');
            $pass = (string) ($_POST['password'] ?? '');
            $pass2 = (string) ($_POST['password2'] ?? '');

            if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
                $error = $i18n->a('login.csrf');
            } elseif ($expected === '' || !hash_equals($expected, $token)) {
                $error = $i18n->a('activate.bad_token');
            } elseif ($old['org'] === '') {
                $error = $i18n->a('activate.bad_org');
            } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
                $error = $i18n->a('activate.bad_email');
            } elseif (mb_strlen($pass) < 12) {
                $error = $i18n->a('activate.weak');
            } elseif (!hash_equals($pass, $pass2)) {
                $error = $i18n->a('activate.mismatch');
            } else {
                $pdo = Db::pdo();
                $pdo->beginTransaction();
                try {
                    Db::run('INSERT INTO tenants (name, slug, plan) VALUES (?, ?, ?)', [
                        $old['org'],
                        substr(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($old['org'])) ?: 'org', 0, 60) . '-' . bin2hex(random_bytes(2)),
                        'free',
                    ]);
                    $tenantId = (int) $pdo->lastInsertId();
                    Db::run('INSERT INTO issuers (tenant_id, name, legal_id, level) VALUES (?, ?, ?, 0)', [
                        $tenantId, $old['org'], $old['legal'] !== '' ? $old['legal'] : null,
                    ]);
                    Db::run(
                        'INSERT INTO users (tenant_id, name, email_enc, email_index, role, password_hash) VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            $tenantId,
                            $old['name'] !== '' ? $old['name'] : $old['org'],
                            Crypto::encrypt($old['email']),
                            Crypto::blindIndex($old['email']),
                            'owner',
                            Auth::hash($pass),
                        ]
                    );
                    $userId = (int) $pdo->lastInsertId();
                    $pdo->commit();
                    Auth::startSession($userId);
                    Auth::audit('owner.activated', 'user', (string) $userId);
                    redirect($i18n->url('/app/'));
                    return;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('[verid4u] ativacao: ' . $e->getMessage());
                    $error = $i18n->t('error.unavailable');
                }
            }
        }
        self::shell($i18n, 'app/activate', ['error' => $error, 'old' => $old], null, '');
    }

    /* ------------------------------------------------------------ entrada */

    private static function login(I18n $i18n, bool $isPost): void
    {
        $error = null;
        $email = '';
        if ($isPost) {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
                $error = $i18n->a('login.csrf');
            } elseif (rate_limit_exceeded('login', 10, 600)) {
                $error = $i18n->t('error.throttled');
            } elseif (Auth::attempt($email, $password) !== null) {
                Auth::audit('user.login');
                redirect($i18n->url('/app/'));
                return;
            } else {
                $error = $i18n->a('login.failed');
            }
        }
        self::shell($i18n, 'app/login', ['error' => $error, 'email' => $email], null, '');
    }

    private static function logout(I18n $i18n): void
    {
        Auth::audit('user.logout');
        Auth::logout();
        redirect($i18n->url());
    }

    /* ------------------------------------------------------------- telas */

    private static function dashboard(I18n $i18n, array $user): void
    {
        $tenantId = (int) $user['tenant_id'];
        self::shell($i18n, 'app/dashboard', [
            'stats' => Issuer::stats($tenantId),
            'expiring' => Issuer::expiring($tenantId),
            'flash' => self::flash($i18n),
        ], $user, 'dashboard');
    }

    private static function programs(I18n $i18n, array $user, bool $isPost): void
    {
        $tenantId = (int) $user['tenant_id'];
        $error = null;
        $old = [];

        if ($isPost) {
            $old = [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'norm' => trim((string) ($_POST['norm'] ?? '')),
                'hours' => max(0, (int) ($_POST['hours'] ?? 0)),
                'modality' => in_array($_POST['modality'] ?? '', ['presencial', 'ead', 'hibrido'], true) ? (string) $_POST['modality'] : 'presencial',
                'assessed' => !empty($_POST['assessed']),
                'validity_months' => max(0, (int) ($_POST['validity_months'] ?? 0)),
            ];
            if ($old['title'] === '') {
                $error = $i18n->a('prog.bad_title');
            } else {
                $issuer = Issuer::defaultIssuer($tenantId);
                if ($issuer !== null) {
                    Issuer::createProgram($tenantId, (int) $issuer['id'], $old);
                    Auth::audit('program.created', 'program', null, ['title' => $old['title']]);
                    redirect($i18n->url('/app/cursos') . '?ok=prog.created');
                    return;
                }
                $error = $i18n->t('error.unavailable');
            }
        }

        self::shell($i18n, 'app/programs', [
            'programs' => Issuer::programs($tenantId),
            'flash' => self::flash($i18n),
            'error' => $error,
            'old' => $old,
        ], $user, 'programs');
    }

    private static function issue(I18n $i18n, array $user, bool $isPost): void
    {
        $tenantId = (int) $user['tenant_id'];
        $programs = Issuer::programs($tenantId);
        $error = null;
        $issued = null;
        $old = [];

        if ($isPost) {
            $old = [
                'program_id' => (int) ($_POST['program_id'] ?? 0),
                'holder_name' => trim((string) ($_POST['holder_name'] ?? '')),
                'holder_email' => trim((string) ($_POST['holder_email'] ?? '')),
                'holder_display' => in_array($_POST['holder_display'] ?? '', ['partial', 'full', 'hidden'], true) ? (string) $_POST['holder_display'] : 'partial',
                'issued_on' => (string) ($_POST['issued_on'] ?? ''),
            ];
            $program = null;
            foreach ($programs as $p) {
                if ((int) $p['id'] === $old['program_id']) {
                    $program = $p;
                }
            }
            $dateOk = DateTimeImmutable::createFromFormat('!Y-m-d', $old['issued_on']) !== false;

            if ($program === null) {
                $error = $i18n->a('issue.bad_program');
            } elseif ($old['holder_name'] === '') {
                $error = $i18n->a('issue.bad_holder');
            } elseif (!$dateOk) {
                $error = $i18n->a('issue.bad_date');
            } else {
                $issuer = Issuer::defaultIssuer($tenantId);
                try {
                    if ($issuer === null) {
                        throw new RuntimeException('Nenhum emissor cadastrado para este tenant.');
                    }
                    $issued = Issuer::issue($tenantId, $program, $issuer, $old);
                    $old = [];
                } catch (Throwable $e) {
                    error_log('[verid4u] emissao: ' . $e->getMessage());
                    $error = $i18n->t('error.unavailable');
                }
            }
        }

        self::shell($i18n, 'app/issue', [
            'programs' => $programs,
            'error' => $error,
            'issued' => $issued,
            'old' => $old,
        ], $user, 'issue');
    }

    private static function registerExternal(I18n $i18n, array $user, bool $isPost): void
    {
        $tenantId = (int) $user['tenant_id'];
        $error = null;
        $registered = null;
        $old = [];

        if ($isPost) {
            $old = [
                'issuer_name' => trim((string) ($_POST['issuer_name'] ?? '')),
                'program_title' => trim((string) ($_POST['program_title'] ?? '')),
                'program_norm' => trim((string) ($_POST['program_norm'] ?? '')),
                'hours' => max(0, (int) ($_POST['hours'] ?? 0)),
                'modality' => in_array($_POST['modality'] ?? '', ['presencial', 'ead', 'hibrido'], true) ? (string) $_POST['modality'] : 'presencial',
                'assessed' => !empty($_POST['assessed']),
                'holder_name' => trim((string) ($_POST['holder_name'] ?? '')),
                'holder_email' => trim((string) ($_POST['holder_email'] ?? '')),
                'holder_display' => in_array($_POST['holder_display'] ?? '', ['partial', 'full', 'hidden'], true) ? (string) $_POST['holder_display'] : 'partial',
                'issued_on' => (string) ($_POST['issued_on'] ?? ''),
                'valid_until' => trim((string) ($_POST['valid_until'] ?? '')),
                'source_sha256' => strtolower(trim((string) ($_POST['source_sha256'] ?? ''))),
                'source_filename' => mb_substr(trim((string) ($_POST['source_filename'] ?? '')), 0, 190),
            ];

            $dateOk = DateTimeImmutable::createFromFormat('!Y-m-d', $old['issued_on']) !== false;
            $untilOk = $old['valid_until'] === ''
                || DateTimeImmutable::createFromFormat('!Y-m-d', $old['valid_until']) !== false;
            $hashOk = $old['source_sha256'] === '' || preg_match('/^[a-f0-9]{64}$/', $old['source_sha256']) === 1;

            if ($old['issuer_name'] === '') {
                $error = $i18n->a('reg.bad_issuer');
            } elseif ($old['program_title'] === '') {
                $error = $i18n->a('prog.bad_title');
            } elseif ($old['holder_name'] === '') {
                $error = $i18n->a('issue.bad_holder');
            } elseif (!$dateOk || !$untilOk) {
                $error = $i18n->a('issue.bad_date');
            } elseif (!$hashOk) {
                $error = $i18n->a('reg.bad_hash');
            } else {
                $issuerRow = Issuer::defaultIssuer($tenantId);
                try {
                    if ($issuerRow === null) {
                        throw new RuntimeException('Nenhum emissor cadastrado para este tenant.');
                    }
                    $input = $old;
                    $input['valid_until'] = $old['valid_until'] !== '' ? $old['valid_until'] : null;
                    $registered = Issuer::register($tenantId, $issuerRow, $input);
                    $old = [];
                } catch (Throwable $e) {
                    error_log('[verid4u] registro: ' . $e->getMessage());
                    $error = $i18n->t('error.unavailable');
                }
            }
        }

        self::shell($i18n, 'app/register', [
            'error' => $error,
            'registered' => $registered,
            'old' => $old,
        ], $user, 'register');
    }

    private static function issuances(I18n $i18n, array $user): void
    {
        self::shell($i18n, 'app/issuances', [
            'issuances' => Issuer::issuances((int) $user['tenant_id']),
            'flash' => self::flash($i18n),
        ], $user, 'issuances');
    }

    private static function revoke(I18n $i18n, array $user, bool $isPost): void
    {
        if (!$isPost) {
            redirect($i18n->url('/app/certificados'));
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '' || $id === 0) {
            self::shell($i18n, 'app/issuances', [
                'issuances' => Issuer::issuances((int) $user['tenant_id']),
                'error' => $i18n->a('list.revoke_bad'),
            ], $user, 'issuances');
            return;
        }
        Issuer::revoke((int) $user['tenant_id'], $id, $reason);
        redirect($i18n->url('/app/certificados') . '?ok=list.revoked_done');
    }

    private static function certificate(I18n $i18n, array $user, string $codeParam): void
    {
        $normalized = Code::normalize($codeParam);
        if (!Code::isValid($normalized)) {
            http_response_code(404);
            redirect($i18n->url('/app/certificados'));
            return;
        }
        $row = Issuer::byCode((int) $user['tenant_id'], Code::base($normalized));
        if ($row === null) {
            http_response_code(404);
            redirect($i18n->url('/app/certificados'));
            return;
        }
        $record = Store::find(Code::base($normalized));
        render('app/certificate', ['i18n' => $i18n, 'record' => $record], 'app/print');
    }
}
