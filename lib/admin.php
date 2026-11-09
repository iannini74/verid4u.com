<?php
declare(strict_types=1);

/**
 * Área administrativa da plataforma.
 *
 * Separada do painel do cliente, e com exigências diferentes. Quem entra aqui
 * vê a conta de todo mundo: isso pede segundo fator, e pede que toda decisão
 * que muda o que um cliente pode fazer fique registrada com quem decidiu e
 * com base em quê.
 *
 * O nível de verificação do emissor é o exemplo central. Ele é o que separa
 * «Registra no VerID4U» de «Emissor verificado» no selo que vai no site do
 * cliente — ou seja, é a nossa palavra. Nível não pode ser campo que alguém
 * edita: é decisão registrada. Sem isso vira opinião, e opinião não sustenta
 * um selo.
 */
final class Admin
{
    /** Por quanto tempo o segundo fator vale depois de conferido. */
    public const STEP_UP_HOURS = 8;

    public static function handle(array $sub): void
    {
        security_headers(true);
        $i18n = new I18n('pt-br');
        $acao = (string) ($sub[0] ?? '');
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $user = Auth::user();

        if ($user === null) {
            redirect('/pt-br/app/entrar');
            return;
        }
        if ($isPost && !Auth::checkCsrf($_POST['csrf'] ?? null)) {
            http_response_code(400);
            redirect('/admin');
            return;
        }

        // Promoção do primeiro administrador. Exige o segredo do config, que
        // só existe no servidor — é o mesmo portão da ativação original, e o
        // único jeito de nascer um administrador sem já haver um.
        if ($acao === 'promover') {
            self::promover($i18n, $user, $isPost);
            return;
        }

        if ((string) ($user['role'] ?? '') !== 'platform') {
            self::render('admin/denied', ['i18n' => $i18n, 'user' => $user], $user, '');
            return;
        }

        // Matrícula no segundo fator, antes de qualquer outra coisa.
        if (empty($user['totp_confirmed_at']) || $acao === 'fator') {
            self::enrolar($i18n, $user, $isPost);
            return;
        }

        if (!self::steppedUp($user)) {
            self::stepUp($i18n, $user, $isPost);
            return;
        }

        match ($acao) {
            'clientes' => self::clientes($i18n, $user),
            'cliente'  => self::cliente($i18n, $user, (int) ($sub[1] ?? 0), $isPost),
            'fila'     => self::fila($i18n, $user, $isPost),
            'sair'     => self::sairDoFator($i18n),
            default    => self::visao($i18n, $user),
        };
    }

    /* ---------------------------------------------------------- entrada */

    private static function promover(I18n $i18n, array $user, bool $isPost): void
    {
        if ((string) ($user['role'] ?? '') === 'platform') {
            redirect('/admin');
            return;
        }
        $erro = null;

        if ($isPost) {
            $esperado = (string) Config::get('setup_token', '');
            $enviado = (string) ($_POST['token'] ?? '');
            if ($esperado === '' || !hash_equals($esperado, $enviado)) {
                $erro = 'Token inválido.';
                // Devagar de propósito: este portão não tem limite de
                // tentativas útil além do tempo, e o segredo é curto.
                usleep(400000);
            } else {
                Db::run("UPDATE users SET role = 'platform' WHERE id = ?", [(int) $user['id']]);
                Auth::audit('platform.promoted', 'user', (string) $user['id']);
                redirect('/admin');
                return;
            }
        }

        self::render('admin/promote', ['i18n' => $i18n, 'erro' => $erro], $user, '');
    }

    private static function enrolar(I18n $i18n, array $user, bool $isPost): void
    {
        $erro = null;
        $segredo = (string) ($user['totp_secret'] ?? '');

        // Segredo novo a cada visita enquanto não houver um confirmado: um
        // segredo gerado e abandonado não pode ficar válido para sempre.
        if ($segredo === '' || (empty($user['totp_confirmed_at']) && !$isPost)) {
            $segredo = Totp::secret();
            Db::run('UPDATE users SET totp_secret = ? WHERE id = ?', [$segredo, (int) $user['id']]);
        }

        if ($isPost) {
            if (!Totp::check($segredo, (string) ($_POST['codigo'] ?? ''))) {
                $erro = 'Código não confere. Veja se o relógio do celular está certo.';
            } else {
                Db::run('UPDATE users SET totp_confirmed_at = NOW() WHERE id = ?', [(int) $user['id']]);
                self::marcarPasso($user);
                Auth::audit('platform.totp_enrolled', 'user', (string) $user['id']);
                redirect('/admin');
                return;
            }
        }

        $conta = (string) Crypto::decrypt((string) $user['email_enc']);
        self::render('admin/totp', [
            'i18n'    => $i18n,
            'erro'    => $erro,
            'segredo' => $segredo,
            'uri'     => Totp::uri($segredo, $conta !== '' ? $conta : 'admin'),
        ], $user, '');
    }

    private static function stepUp(I18n $i18n, array $user, bool $isPost): void
    {
        $erro = null;
        if ($isPost) {
            if (rate_limit_exceeded('stepup-' . $user['id'], 8, 600)) {
                $erro = 'Tentativas demais. Espere alguns minutos.';
            } elseif (!Totp::check((string) $user['totp_secret'], (string) ($_POST['codigo'] ?? ''))) {
                $erro = 'Código não confere.';
                Auth::audit('platform.stepup_failed', 'user', (string) $user['id']);
            } else {
                self::marcarPasso($user);
                redirect('/admin');
                return;
            }
        }

        self::render('admin/stepup', ['i18n' => $i18n, 'erro' => $erro], $user, '');
    }

    private static function marcarPasso(array $user): void
    {
        if (!empty($user['session_id'])) {
            Db::run('UPDATE sessions SET stepped_up_at = NOW() WHERE id = ?', [(int) $user['session_id']]);
        }
    }

    private static function steppedUp(array $user): bool
    {
        if (empty($user['session_id'])) {
            return false;
        }
        $linha = Db::one(
            "SELECT stepped_up_at FROM sessions
              WHERE id = ? AND stepped_up_at IS NOT NULL
                AND stepped_up_at > NOW() - INTERVAL " . self::STEP_UP_HOURS . " HOUR LIMIT 1",
            [(int) $user['session_id']]
        );

        return $linha !== null;
    }

    private static function sairDoFator(I18n $i18n): void
    {
        $user = Auth::user();
        if ($user !== null && !empty($user['session_id'])) {
            Db::run('UPDATE sessions SET stepped_up_at = NULL WHERE id = ?', [(int) $user['session_id']]);
        }
        redirect('/pt-br/app/');
    }

    /* ------------------------------------------------------------ telas */

    private static function visao(I18n $i18n, array $user): void
    {
        self::render('admin/overview', [
            'i18n'  => $i18n,
            'nums'  => self::numeros(),
            'fila'  => self::pendentes(5),
            'mail'  => Mailer::stats(),
            'flash' => self::flash(),
        ], $user, 'visao');
    }

    private static function clientes(I18n $i18n, array $user): void
    {
        $comp = Plan::competencia();
        $linhas = Db::all(
            "SELECT t.id, t.name, t.slug, t.plan, t.status, t.created_via, t.created_at,
                    t.brand_enabled_at,
                    (SELECT COUNT(*) FROM issuances n WHERE n.tenant_id = t.id) AS total,
                    (SELECT COALESCE(issuances + registrations, 0) FROM usage_counters u
                      WHERE u.tenant_id = t.id AND u.competencia = ?) AS mes,
                    (SELECT MAX(level) FROM issuers e WHERE e.tenant_id = t.id) AS nivel,
                    (SELECT status FROM users x WHERE x.tenant_id = t.id AND x.role = 'owner'
                      ORDER BY x.id ASC LIMIT 1) AS dono_status
               FROM tenants t
              ORDER BY t.created_at DESC
              LIMIT 200",
            [$comp]
        );

        self::render('admin/tenants', [
            'i18n' => $i18n, 'linhas' => $linhas, 'comp' => $comp, 'flash' => self::flash(),
        ], $user, 'clientes');
    }

    private static function cliente(I18n $i18n, array $user, int $id, bool $isPost): void
    {
        $t = Db::one('SELECT * FROM tenants WHERE id = ? LIMIT 1', [$id]);
        if ($t === null) {
            http_response_code(404);
            redirect('/admin/clientes');
            return;
        }
        $erro = null;

        if ($isPost) {
            $acao = (string) ($_POST['acao'] ?? '');

            if ($acao === 'plano') {
                $novo = (string) ($_POST['plano'] ?? '');
                $valido = $novo === 'unlimited' || Catalog::plan($novo) !== null;
                if (!$valido) {
                    $erro = 'Plano desconhecido.';
                } else {
                    Db::run('UPDATE tenants SET plan = ? WHERE id = ?', [$novo, $id]);
                    Auth::audit('platform.plan', 'tenant', (string) $id, ['plano' => $novo]);
                    redirect('/admin/cliente/' . $id . '?ok=plano');
                    return;
                }
            } elseif ($acao === 'suspender' || $acao === 'reativar') {
                $novo = $acao === 'suspender' ? 'suspended' : 'active';
                Db::run('UPDATE tenants SET status = ? WHERE id = ?', [$novo, $id]);
                Auth::audit('platform.status', 'tenant', (string) $id, ['status' => $novo]);
                redirect('/admin/cliente/' . $id . '?ok=status');
                return;
            } elseif ($acao === 'apagar') {
                // Só conta sem certificado nenhum. O que está no registro não
                // se apaga por aqui: quem recebeu o certificado não tem nada a
                // ver com a conta de quem emitiu, e apagar o registro seria
                // quebrar a promessa que está escrita na página de preços.
                $n = Db::one('SELECT COUNT(*) AS n FROM issuances WHERE tenant_id = ?', [$id]);
                $confere = trim((string) ($_POST['confirma'] ?? ''));
                if ((int) ($n['n'] ?? 0) > 0) {
                    $erro = 'Esta conta tem certificado no registro. Contas com registro não se apagam daqui.';
                } elseif ($confere !== (string) $t['name']) {
                    $erro = 'Para apagar, escreva o nome da conta exatamente como aparece acima.';
                } else {
                    Auth::audit('platform.tenant_deleted', 'tenant', (string) $id, ['nome' => $t['name']]);
                    Db::run('DELETE FROM tenants WHERE id = ?', [$id]);
                    redirect('/admin/clientes?ok=apagado');
                    return;
                }
            }
        }

        self::render('admin/tenant', [
            'i18n'      => $i18n,
            't'         => $t,
            'erro'      => $erro,
            'emissores' => Db::all('SELECT * FROM issuers WHERE tenant_id = ? ORDER BY id ASC', [$id]),
            'usuarios'  => Db::all('SELECT id, name, role, status, last_login_at FROM users WHERE tenant_id = ? ORDER BY id ASC', [$id]),
            'uso'       => Db::all('SELECT * FROM usage_counters WHERE tenant_id = ? ORDER BY competencia DESC LIMIT 6', [$id]),
            'stats'     => Issuer::stats($id),
            'planos'    => Catalog::plans(),
            'flash'     => self::flash(),
        ], $user, 'clientes');
    }

    private static function fila(I18n $i18n, array $user, bool $isPost): void
    {
        $erro = null;

        if ($isPost) {
            $issuerId = (int) ($_POST['issuer'] ?? 0);
            $nivel = (int) ($_POST['nivel'] ?? 0);
            $base = trim((string) ($_POST['base'] ?? ''));
            $decisao = (string) ($_POST['decisao'] ?? '');

            if ($issuerId === 0) {
                $erro = 'Emissor não informado.';
            } elseif ($base === '') {
                // Sem base não há decisão, há carimbo. O selo que isto libera
                // é a nossa palavra; ela precisa ter em que se apoiar.
                $erro = 'Escreva com base em quê. Nível sem base registrada é opinião.';
            } elseif ($decisao === 'aprovar' && ($nivel < 1 || $nivel > 3)) {
                $erro = 'Nível fora da faixa.';
            } else {
                $aprovado = $decisao === 'aprovar';
                Db::run(
                    'UPDATE issuers SET level = ?, review_status = ?, level_basis = ?,
                            level_set_by = ?, level_set_at = NOW()
                      WHERE id = ?',
                    [
                        $aprovado ? $nivel : 0,
                        $aprovado ? 'approved' : 'rejected',
                        mb_substr($base, 0, 190),
                        (int) $user['id'],
                        $issuerId,
                    ]
                );
                Auth::audit($aprovado ? 'platform.issuer_approved' : 'platform.issuer_rejected',
                    'issuer', (string) $issuerId, ['nivel' => $aprovado ? $nivel : 0, 'base' => $base]);
                redirect('/admin/fila?ok=decidido');
                return;
            }
        }

        self::render('admin/queue', [
            'i18n' => $i18n, 'erro' => $erro, 'fila' => self::pendentes(50),
            'recentes' => Db::all(
                "SELECT e.id, e.name, e.level, e.level_basis, e.level_set_at, t.name AS tenant
                   FROM issuers e JOIN tenants t ON t.id = e.tenant_id
                  WHERE e.level_set_at IS NOT NULL
                  ORDER BY e.level_set_at DESC LIMIT 12"
            ),
            'flash' => self::flash(),
        ], $user, 'fila');
    }

    /* ----------------------------------------------------------- apoio */

    /** @return list<array<string,mixed>> */
    private static function pendentes(int $limite): array
    {
        // LIMIT com parâmetro quebra: o PDO roda com prepare nativo
        // (ATTR_EMULATE_PREPARES => false) e manda o valor como texto, e o
        // MySQL não aceita LIMIT '5'. O número entra interpolado, preso
        // entre 1 e 200 — é inteiro de origem, não vem de fora.
        return Db::all(
            "SELECT e.id, e.name, e.slug, e.legal_id, e.category, e.level,
                    e.review_requested_at, e.review_note,
                    t.id AS tenant_id, t.name AS tenant, t.created_at,
                    (SELECT COUNT(*) FROM issuances n WHERE n.issuer_id = e.id) AS emissoes
               FROM issuers e
               JOIN tenants t ON t.id = e.tenant_id
              WHERE e.review_status = 'pending'
              ORDER BY e.review_requested_at ASC
              LIMIT " . max(1, min(200, $limite))
        );
    }

    /** @return array<string,int> */
    private static function numeros(): array
    {
        $comp = Plan::competencia();
        $u = static fn (string $sql, array $p = []): int => (int) (Db::one($sql, $p)['n'] ?? 0);

        return [
            'tenants'    => $u('SELECT COUNT(*) AS n FROM tenants'),
            'ativos'     => $u("SELECT COUNT(DISTINCT tenant_id) AS n FROM usage_counters WHERE competencia = ?", [$comp]),
            'issuances'  => $u('SELECT COUNT(*) AS n FROM issuances'),
            'mes'        => $u('SELECT COALESCE(SUM(issuances + registrations), 0) AS n FROM usage_counters WHERE competencia = ?', [$comp]),
            'verif30'    => $u('SELECT COUNT(*) AS n FROM verification_events WHERE created_at > NOW() - INTERVAL 30 DAY'),
            'fila'       => $u("SELECT COUNT(*) AS n FROM issuers WHERE review_status = 'pending'"),
            'pendentes'  => $u("SELECT COUNT(*) AS n FROM users WHERE status = 'pending'"),
            'chaves'     => $u('SELECT COUNT(*) AS n FROM api_keys WHERE revoked_at IS NULL'),
        ];
    }

    private static function flash(): ?string
    {
        $chave = $_GET['ok'] ?? null;
        $mapa = [
            'plano'    => 'Plano alterado.',
            'status'   => 'Situação da conta alterada.',
            'apagado'  => 'Conta apagada.',
            'decidido' => 'Decisão registrada.',
        ];

        return is_string($chave) ? ($mapa[$chave] ?? null) : null;
    }

    private static function render(string $view, array $dados, array $user, string $atual): void
    {
        render($view, $dados + ['user' => $user, 'atual' => $atual], 'admin/layout');
    }
}
