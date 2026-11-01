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
        if ($action === 'criar-conta') {
            if ($user !== null) {
                redirect($i18n->url('/app/'));
            }
            self::signup($i18n, $isPost);
            return;
        }
        if ($action === 'confirmar') {
            self::confirmEmail($i18n, (string) ($sub[1] ?? ''));
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
            'importar'     => self::importar($i18n, $user, $isPost, $sub),
            'email'        => self::emailSettings($i18n, $user, $isPost),
            'dobra'        => self::heroPanel($i18n, $user),
            'selo'         => self::sealPanel($i18n, $user, $isPost),
            'api'          => self::apiPanel($i18n, $user, $isPost),
            'reenviar'     => self::resendConfirmation($i18n, $user, $isPost),
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
                // O endereço público do emissor sai limpo quando dá, porque ele
                // vai em peça impressa e precisa ser ditável. Nunca muda depois:
                // trocar o endereço cegaria todo selo já instalado por aí.
                $slug = Issuer::slugify($old['org']);
                if (Db::one('SELECT id FROM issuers WHERE slug = ? LIMIT 1', [$slug]) !== null) {
                    $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
                }

                $pdo = Db::pdo();
                $pdo->beginTransaction();
                try {
                    Db::run('INSERT INTO tenants (name, slug, plan) VALUES (?, ?, ?)', [
                        $old['org'],
                        substr(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($old['org'])) ?: 'org', 0, 60) . '-' . bin2hex(random_bytes(2)),
                        'free',
                    ]);
                    $tenantId = (int) $pdo->lastInsertId();
                    Db::run(
                        'INSERT INTO issuers (tenant_id, name, legal_id, level, slug, category) VALUES (?, ?, ?, 0, ?, ?)',
                        [
                            $tenantId,
                            $old['org'],
                            $old['legal'] !== '' ? $old['legal'] : null,
                            $slug,
                            Brand::CATEGORY_DEFAULT,
                        ]
                    );
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

    /* --------------------------------------------------------------- selo */

    /**
     * A central do selo.
     *
     * O que o emissor escolhe aqui é o ramo — e só. O texto do selo não é
     * editável de propósito: selo com texto livre vira banner, e banner não
     * prova nada. O qualificador sai do ramo declarado; o resto sai do
     * estado do emissor no registro, que ele não controla.
     */
    private static function sealPanel(I18n $i18n, array $user, bool $isPost): void
    {
        $tenantId = (int) $user['tenant_id'];
        $issuerRow = Issuer::defaultIssuer($tenantId);

        if ($issuerRow === null) {
            self::shell($i18n, 'app/seal', ['issuer' => null, 'slug' => '', 'error' => null], $user, 'selo');
            return;
        }

        $issuerId = (int) $issuerRow['id'];
        $error = null;

        if ($isPost) {
            $acao = (string) ($_POST['acao'] ?? 'ramo');

            if ($acao === 'logo') {
                $r = Logo::store($issuerId, $_FILES['logo'] ?? []);
                if ($r['ok']) {
                    // A anterior só sai depois que a nova entrou. Apagar antes
                    // deixaria o emissor sem logo nenhuma se a gravação falhasse.
                    Logo::remove((string) ($issuerRow['logo_path'] ?? ''));
                    Db::run(
                        'UPDATE issuers SET logo_path = ?, logo_updated_at = NOW() WHERE id = ? AND tenant_id = ?',
                        [$r['file'], $issuerId, $tenantId]
                    );
                    Auth::audit('issuer.logo', 'issuer', (string) $issuerId);
                    redirect($i18n->url('/app/selo?ok=logo.saved'));
                    return;
                }
                $error = $i18n->a((string) $r['error'], [
                    'mb' => 1,
                    'px' => Logo::MIN_WIDTH,
                    'max' => Logo::MAX_SIDE,
                ]);
            } elseif ($acao === 'logo_remover') {
                Logo::remove((string) ($issuerRow['logo_path'] ?? ''));
                Db::run(
                    'UPDATE issuers SET logo_path = NULL, logo_updated_at = NULL WHERE id = ? AND tenant_id = ?',
                    [$issuerId, $tenantId]
                );
                Auth::audit('issuer.logo.removed', 'issuer', (string) $issuerId);
                redirect($i18n->url('/app/selo?ok=logo.removed'));
                return;
            } else {
                $ramo = (string) ($_POST['ramo'] ?? '');
                if (Issuer::setCategory($tenantId, $issuerId, $ramo)) {
                    Auth::audit('issuer.category', 'issuer', (string) $issuerId, ['ramo' => $ramo]);
                    redirect($i18n->url('/app/selo?ok=seal.saved'));
                    return;
                }
                $error = $i18n->a('seal.ramo.invalid');
            }
        }

        // O endereço público pode faltar em emissor criado antes desta tela.
        $slug = (string) ($issuerRow['slug'] ?? '');
        if ($slug === '') {
            $slug = Issuer::ensureSlug($issuerId, (string) $issuerRow['name']);
        }

        self::shell($i18n, 'app/seal', [
            'issuer' => Issuer::publicBySlug($slug),
            'slug'   => $slug,
            'logo'   => (string) ($issuerRow['logo_path'] ?? ''),
            'error'  => $error,
            'flash'  => self::flash($i18n),
        ], $user, 'selo');
    }

    /* ------------------------------------------------------ criar conta */

    /**
     * Cadastro pelo próprio cliente.
     *
     * Três campos e nenhum cartão. A conta nasce podendo entrar e configurar,
     * e sem poder emitir até confirmar o e-mail — deixar endereço não
     * confirmado pôr registro no cadastro público é abrir a porta para quem
     * quer sujá-lo.
     */
    private static function signup(I18n $i18n, bool $isPost): void
    {
        $old = ['org' => '', 'name' => '', 'email' => ''];
        $error = null;

        if ($isPost) {
            $old = [
                'org'   => trim((string) ($_POST['org'] ?? '')),
                'name'  => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
            ];
            $senha = (string) ($_POST['password'] ?? '');
            $senha2 = (string) ($_POST['password2'] ?? '');

            if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
                $error = $i18n->a('login.csrf');
            } elseif (empty($_POST['aceite'])) {
                $error = $i18n->a('signup.need_terms');
            } elseif (!hash_equals($senha, $senha2)) {
                $error = $i18n->a('activate.mismatch');
            } elseif (rate_limit_exceeded('signup', 5, 3600)) {
                $error = $i18n->a('signup.too_many');
            } else {
                $r = Onboarding::create($old + ['password' => $senha]);
                if ($r['ok']) {
                    $userId = (int) $r['user_id'];
                    Auth::startSession($userId);
                    Auth::audit('tenant.signup', 'user', (string) $userId);
                    $novo = Db::one('SELECT tenant_id FROM users WHERE id = ? LIMIT 1', [$userId]);
                    Onboarding::sendConfirmation(
                        (int) ($novo['tenant_id'] ?? 0),
                        $old['email'],
                        $old['org'],
                        (string) $r['token'],
                        $i18n->locale()
                    );
                    redirect($i18n->url('/app/?ok=signup.welcome'));
                    return;
                }
                $chave = (string) $r['error'];
                $error = strncmp($chave, 'error.', 6) === 0 ? $i18n->t($chave) : $i18n->a($chave);
            }
        }

        self::shell($i18n, 'app/signup', ['error' => $error, 'old' => $old], null, '');
    }

    private static function confirmEmail(I18n $i18n, string $token): void
    {
        $user = Onboarding::confirm($token);
        if ($user === null) {
            self::shell($i18n, 'app/login', ['error' => $i18n->a('signup.bad_token')], null, '');
            return;
        }
        Auth::audit('user.confirmed', 'user', (string) $user['id']);

        // Quem clica no link do e-mail pode estar em outro navegador. Se já há
        // sessão, segue direto; se não, a tela de entrada recebe o aviso bom.
        redirect($i18n->url(Auth::user() !== null ? '/app/?ok=signup.confirmed' : '/app/entrar?ok=signup.confirmed'));
    }

    private static function resendConfirmation(I18n $i18n, array $user, bool $isPost): void
    {
        if (!$isPost || Onboarding::emailConfirmed($user)) {
            redirect($i18n->url('/app/'));
            return;
        }
        if (rate_limit_exceeded('resend', 3, 900)) {
            redirect($i18n->url('/app/?ok=signup.resend_wait'));
            return;
        }
        $email = (string) Crypto::decrypt((string) $user['email_enc']);
        Onboarding::sendConfirmation(
            (int) $user['tenant_id'],
            $email,
            (string) $user['name'],
            Onboarding::mintConfirmToken((int) $user['id']),
            $i18n->locale()
        );
        redirect($i18n->url('/app/?ok=signup.resent'));
    }

    /* ---------------------------------------------------------------- API */

    /**
     * Chaves da API.
     *
     * A chave aparece uma vez e some. Guardamos o hash, não o texto — se
     * conseguíssemos mostrá-la de novo, quem tivesse acesso ao nosso banco
     * também conseguiria. O que a tela repete depois é só o prefixo, que
     * serve para o cliente saber qual chave é qual.
     */
    private static function apiPanel(I18n $i18n, array $user, bool $isPost): void
    {
        $tenantId = (int) $user['tenant_id'];
        $error = null;
        $nova = null;

        if ($isPost) {
            $acao = (string) ($_POST['acao'] ?? 'criar');

            if ($acao === 'revogar') {
                ApiKey::revoke($tenantId, (int) ($_POST['id'] ?? 0));
                Auth::audit('apikey.revoked', 'api_key', (string) ($_POST['id'] ?? 0));
                redirect($i18n->url('/app/api?ok=api.revoked'));
                return;
            }

            // A trava fica na escrita, não aqui. Chave é credencial; o que
            // decide se pode gravar é o estado da conta no momento em que a
            // gravação acontece. Travar a criação daria a falsa impressão de
            // que, tendo a chave, está liberado — e a conta pode esgotar a
            // faixa ou perder a confirmação depois de a chave existir.
            if (count(ApiKey::forTenant($tenantId)) >= 10) {
                $error = $i18n->a('api.too_many');
            } else {
                $nova = ApiKey::create($tenantId, (string) ($_POST['name'] ?? ''));
                Auth::audit('apikey.created', 'api_key', (string) $nova['id']);
            }
        }

        self::shell($i18n, 'app/api', [
            'keys'  => ApiKey::forTenant($tenantId),
            'nova'  => $nova,
            'error' => $error,
            'flash' => self::flash($i18n),
        ], $user, 'api');
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
            'plano' => Plan::summary($tenantId),
            'pendente' => !Onboarding::emailConfirmed($user),
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
        // E-mail não confirmado ou faixa esgotada barram a gravação, nunca a
        // leitura: o cliente continua vendo o que já tem. A mensagem diz qual
        // das duas é — sem isso ele conserta a errada.
        $bloqueio = Onboarding::blockReason($user);
        if ($bloqueio !== null && $isPost) {
            $isPost = false;
            $error = strncmp($bloqueio, 'plan.', 5) === 0
                ? $i18n->a($bloqueio, ['n' => Plan::FREE_MONTHLY])
                : $i18n->a($bloqueio);
        }

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
                    $old['locale'] = $i18n->locale();
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
        // E-mail não confirmado ou faixa esgotada barram a gravação, nunca a
        // leitura: o cliente continua vendo o que já tem. A mensagem diz qual
        // das duas é — sem isso ele conserta a errada.
        $bloqueio = Onboarding::blockReason($user);
        if ($bloqueio !== null && $isPost) {
            $isPost = false;
            $error = strncmp($bloqueio, 'plan.', 5) === 0
                ? $i18n->a($bloqueio, ['n' => Plan::FREE_MONTHLY])
                : $i18n->a($bloqueio);
        }

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
                    $input['locale'] = $i18n->locale();
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

    /**
     * Importação em lote, em três passos. O passo do meio existe para que
     * ninguém grave nada sem ver antes, linha a linha, o que vai acontecer.
     */
    private static function importar(I18n $i18n, array $user, bool $isPost, array $sub): void
    {
        $tenantId = (int) $user['tenant_id'];
        $programs = Issuer::programs($tenantId);
        $error = null;
        // E-mail não confirmado ou faixa esgotada barram a gravação, nunca a
        // leitura: o cliente continua vendo o que já tem. A mensagem diz qual
        // das duas é — sem isso ele conserta a errada.
        $bloqueio = Onboarding::blockReason($user);
        if ($bloqueio !== null && $isPost) {
            $isPost = false;
            $error = strncmp($bloqueio, 'plan.', 5) === 0
                ? $i18n->a($bloqueio, ['n' => Plan::FREE_MONTHLY])
                : $i18n->a($bloqueio);
        }

        $resultado = null;

        // Relatório de erros em CSV
        if (($sub[1] ?? '') === 'erros') {
            self::relatorioDeErros($tenantId, (int) ($sub[2] ?? 0));
            return;
        }

        $batchId = (int) ($_GET['lote'] ?? $_POST['lote'] ?? 0);
        $batch = $batchId > 0 ? Importer::batch($tenantId, $batchId) : null;

        if ($isPost) {
            $acao = (string) ($_POST['acao'] ?? 'enviar');

            if ($acao === 'enviar') {
                $mode = ($_POST['mode'] ?? 'register') === 'issue' ? 'issue' : 'register';
                $programId = (int) ($_POST['program_id'] ?? 0);
                $file = $_FILES['arquivo'] ?? null;

                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $error = $i18n->a('imp.no_file');
                } elseif ((int) $file['size'] > Importer::MAX_BYTES) {
                    $error = $i18n->a('imp.too_big');
                } elseif ($mode === 'issue' && $programId === 0) {
                    $error = $i18n->a('issue.bad_program');
                } else {
                    try {
                        $parsed = Importer::parse((string) $file['tmp_name']);
                        if ($parsed['rows'] === []) {
                            $error = $i18n->a('imp.no_rows');
                        } else {
                            $novo = Importer::createBatch(
                                $tenantId, (int) $user['id'], $mode,
                                $mode === 'issue' ? $programId : null,
                                (string) $file['name'], $parsed
                            );
                            Importer::saveMapping($novo, Importer::guessMapping($parsed['header'], $mode));
                            Auth::audit('import.parsed', 'import_batch', (string) $novo, ['rows' => count($parsed['rows'])]);
                            redirect($i18n->url('/app/importar') . '?lote=' . $novo);
                            return;
                        }
                    } catch (Throwable $e) {
                        error_log('[verid4u] importacao: ' . $e->getMessage());
                        $error = $i18n->a('imp.unreadable');
                    }
                }
            } elseif ($batch !== null && $batch['status'] === 'parsed') {
                $mapping = [];
                $enviado = is_array($_POST['map'] ?? null) ? $_POST['map'] : [];
                foreach (array_keys(Importer::fields((string) $batch['mode'])) as $campo) {
                    $valor = $enviado[$campo] ?? '';
                    if ($valor !== '' && is_numeric($valor)) {
                        $mapping[$campo] = (int) $valor;
                    }
                }
                Importer::saveMapping((int) $batch['id'], $mapping);
                $batch['mapping'] = $mapping;

                if ($acao === 'descartar') {
                    Importer::discard($tenantId, (int) $batch['id']);
                    redirect($i18n->url('/app/importar'));
                    return;
                }
                if ($acao === 'gravar') {
                    $issuerRow = Issuer::defaultIssuer($tenantId);
                    $program = self::programaDoLote($programs, $batch);
                    if ((string) $batch['mode'] === 'issue' && $program === null) {
                        $error = $i18n->a('imp.program_gone');
                    } elseif ($issuerRow === null) {
                        $error = $i18n->t('error.unavailable');
                    } else {
                        try {
                            $resultado = Importer::commit($tenantId, $issuerRow, $batch, $batch['mapping'], $program);
                            $batch = Importer::batch($tenantId, (int) $batch['id']);
                        } catch (Throwable $e) {
                            error_log('[verid4u] importacao commit: ' . $e->getMessage());
                            $error = $i18n->t('error.unavailable');
                        }
                    }
                }
            }
        }

        // Um lote no modo «emitir» cujo curso foi apagado depois do envio não
        // tem como ser avaliado: avisa em vez de quebrar.
        if ($batch !== null && (string) $batch['mode'] === 'issue' && self::programaDoLote($programs, $batch) === null) {
            $error = $error ?? $i18n->a('imp.program_gone');
            $batch['mapping'] = [];
        }

        $previa = null;
        if ($batch !== null && $batch['status'] === 'parsed' && $batch['mapping'] !== []) {
            try {
                $previa = Importer::evaluate($tenantId, $batch, $batch['mapping'], self::programaDoLote($programs, $batch));
            } catch (Throwable $e) {
                error_log('[verid4u] importacao previa: ' . $e->getMessage());
            }
        }

        self::shell($i18n, 'app/import', [
            'programs' => $programs,
            'batch' => $batch,
            'previa' => $previa,
            'resultado' => $resultado,
            'error' => $error,
        ], $user, 'import');
    }

    /**
     * Estado do e-mail e envio de teste.
     *
     * Existe para que a configuração do provedor possa ser verificada sem
     * precisar emitir um certificado de verdade para alguém.
     */
    private static function emailSettings(I18n $i18n, array $user, bool $isPost): void
    {
        $tenantId = (int) $user['tenant_id'];
        $error = null;
        $flash = null;
        $transcript = [];

        if ($isPost) {
            $acao = (string) ($_POST['acao'] ?? '');
            if ($acao === 'teste') {
                $destino = trim((string) ($_POST['destino'] ?? ''));
                if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                    $error = $i18n->a('mail.bad_address');
                } else {
                    try {
                        $peca = Mailer::render('issuance_created', $i18n->locale(), [
                            'program'   => $i18n->a('mail.test_program'),
                            'issuer'    => $i18n->a('mail.test_issuer'),
                            'code'      => 'TEST-0000-0000',
                            'issued_on' => Mailer::formatDate(date('Y-m-d'), $i18n->locale()),
                            'validity'  => $i18n->a('mail.test_note'),
                            'url'       => base_url() . $i18n->url(),
                        ]);
                        $peca['subject'] = '[teste] ' . $peca['subject'];
                        Mailer::deliver($destino, '', $peca);
                        $flash = $i18n->a('mail.test_sent', ['driver' => (string) Config::get('mail.driver', 'log')]);
                        Auth::audit('mail.test', 'mail', null, ['driver' => (string) Config::get('mail.driver', 'log')]);
                    } catch (Throwable $e) {
                        $error = mb_substr($e->getMessage(), 0, 240);
                    }
                }
            } elseif ($acao === 'liberar') {
                $alvo = trim((string) ($_POST['email'] ?? ''));
                if (filter_var($alvo, FILTER_VALIDATE_EMAIL)) {
                    try {
                        Db::run('DELETE FROM mail_suppressions WHERE email_index = ?', [Crypto::blindIndex($alvo)]);
                        Auth::audit('mail.unsuppressed', 'mail', null, []);
                        $flash = $i18n->a('mail.released');
                    } catch (Throwable $e) {
                        $error = $i18n->t('error.unavailable');
                    }
                } else {
                    $error = $i18n->a('mail.bad_address');
                }
            }
        }

        self::shell($i18n, 'app/email', [
            'stats' => Mailer::stats($tenantId),
            'recent' => Mailer::recent($tenantId),
            'driver' => (string) Config::get('mail.driver', 'log'),
            'fromEmail' => (string) Config::get('mail.from_email', ''),
            'smtpHost' => (string) Config::get('mail.host', ''),
            'cronSet' => ((string) Config::get('mail.cron_key', '')) !== '',
            'flash' => $flash,
            'error' => $error,
        ], $user, 'email');
    }

    private static function heroPanel(I18n $i18n, array $user): void
    {
        self::shell($i18n, 'app/hero', [
            'variants' => Hero::variants($i18n->locale()),
            'perf'     => Hero::performance(),
            'atual'    => Hero::current($i18n->locale()),
        ], $user, 'dobra');
    }

    private static function programaDoLote(array $programs, array $batch): ?array
    {
        if ((string) $batch['mode'] !== 'issue') {
            return null;
        }
        foreach ($programs as $p) {
            if ((int) $p['id'] === (int) $batch['program_id']) {
                return $p;
            }
        }
        return null;
    }

    /** Devolve as linhas que não entraram, como planilha corrigível. */
    private static function relatorioDeErros(int $tenantId, int $batchId): void
    {
        $batch = Importer::batch($tenantId, $batchId);
        if ($batch === null) {
            http_response_code(404);
            return;
        }
        $rows = Db::all(
            "SELECT line_no, raw_json, outcome, reason FROM import_rows
             WHERE batch_id = ? AND outcome <> 'created' ORDER BY line_no ASC",
            [$batchId]
        );
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="erros-importacao-' . $batchId . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM, para o Excel abrir com acento certo
        fputcsv($out, array_merge(['linha', 'situacao', 'motivo'], $batch['header']), ';');
        foreach ($rows as $r) {
            $cells = json_decode((string) $r['raw_json'], true) ?: [];
            fputcsv($out, array_merge([$r['line_no'], $r['outcome'], $r['reason']], $cells), ';');
        }
        fclose($out);
        exit;
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
        Issuer::revoke((int) $user['tenant_id'], $id, $reason, $i18n->locale());
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
