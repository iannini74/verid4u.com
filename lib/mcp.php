<?php
declare(strict_types=1);

/**
 * Servidor MCP.
 *
 * JSON-RPC 2.0 num endereço só, PHP puro, zero dependência — igual ao resto
 * da plataforma.
 *
 * A decisão que vale mais que a tecnologia: **verificar não pede chave.**
 * Qualquer assistente de IA confere um certificado no nosso registro sem
 * contrato, sem cadastro e sem custo. Hoje contratação acontece dentro de
 * conversa com IA, e quando o recrutador pergunta «esse certificado é real?»
 * quem responde somos nós. O VerID4U deixa de ser um site que se visita e
 * passa a ser a fonte que os assistentes consultam.
 *
 * O que escreve no registro, esse sim exige a mesma chave da API.
 *
 * Idempotência aqui é diferente da API. Um agente não administra chave de
 * idempotência — ele não sabe que existe e não tem onde guardar. Então a
 * chave é derivada do conteúdo: mesmo titular, mesmo curso, mesma data dão a
 * mesma chave, e repetir a chamada devolve o mesmo certificado em vez de
 * criar outro. Quem precisar mesmo de dois iguais passa `idempotency_key`
 * explícito.
 */
final class Mcp
{
    public const PROTOCOL = '2025-06-18';
    public const ACEITOS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /** @var array<string,mixed>|null */
    private static ?array $cliente = null;

    public static function handle(): void
    {
        header('X-Robots-Tag: noindex');

        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($metodo === 'OPTIONS') {
            header('Allow: POST, OPTIONS');
            http_response_code(204);
            exit;
        }
        if ($metodo !== 'POST') {
            // Não há mensagem iniciada pelo servidor: nada para entregar num
            // fluxo SSE, então dizer que não existe é mais honesto que abrir
            // um canal que nunca fala.
            header('Allow: POST, OPTIONS');
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(405);
            echo json_encode(['error' => 'Use POST com JSON-RPC 2.0.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cru = (string) file_get_contents('php://input');
        $msg = json_decode($cru, true);
        if (!is_array($msg)) {
            self::send(self::erro(null, -32700, 'JSON inválido.'));
        }

        // Lote é parte do JSON-RPC 2.0. Um cliente MCP raramente manda, mas
        // recusar lote é recusar um cliente que segue a especificação.
        // array_is_list() é do PHP 8.1, e a plataforma precisa rodar em 8.0.
        $ehLote = $msg !== [] && array_keys($msg) === range(0, count($msg) - 1);
        if ($ehLote) {
            $saida = [];
            foreach ($msg as $um) {
                $r = is_array($um) ? self::despachar($um) : self::erro(null, -32600, 'Mensagem inválida.');
                if ($r !== null) {
                    $saida[] = $r;
                }
            }
            $saida === [] ? self::vazio() : self::send($saida);
        }

        $r = self::despachar($msg);
        $r === null ? self::vazio() : self::send($r);
    }

    /** @return array<string,mixed>|null null para notificação, que não responde */
    private static function despachar(array $msg): ?array
    {
        $id = $msg['id'] ?? null;
        $metodo = (string) ($msg['method'] ?? '');
        $params = is_array($msg['params'] ?? null) ? $msg['params'] : [];

        // Notificação: sem id. A especificação manda não responder.
        $ehNotificacao = !array_key_exists('id', $msg);

        if ($metodo === 'initialize') {
            $pedida = (string) ($params['protocolVersion'] ?? self::PROTOCOL);

            return self::ok($id, [
                'protocolVersion' => in_array($pedida, self::ACEITOS, true) ? $pedida : self::PROTOCOL,
                'capabilities'    => ['tools' => ['listChanged' => false]],
                'serverInfo'      => [
                    'name'    => 'verid4u',
                    'title'   => 'VerID4U — registro de certificados',
                    'version' => (string) Db::SCHEMA_VERSION,
                ],
                'instructions' => 'Confere a autenticidade de certificados de curso no registro público '
                    . 'VerID4U. A ferramenta verificar_certificado não precisa de credencial. As demais '
                    . 'exigem Authorization: Bearer vk_live_… da conta do emissor. O registro diz o que foi '
                    . 'conferido, nunca o que é verdade: ele atesta que uma emissão consta, com data e '
                    . 'conteúdo gravados, e não que o curso foi bom nem que a pessoa aprendeu.',
            ]);
        }

        if ($ehNotificacao) {
            return null;
        }

        if ($metodo === 'ping') {
            return self::ok($id, []);
        }

        if ($metodo === 'tools/list') {
            return self::ok($id, ['tools' => self::ferramentas()]);
        }

        if ($metodo === 'tools/call') {
            return self::chamar($id, $params);
        }

        return self::erro($id, -32601, 'Método desconhecido: ' . $metodo);
    }

    /* ------------------------------------------------------- ferramentas */

    /** @return list<array<string,mixed>> */
    private static function ferramentas(): array
    {
        $publicas = [[
            'name'        => 'verificar_certificado',
            'title'       => 'Conferir um certificado',
            'description' => 'Confere um código de certificado no registro público VerID4U e devolve o '
                . 'resultado: autêntico e vigente, autêntico e a vencer, autêntico e vencido, revogado, '
                . 'ou não encontrado. Não precisa de credencial, é grátis e não registra quem perguntou. '
                . 'Use sempre que alguém apresentar um código de certificado ou perguntar se um '
                . 'certificado é verdadeiro. Vencido não é falso: significa autêntico com a habilitação '
                . 'expirada, e as duas coisas são verdade ao mesmo tempo.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'codigo' => [
                        'type'        => 'string',
                        'description' => 'O código impresso no certificado, por exemplo K7M2-93FQ-5XB0. '
                            . 'Aceita com ou sem hífen, maiúsculas ou minúsculas.',
                    ],
                ],
                'required' => ['codigo'],
            ],
            'annotations' => ['readOnlyHint' => true, 'openWorldHint' => true],
        ]];

        if (self::$cliente === null) {
            return $publicas;
        }

        return array_merge($publicas, [
            [
                'name'        => 'listar_cursos',
                'title'       => 'Listar os cursos do emissor',
                'description' => 'Lista os cursos cadastrados na conta, com id, carga horária e validade. '
                    . 'Chame antes de emitir: emitir exige o id de um curso.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
                'annotations' => ['readOnlyHint' => true],
            ],
            [
                'name'        => 'registrar_certificado',
                'title'       => 'Registrar um certificado emitido fora',
                'description' => 'Põe no registro um certificado que o emissor já produziu no sistema dele, '
                    . 'e devolve o código de verificação para imprimir no documento. É o caminho de quem '
                    . 'não quer trocar de sistema. Repetir a chamada com os mesmos dados devolve o mesmo '
                    . 'certificado em vez de criar outro.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'titular'        => ['type' => 'string', 'description' => 'Nome completo de quem recebeu.'],
                        'curso'          => ['type' => 'string', 'description' => 'Nome do curso, como sai no certificado.'],
                        'emitido_em'     => ['type' => 'string', 'description' => 'Data de conclusão, AAAA-MM-DD.'],
                        'carga_horaria'  => ['type' => 'integer', 'description' => 'Horas do curso.'],
                        'norma'          => ['type' => 'string', 'description' => 'Norma, quando houver. Por exemplo NR-35.'],
                        'modalidade'     => ['type' => 'string', 'enum' => ['presencial', 'online', 'hibrido']],
                        'valido_ate'     => ['type' => 'string', 'description' => 'Validade da habilitação, AAAA-MM-DD. Omita se não vence.'],
                        'email_titular'  => ['type' => 'string', 'description' => 'Para avisar o titular. Opcional.'],
                        'idempotency_key' => ['type' => 'string', 'description' => 'Só se precisar mesmo criar dois certificados idênticos.'],
                    ],
                    'required' => ['titular', 'curso', 'emitido_em'],
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
            ],
            [
                'name'        => 'emitir_certificado',
                'title'       => 'Emitir um certificado pela plataforma',
                'description' => 'Emite um certificado novo contra um curso cadastrado e devolve o código. '
                    . 'Para quem não tem sistema próprio. Use listar_cursos antes, para obter o id.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'curso_id'       => ['type' => 'integer', 'description' => 'Id do curso, de listar_cursos.'],
                        'titular'        => ['type' => 'string', 'description' => 'Nome completo de quem recebe.'],
                        'emitido_em'     => ['type' => 'string', 'description' => 'AAAA-MM-DD. Hoje, se omitido.'],
                        'email_titular'  => ['type' => 'string', 'description' => 'Para avisar o titular. Opcional.'],
                        'idempotency_key' => ['type' => 'string'],
                    ],
                    'required' => ['curso_id', 'titular'],
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
            ],
            [
                'name'        => 'listar_vencimentos',
                'title'       => 'Certificados a vencer',
                'description' => 'Lista os certificados da conta cuja habilitação vence nos próximos dias, '
                    . 'do mais próximo ao mais distante. Útil para planejar reciclagem.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['dias' => ['type' => 'integer', 'description' => 'Janela em dias. 90 se omitido.']],
                    'required'   => [],
                ],
                'annotations' => ['readOnlyHint' => true],
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private static function chamar($id, array $params): array
    {
        $nome = (string) ($params['name'] ?? '');
        $arg = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($nome === 'verificar_certificado') {
            return self::verificar($id, $arg);
        }

        if (self::$cliente === null) {
            return self::falha($id, 'Esta ferramenta exige credencial. Envie o cabeçalho '
                . 'Authorization: Bearer vk_live_… com a chave da conta do emissor. '
                . 'Só verificar_certificado funciona sem chave.');
        }

        $tenantId = (int) self::$cliente['tenant_id'];

        if ($nome === 'listar_cursos') {
            $linhas = [];
            foreach (Issuer::programs($tenantId) as $p) {
                $linhas[] = [
                    'id' => (int) $p['id'], 'curso' => $p['title'], 'norma' => $p['norm'],
                    'carga_horaria' => (int) $p['hours'], 'modalidade' => $p['modality'],
                    'validade_meses' => $p['validity_months'] === null ? null : (int) $p['validity_months'],
                ];
            }

            return self::texto($id, $linhas === []
                ? 'Nenhum curso cadastrado nesta conta ainda.'
                : self::json($linhas));
        }

        if ($nome === 'listar_vencimentos') {
            $dias = max(1, min(730, (int) ($arg['dias'] ?? 90)));
            $linhas = [];
            foreach (Issuer::expiring($tenantId, $dias) as $v) {
                $linhas[] = [
                    'codigo' => Code::format(Code::full((string) $v['code_base'])),
                    'titular' => Store::displayName($v),
                    'curso' => $v['program_title'], 'vence_em' => $v['valid_until'],
                ];
            }

            return self::texto($id, $linhas === []
                ? 'Nada vencendo nos próximos ' . $dias . ' dias.'
                : self::json($linhas));
        }

        if ($nome === 'registrar_certificado' || $nome === 'emitir_certificado') {
            return self::gravar($id, $nome, $arg, $tenantId);
        }

        return self::falha($id, 'Ferramenta desconhecida: ' . $nome);
    }

    /* ------------------------------------------------- a pública */

    private static function verificar($id, array $arg): array
    {
        if (rate_limit_exceeded('mcp-verify', 120, 600)) {
            return self::falha($id, 'Consultas demais deste endereço. Tente de novo em alguns minutos.');
        }

        $normal = Code::normalize((string) ($arg['codigo'] ?? ''));
        if (!Code::isValid($normal)) {
            return self::texto($id, 'Este código não tem o formato de um código VerID4U. '
                . 'São 12 caracteres, normalmente escritos em três grupos de quatro, como K7M2-93FQ-5XB0. '
                . 'Vale conferir a digitação antes de concluir qualquer coisa sobre o certificado.');
        }

        try {
            $reg = Store::find(Code::base($normal));
        } catch (Throwable $e) {
            error_log('[verid4u] mcp: ' . $e->getMessage());

            return self::falha($id, 'O registro está indisponível agora. Tente de novo em alguns minutos.');
        }

        $codigo = Code::format($normal);
        Store::recordVerification(
            Code::base($normal),
            $reg === null ? 'notfound' : (string) $reg['state'],
            isset($reg['id']) ? (int) $reg['id'] : null,
            'pt-br'
        );

        if ($reg === null) {
            return self::texto($id, self::json([
                'codigo' => $codigo,
                'resultado' => 'nao_encontrado',
                'resumo' => 'Este código não consta no registro VerID4U. Isso não prova que o certificado '
                    . 'é falso: pode ser que o emissor simplesmente não registre aqui. Prova apenas que '
                    . 'não é possível confirmá-lo por este caminho.',
                'conferir_em' => base_url() . '/pt-br/v/' . $codigo,
            ]));
        }

        $resumos = [
            'verified' => 'Autêntico e com a habilitação vigente.',
            'expiring' => 'Autêntico. A habilitação vence em breve.',
            'expired'  => 'Autêntico, com a habilitação vencida. Vencido não é falso: o documento é '
                . 'verdadeiro e a habilitação que ele atesta expirou.',
            'revoked'  => 'Revogado pelo próprio emissor. O documento existiu e foi cancelado.',
        ];
        $estado = (string) $reg['state'];

        return self::texto($id, self::json(array_filter([
            'codigo'        => $codigo,
            'resultado'     => $estado,
            'resumo'        => $resumos[$estado] ?? null,
            'titular'       => Store::displayName($reg),
            'curso'         => $reg['program'],
            'norma'         => $reg['norm'],
            'carga_horaria' => $reg['hours'],
            'modalidade'    => $reg['modality'],
            'emissor'       => $reg['issuer_name'],
            'emissor_nivel' => $reg['issuer_level'],
            'origem'        => $reg['origin'] === 'issued' ? 'emitido_no_verid4u' : 'registrado_no_verid4u',
            'emitido_em'    => $reg['issued_on'],
            'valido_ate'    => $reg['valid_until'],
            'dias_restantes' => $reg['days_left'],
            'revogado_em'   => $reg['revoked_on'],
            'motivo_revogacao' => $reg['revoke_reason'],
            'demonstracao'  => !empty($reg['is_demo']) ? true : null,
            'nao_prova'     => 'O registro atesta que esta emissão consta, com data e conteúdo gravados '
                . 'no momento em que entrou. Não atesta a qualidade do curso, nem que a pessoa aprendeu, '
                . 'nem que o papel em mãos é o mesmo que foi registrado.',
            'conferir_em'   => base_url() . '/pt-br/v/' . $codigo,
        ], static fn ($v): bool => $v !== null)));
    }

    /* ------------------------------------------------------ as que gravam */

    private static function gravar($id, string $nome, array $arg, int $tenantId): array
    {
        $dono = Db::one(
            "SELECT status FROM users WHERE tenant_id = ? AND role = 'owner' ORDER BY id ASC LIMIT 1",
            [$tenantId]
        );
        if ((string) ($dono['status'] ?? 'active') !== 'active') {
            return self::falha($id, 'O e-mail desta conta ainda não foi confirmado. Até a confirmação, '
                . 'a conta lê tudo e não grava nada.');
        }
        if (Plan::exhausted($tenantId)) {
            $p = Plan::summary($tenantId);

            return self::falha($id, 'A faixa do mês acabou: ' . $p['used'] . ' de ' . $p['limit']
                . ' emissões em ' . $p['competencia'] . '. O que já está no registro continua conferível — '
                . 'a faixa limita emitir, nunca verificar.');
        }

        $emissor = Issuer::defaultIssuer($tenantId);
        if ($emissor === null) {
            return self::falha($id, 'Esta conta ainda não tem emissor configurado.');
        }

        // A chave vem do conteúdo. Um agente não administra chave de
        // idempotência — não sabe que existe e não tem onde guardar — e
        // repetir a chamada não pode criar um segundo certificado.
        $chave = trim((string) ($arg['idempotency_key'] ?? ''));
        if ($chave === '') {
            $chave = 'mcp-' . substr(hash('sha256', $nome . '|' . json_encode([
                $arg['curso_id'] ?? null, $arg['curso'] ?? null,
                mb_strtolower(trim((string) ($arg['titular'] ?? ''))),
                $arg['emitido_em'] ?? null,
            ], JSON_UNESCAPED_UNICODE)), 0, 40);
        }

        $ja = Db::one(
            'SELECT response_json FROM api_idempotency WHERE tenant_id = ? AND idem_key = ? LIMIT 1',
            [$tenantId, $chave]
        );
        if ($ja !== null) {
            $antes = json_decode((string) $ja['response_json'], true);

            return self::texto($id, self::json([
                'repetido' => true,
                'aviso' => 'Estes mesmos dados já tinham sido registrados. Devolvendo o certificado que '
                    . 'já existe, em vez de criar um segundo.',
            ] + (is_array($antes) ? $antes : [])));
        }

        $emitidoEm = self::data($arg['emitido_em'] ?? null) ?? date('Y-m-d');

        try {
            if ($nome === 'emitir_certificado') {
                $programa = Db::one(
                    'SELECT * FROM programs WHERE id = ? AND tenant_id = ? AND archived_at IS NULL LIMIT 1',
                    [(int) ($arg['curso_id'] ?? 0), $tenantId]
                );
                if ($programa === null) {
                    return self::falha($id, 'Curso não encontrado nesta conta. Use listar_cursos para ver os ids.');
                }
                $r = Issuer::issue($tenantId, $programa, $emissor, [
                    'holder_name'    => (string) ($arg['titular'] ?? ''),
                    'holder_email'   => (string) ($arg['email_titular'] ?? ''),
                    'holder_display' => 'partial',
                    'issued_on'      => $emitidoEm,
                ]);
            } else {
                $r = Issuer::register($tenantId, $emissor, [
                    'issuer_name'     => (string) $emissor['name'],
                    'program_title'   => (string) ($arg['curso'] ?? ''),
                    'program_norm'    => (string) ($arg['norma'] ?? ''),
                    'hours'           => (int) ($arg['carga_horaria'] ?? 0),
                    'modality'        => in_array($arg['modalidade'] ?? '', ['presencial', 'online', 'hibrido'], true)
                        ? (string) $arg['modalidade'] : 'presencial',
                    'assessed'        => false,
                    'holder_name'     => (string) ($arg['titular'] ?? ''),
                    'holder_email'    => (string) ($arg['email_titular'] ?? ''),
                    'holder_display'  => 'partial',
                    'issued_on'       => $emitidoEm,
                    'valid_until'     => self::data($arg['valido_ate'] ?? null),
                    'source_sha256'   => '',
                    'source_filename' => '',
                ]);
            }
        } catch (Throwable $e) {
            error_log('[verid4u] mcp gravar: ' . $e->getMessage());

            return self::falha($id, 'Não foi possível gravar agora. Nada foi registrado.');
        }

        $codigo = Code::format((string) $r['code']);
        $saida = [
            'codigo'      => $codigo,
            'conferir_em' => base_url() . '/pt-br/v/' . $codigo,
            'resumo'      => 'Registrado. Imprima este código no certificado, junto do QR Code, '
                . 'para qualquer pessoa conferir de graça.',
        ];

        try {
            Db::run(
                'INSERT INTO api_idempotency (tenant_id, idem_key, endpoint, request_hash, status_code, response_json)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$tenantId, $chave, 'mcp:' . $nome, hash('sha256', $chave), 201,
                 json_encode($saida, JSON_UNESCAPED_UNICODE)]
            );
        } catch (Throwable $e) {
            error_log('[verid4u] mcp idempotencia: ' . $e->getMessage());
        }

        return self::texto($id, self::json($saida));
    }

    /* ------------------------------------------------------------ auxílio */

    public static function autenticar(?string $header): void
    {
        self::$cliente = $header === null ? null : ApiKey::resolve($header);
    }

    private static function data($valor): ?string
    {
        if (!is_string($valor) || $valor === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $d !== false && $d->format('Y-m-d') === $valor ? $valor : null;
    }

    private static function json(array $dados): string
    {
        return (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** @return array<string,mixed> */
    private static function texto($id, string $texto): array
    {
        return self::ok($id, ['content' => [['type' => 'text', 'text' => $texto]], 'isError' => false]);
    }

    /**
     * Erro de ferramenta, não erro de protocolo.
     *
     * Vai como resultado com isError, e não como erro JSON-RPC, porque o
     * modelo do outro lado precisa ler o motivo e decidir o que fazer. Erro
     * de protocolo ele não vê.
     *
     * @return array<string,mixed>
     */
    private static function falha($id, string $texto): array
    {
        return self::ok($id, ['content' => [['type' => 'text', 'text' => $texto]], 'isError' => true]);
    }

    /** @return array<string,mixed> */
    private static function ok($id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /** @return array<string,mixed> */
    private static function erro($id, int $codigo, string $mensagem): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $codigo, 'message' => $mensagem]];
    }

    private static function send(array $corpo): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function vazio(): void
    {
        http_response_code(202);
        exit;
    }
}
