<?php
declare(strict_types=1);

/**
 * A API de escrita.
 *
 * O ciclo que a finalidade pede — ler o certificado, validar, registrar e
 * devolver o código de registro — agora tem porta programável. Mas nada aqui
 * é exclusivo da API: toda função alcançável por chave existe no painel e na
 * importação. Integrar nunca pode ser condição para usar a plataforma.
 *
 * Três decisões que não são de gosto:
 *
 * 1. Idempotency-Key obrigatório em todo POST. Toda integração reenvia —
 *    timeout, retentativa, fila que roda duas vezes — e reenviar não pode
 *    gerar um segundo certificado para o mesmo aluno. É o pior defeito
 *    possível num registro, porque ninguém descobre na hora.
 *
 * 2. Erro em application/problem+json (RFC 9457), com um campo `code`
 *    estável. A mensagem muda e é traduzida; o `code` é o que a integração
 *    do outro lado pode comparar sem quebrar.
 *
 * 3. A faixa do plano barra escrita e nunca leitura. Esgotada, continua
 *    possível consultar tudo o que já existe — verificação é grátis e
 *    ilimitada para sempre, inclusive pela API.
 */
final class Api
{
    public const VERSION = 'v1';

    /** Quanto tempo a resposta gravada responde por uma repetição. */
    public const IDEM_TTL_DAYS = 2;

    /** @var array<string,mixed>|null cliente resolvido pela chave */
    private static ?array $cliente = null;

    /**
     * Tenta atender uma rota de escrita. Devolve false se a rota não é daqui.
     *
     * @param list<string> $segments
     */
    public static function handle(array $segments): bool
    {
        $recurso = (string) ($segments[2] ?? '');
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $rotas = ['programs', 'issuances', 'registrations', 'me'];
        if (!in_array($recurso, $rotas, true)) {
            return false;
        }

        // O bloco geral da API manda Access-Control-Allow-Origin: * , e para
        // leitura pública isso é o certo — qualquer página pode conferir um
        // certificado. Para escrita, não: liberar CORS convidaria a pôr a
        // chave em JavaScript de navegador, onde ela fica visível para quem
        // abrir o inspetor. Sem CORS, a chave precisa viver no servidor de
        // quem integra, que é o único lugar em que ela está segura.
        header_remove('Access-Control-Allow-Origin');

        self::$cliente = ApiKey::resolve(self::authHeader());
        if (self::$cliente === null) {
            self::problem(401, 'unauthorized', 'Chave ausente ou inválida.',
                'Envie o cabeçalho Authorization: Bearer vk_live_…');
        }

        $tenantId = (int) self::$cliente['tenant_id'];

        if (rate_limit_exceeded('apikey-' . self::$cliente['id'], ApiKey::RATE_PER_10MIN, 600)) {
            header('Retry-After: 60');
            self::problem(429, 'rate_limited', 'Chamadas demais.',
                'Limite de ' . ApiKey::RATE_PER_10MIN . ' chamadas por 10 minutos nesta chave.');
        }

        if ($recurso === 'me' && $metodo === 'GET') {
            self::me($tenantId);
        }
        if ($recurso === 'programs') {
            $metodo === 'GET' ? self::listPrograms($tenantId) : self::createProgram($tenantId);
        }
        if ($recurso === 'issuances' && ($segments[4] ?? '') === 'revoke') {
            self::revoke($tenantId, (string) ($segments[3] ?? ''));
        }
        if ($recurso === 'issuances' && $metodo === 'POST') {
            self::issue($tenantId);
        }
        if ($recurso === 'registrations' && $metodo === 'POST') {
            self::register($tenantId);
        }

        self::problem(405, 'method_not_allowed', 'Método não aceito neste endereço.', null);
    }

    /* ----------------------------------------------------------- endpoints */

    private static function me(int $tenantId): void
    {
        $plano = Plan::summary($tenantId);
        self::ok(200, [
            'tenant' => [
                'name' => self::$cliente['tenant_name'] ?? null,
                'id'   => $tenantId,
            ],
            'key' => ['name' => self::$cliente['name'] ?? null, 'prefix' => self::$cliente['prefix'] ?? null],
            'usage' => [
                'period'    => $plano['competencia'],
                'used'      => $plano['used'],
                'limit'     => $plano['unlimited'] ? null : $plano['limit'],
                'remaining' => $plano['unlimited'] ? null : $plano['remaining'],
            ],
            'brand_on_verification' => Plan::brandEligible($tenantId),
        ]);
    }

    private static function listPrograms(int $tenantId): void
    {
        $saida = [];
        foreach (Issuer::programs($tenantId) as $p) {
            $saida[] = [
                'id'               => (int) $p['id'],
                'title'            => $p['title'],
                'norm'             => $p['norm'],
                'hours'            => (int) $p['hours'],
                'modality'         => $p['modality'],
                'assessed'         => (bool) $p['assessed'],
                'validity_months'  => $p['validity_months'] === null ? null : (int) $p['validity_months'],
            ];
        }
        self::ok(200, ['data' => $saida]);
    }

    private static function createProgram(int $tenantId): void
    {
        $body = self::body();
        self::requireFields($body, ['title', 'hours']);

        $emissor = Issuer::defaultIssuer($tenantId);
        if ($emissor === null) {
            self::problem(409, 'no_issuer', 'Esta conta ainda não tem emissor configurado.', null);
        }

        self::idempotent('programs', $body, static function () use ($tenantId, $emissor, $body): array {
            $id = Issuer::createProgram($tenantId, (int) $emissor['id'], [
                'title'           => (string) $body['title'],
                'norm'            => isset($body['norm']) ? (string) $body['norm'] : '',
                'hours'           => (int) $body['hours'],
                'modality'        => self::modality($body['modality'] ?? 'presencial'),
                'assessed'        => !empty($body['assessed']),
                'validity_months' => isset($body['validity_months']) ? (string) (int) $body['validity_months'] : '',
            ]);

            return [201, ['id' => $id]];
        });
    }

    private static function issue(int $tenantId): void
    {
        $body = self::body();
        self::requireFields($body, ['program_id', 'holder_name']);
        self::guardWrite($tenantId);

        $emissor = Issuer::defaultIssuer($tenantId);
        $programa = Db::one(
            'SELECT * FROM programs WHERE id = ? AND tenant_id = ? AND archived_at IS NULL LIMIT 1',
            [(int) $body['program_id'], $tenantId]
        );
        if ($emissor === null || $programa === null) {
            self::problem(404, 'program_not_found', 'Curso não encontrado nesta conta.',
                'Liste os cursos em GET /api/' . self::VERSION . '/programs.');
        }

        self::idempotent('issuances', $body, static function () use ($tenantId, $programa, $emissor, $body): array {
            $r = Issuer::issue($tenantId, $programa, $emissor, [
                'holder_name'    => (string) $body['holder_name'],
                'holder_email'   => isset($body['holder_email']) ? (string) $body['holder_email'] : '',
                'holder_display' => ($body['holder_display'] ?? 'partial') === 'full' ? 'full' : 'partial',
                'issued_on'      => self::date($body['issued_on'] ?? null) ?? date('Y-m-d'),
            ]);

            return [201, self::issuanceOut($r)];
        });
    }

    private static function register(int $tenantId): void
    {
        $body = self::body();
        self::requireFields($body, ['holder_name', 'program_title', 'issued_on']);
        self::guardWrite($tenantId);

        $emissor = Issuer::defaultIssuer($tenantId);
        if ($emissor === null) {
            self::problem(409, 'no_issuer', 'Esta conta ainda não tem emissor configurado.', null);
        }

        $emitidoEm = self::date($body['issued_on'] ?? null);
        if ($emitidoEm === null) {
            self::problem(422, 'invalid_date', 'issued_on precisa ser uma data AAAA-MM-DD.', null);
        }

        self::idempotent('registrations', $body, static function () use ($tenantId, $emissor, $body, $emitidoEm): array {
            $r = Issuer::register($tenantId, $emissor, [
                'issuer_name'     => isset($body['issuer_name']) && trim((string) $body['issuer_name']) !== ''
                    ? (string) $body['issuer_name'] : (string) $emissor['name'],
                'program_title'   => (string) $body['program_title'],
                'program_norm'    => isset($body['norm']) ? (string) $body['norm'] : '',
                'hours'           => (int) ($body['hours'] ?? 0),
                'modality'        => self::modality($body['modality'] ?? 'presencial'),
                'assessed'        => !empty($body['assessed']),
                'holder_name'     => (string) $body['holder_name'],
                'holder_email'    => isset($body['holder_email']) ? (string) $body['holder_email'] : '',
                'holder_display'  => ($body['holder_display'] ?? 'partial') === 'full' ? 'full' : 'partial',
                'issued_on'       => $emitidoEm,
                'valid_until'     => self::date($body['valid_until'] ?? null),
                'source_sha256'   => self::sha256($body['source_sha256'] ?? null),
                'source_filename' => isset($body['source_filename']) ? (string) $body['source_filename'] : '',
            ]);

            return [201, self::issuanceOut($r)];
        });
    }

    private static function revoke(int $tenantId, string $codigo): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            self::problem(405, 'method_not_allowed', 'Use POST para revogar.', null);
        }
        $body = self::body(false);
        $motivo = trim((string) ($body['reason'] ?? ''));
        if ($motivo === '') {
            self::problem(422, 'missing_field', 'reason é obrigatório.',
                'Revogação sem motivo registrado não serve a quem consulta depois.');
        }

        $normal = Code::normalize($codigo);
        if (!Code::isValid($normal)) {
            self::problem(422, 'invalid_code', 'Código fora do formato.', null);
        }
        $linha = Issuer::byCode($tenantId, Code::base($normal));
        if ($linha === null) {
            self::problem(404, 'not_found', 'Este código não pertence a esta conta.', null);
        }

        self::idempotent('revoke', ['code' => $normal, 'reason' => $motivo], static function () use ($tenantId, $linha, $motivo, $normal): array {
            Issuer::revoke($tenantId, (int) $linha['id'], $motivo);

            return [200, ['code' => Code::format(Code::full(Code::base($normal))), 'status' => 'revoked']];
        });
    }

    /* ------------------------------------------------------- idempotência */

    /**
     * Executa uma escrita uma única vez por Idempotency-Key.
     *
     * Chave repetida com o mesmo corpo devolve a resposta gravada, com o
     * mesmo status. Chave repetida com corpo diferente é 409: significa que
     * duas coisas distintas saíram com a mesma etiqueta, e adivinhar qual
     * delas vale seria pior do que recusar.
     *
     * @param callable():array{0:int,1:array} $acao
     */
    private static function idempotent(string $endpoint, array $body, callable $acao): void
    {
        $chave = trim((string) (self::header('Idempotency-Key') ?? ''));
        if ($chave === '' || mb_strlen($chave) > 190) {
            self::problem(400, 'idempotency_key_required', 'Falta o cabeçalho Idempotency-Key.',
                'Use um valor único por operação, por exemplo um UUID. Reenviar com a mesma chave devolve a mesma resposta em vez de criar outro registro.');
        }

        $tenantId = (int) self::$cliente['tenant_id'];
        $assinatura = hash('sha256', $endpoint . '|' . json_encode($body, JSON_UNESCAPED_UNICODE));

        $ja = Db::one(
            'SELECT request_hash, status_code, response_json FROM api_idempotency
              WHERE tenant_id = ? AND idem_key = ? LIMIT 1',
            [$tenantId, $chave]
        );
        if ($ja !== null) {
            if (!hash_equals((string) $ja['request_hash'], $assinatura)) {
                self::problem(409, 'idempotency_key_reused',
                    'Esta Idempotency-Key já foi usada com outro conteúdo.',
                    'Use uma chave nova para uma operação nova.');
            }
            header('Idempotent-Replay: true');
            self::ok((int) $ja['status_code'], json_decode((string) $ja['response_json'], true) ?: []);
        }

        [$status, $saida] = $acao();

        // Gravado depois de a escrita dar certo. Gravar antes faria uma
        // retentativa receber de volta a confirmação de algo que falhou.
        try {
            Db::run(
                'INSERT INTO api_idempotency (tenant_id, idem_key, endpoint, request_hash, status_code, response_json)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$tenantId, $chave, $endpoint, $assinatura, $status, json_encode($saida, JSON_UNESCAPED_UNICODE)]
            );
        } catch (Throwable $e) {
            error_log('[verid4u] idempotencia: ' . $e->getMessage());
        }

        self::ok($status, $saida);
    }

    /* ------------------------------------------------------------ auxílio */

    /**
     * As duas condições para gravar no registro público, conferidas no
     * instante da gravação e não no da criação da chave.
     *
     * Separadas de propósito: e-mail não confirmado é defesa contra quem quer
     * sujar o registro, faixa esgotada é limite de plano. São problemas
     * diferentes, com códigos diferentes, e quem integra precisa distinguir —
     * um se resolve clicando num link, o outro não.
     */
    private static function guardWrite(int $tenantId): void
    {
        $dono = Db::one(
            "SELECT status FROM users WHERE tenant_id = ? AND role = 'owner' ORDER BY id ASC LIMIT 1",
            [$tenantId]
        );
        if ((string) ($dono['status'] ?? 'active') !== 'active') {
            self::problem(403, 'email_unconfirmed',
                'O e-mail desta conta ainda não foi confirmado.',
                'Até a confirmação, a conta lê tudo e não grava nada. O link está na caixa de entrada de quem criou a conta.');
        }

        if (!Plan::exhausted($tenantId)) {
            return;
        }
        $plano = Plan::summary($tenantId);
        self::problem(402, 'quota_exceeded',
            'A faixa do mês acabou.',
            'Foram ' . $plano['used'] . ' de ' . $plano['limit'] . ' emissões em ' . $plano['competencia']
            . '. O que já está no registro continua conferível: a faixa limita emitir, nunca verificar.');
    }

    /**
     * O que a escrita devolve é lido de volta do registro, e não montado a
     * partir do retorno da função que gravou. Assim a API responde exatamente
     * o que a verificação responde — uma fonte só — e um campo derivado como
     * o estado nunca sai calculado de dois jeitos diferentes.
     */
    private static function issuanceOut(array $r): array
    {
        $base = (string) $r['base'];
        $reg = Store::find($base);
        $codigo = Code::format(Code::full($base));

        return [
            'code'        => $codigo,
            'status'      => $reg['state'] ?? 'verified',
            'origin'      => $reg['origin'] ?? 'issued',
            'holder'      => $reg !== null ? Store::displayName($reg) : null,
            'program'     => $reg['program'] ?? null,
            'issued_on'   => $reg['issued_on'] ?? null,
            'valid_until' => $reg['valid_until'] ?? null,
            'days_left'   => $reg['days_left'] ?? null,
            'verify_url'  => base_url() . '/pt-br/v/' . $codigo,
            'sha256'      => $reg['hash'] ?? null,
        ];
    }

    /** Público porque o MCP lê a mesma credencial pelo mesmo caminho. */
    public static function authHeader(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($h === null && function_exists('apache_request_headers')) {
            $todos = apache_request_headers();
            foreach ($todos as $nome => $valor) {
                if (strcasecmp($nome, 'Authorization') === 0) {
                    return (string) $valor;
                }
            }
        }

        return is_string($h) ? $h : null;
    }

    private static function header(string $nome): ?string
    {
        $chave = 'HTTP_' . strtoupper(str_replace('-', '_', $nome));
        if (isset($_SERVER[$chave])) {
            return (string) $_SERVER[$chave];
        }
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $n => $v) {
                if (strcasecmp($n, $nome) === 0) {
                    return (string) $v;
                }
            }
        }

        return null;
    }

    private static function body(bool $obrigatorio = true): array
    {
        $cru = file_get_contents('php://input');
        if (!is_string($cru) || trim($cru) === '') {
            if ($obrigatorio) {
                self::problem(400, 'empty_body', 'Corpo vazio.', 'Envie JSON em application/json.');
            }

            return [];
        }
        $dados = json_decode($cru, true);
        if (!is_array($dados)) {
            self::problem(400, 'invalid_json', 'O corpo não é JSON válido.', null);
        }

        return $dados;
    }

    /** @param list<string> $campos */
    private static function requireFields(array $body, array $campos): void
    {
        $faltando = [];
        foreach ($campos as $c) {
            if (!isset($body[$c]) || (is_string($body[$c]) && trim($body[$c]) === '')) {
                $faltando[] = $c;
            }
        }
        if ($faltando !== []) {
            self::problem(422, 'missing_field',
                'Faltam campos obrigatórios: ' . implode(', ', $faltando) . '.', null);
        }
    }

    private static function modality($valor): string
    {
        $v = is_string($valor) ? strtolower($valor) : 'presencial';

        return in_array($v, ['presencial', 'online', 'hibrido'], true) ? $v : 'presencial';
    }

    private static function date($valor): ?string
    {
        if (!is_string($valor) || $valor === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $d !== false && $d->format('Y-m-d') === $valor ? $valor : null;
    }

    private static function sha256($valor): string
    {
        return is_string($valor) && preg_match('/^[a-f0-9]{64}$/i', $valor) === 1 ? strtolower($valor) : '';
    }

    /* ---------------------------------------------------------- respostas */

    private static function ok(int $status, array $corpo): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Erro em RFC 9457.
     *
     * `code` é o contrato: a mensagem muda e um dia será traduzida, o código
     * não. Quem integra compara o código.
     */
    private static function problem(int $status, string $code, string $title, ?string $detail): void
    {
        http_response_code($status);
        header('Content-Type: application/problem+json; charset=UTF-8');
        echo json_encode(array_filter([
            'type'   => 'https://verid4u.com/errors/' . $code,
            'title'  => $title,
            'status' => $status,
            'detail' => $detail,
            'code'   => $code,
        ], static fn ($v): bool => $v !== null), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
