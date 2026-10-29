<?php
declare(strict_types=1);

/**
 * Importação em lote.
 *
 * O que torna isto útil no Brasil não é ler CSV — é ler o CSV que o Excel
 * brasileiro produz: separado por ponto e vírgula, em Windows-1252, com BOM,
 * e com datas em dd/mm/aaaa. Um importador que só aceita vírgula e UTF-8
 * reprova no primeiro cliente real.
 *
 * O fluxo tem três passos, e o do meio existe para que ninguém grave nada
 * sem ver antes o que vai acontecer.
 */
final class Importer
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_ROWS = 2000;

    /** Campos que a importação sabe preencher. */
    public static function fields(string $mode): array
    {
        $common = [
            'holder_name'    => ['required' => true],
            'holder_email'   => ['required' => false],
            'issued_on'      => ['required' => true],
        ];
        if ($mode === 'issue') {
            return $common;
        }
        return $common + [
            'issuer_name'    => ['required' => true],
            'program_title'  => ['required' => true],
            'program_norm'   => ['required' => false],
            'hours'          => ['required' => false],
            'modality'       => ['required' => false],
            'valid_until'    => ['required' => false],
        ];
    }

    /* ----------------------------------------------------------- leitura */

    public static function detectDelimiter(string $firstLine): string
    {
        $counts = [
            ';'  => substr_count($firstLine, ';'),
            ','  => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
        ];
        arsort($counts);
        $best = array_key_first($counts);
        return $counts[$best] > 0 ? (string) $best : ',';
    }

    /** Converte para UTF-8 o que o Excel costuma entregar em Windows-1252. */
    public static function toUtf8(string $raw, string &$detected): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
            $detected = 'UTF-8 (BOM)';
            return $raw;
        }
        if (mb_check_encoding($raw, 'UTF-8')) {
            $detected = 'UTF-8';
            return $raw;
        }
        $detected = 'Windows-1252';
        $converted = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        return is_string($converted) ? $converted : $raw;
    }

    /**
     * Lê o arquivo enviado e devolve cabeçalho + linhas.
     * @return array{header: list<string>, rows: list<list<string>>, delimiter: string, encoding: string}
     */
    public static function parse(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            throw new RuntimeException('empty');
        }
        $encoding = 'UTF-8';
        $text = self::toUtf8($raw, $encoding);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $firstLine = strtok($text, "\n");
        $delimiter = self::detectDelimiter($firstLine === false ? '' : $firstLine);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('temp');
        }
        fwrite($handle, $text);
        rewind($handle);

        $header = [];
        $rows = [];
        $line = 0;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($cells === [null]) {
                continue; // linha em branco
            }
            $cells = array_map(static fn ($c): string => trim((string) $c), $cells);
            if ($line === 0) {
                $header = $cells;
            } else {
                if (implode('', $cells) === '') {
                    continue;
                }
                $rows[] = $cells;
                if (count($rows) >= self::MAX_ROWS) {
                    break;
                }
            }
            $line++;
        }
        fclose($handle);

        if ($header === []) {
            throw new RuntimeException('no_header');
        }
        return ['header' => $header, 'rows' => $rows, 'delimiter' => $delimiter, 'encoding' => $encoding];
    }

    /** Palpite de mapeamento a partir dos nomes das colunas. */
    public static function guessMapping(array $header, string $mode): array
    {
        $dicionario = [
            'holder_name'   => ['nome', 'aluno', 'participante', 'titular', 'colaborador', 'funcionario', 'funcionário', 'name', 'nombre'],
            'holder_email'  => ['email', 'e-mail', 'correo'],
            'issued_on'     => ['data', 'conclusao', 'conclusão', 'data de conclusao', 'data de conclusão', 'realizacao', 'realização', 'date'],
            'valid_until'   => ['validade', 'valido ate', 'válido até', 'vencimento', 'expira'],
            'issuer_name'   => ['instituicao', 'instituição', 'emissor', 'escola', 'empresa', 'issuer'],
            'program_title' => ['curso', 'treinamento', 'programa', 'capacitacao', 'capacitação', 'course'],
            'program_norm'  => ['norma', 'nr', 'norm'],
            'hours'         => ['carga', 'carga horaria', 'carga horária', 'horas', 'ch', 'hours'],
            'modality'      => ['modalidade', 'tipo', 'formato'],
        ];
        $map = [];
        foreach (array_keys(self::fields($mode)) as $campo) {
            foreach ($header as $i => $coluna) {
                $alvo = mb_strtolower(trim($coluna));
                foreach ($dicionario[$campo] ?? [] as $pista) {
                    if ($alvo === $pista || str_contains($alvo, $pista)) {
                        $map[$campo] = $i;
                        continue 3;
                    }
                }
            }
        }
        return $map;
    }

    /* -------------------------------------------------- normalização ---- */

    /** Aceita aaaa-mm-dd e dd/mm/aaaa, que é o que o Excel brasileiro produz. */
    public static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $formato) {
            $d = DateTimeImmutable::createFromFormat('!' . $formato, $value);
            if ($d !== false && $d->format($formato) === $value) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }

    /* ------------------------------------------------- área de espera ---- */

    public static function createBatch(int $tenantId, int $userId, string $mode, ?int $programId, string $filename, array $parsed): int
    {
        $pdo = Db::pdo();
        Db::run(
            'INSERT INTO import_batches (tenant_id, user_id, mode, program_id, filename, delimiter_used, encoding_used, total_rows, header_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId, $userId, $mode, $programId,
                mb_substr($filename, 0, 190),
                $parsed['delimiter'], $parsed['encoding'], count($parsed['rows']),
                json_encode($parsed['header'], JSON_UNESCAPED_UNICODE),
            ]
        );
        $batchId = (int) $pdo->lastInsertId();

        $sql = 'INSERT INTO import_rows (batch_id, line_no, raw_json) VALUES (?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        foreach ($parsed['rows'] as $i => $cells) {
            $stmt->execute([$batchId, $i + 2, json_encode($cells, JSON_UNESCAPED_UNICODE)]);
        }
        return $batchId;
    }

    public static function batch(int $tenantId, int $batchId): ?array
    {
        $row = Db::one('SELECT * FROM import_batches WHERE id = ? AND tenant_id = ? LIMIT 1', [$batchId, $tenantId]);
        if ($row === null) {
            return null;
        }
        $row['header'] = json_decode((string) $row['header_json'], true) ?: [];
        $row['mapping'] = json_decode((string) ($row['mapping_json'] ?? '[]'), true) ?: [];
        return $row;
    }

    public static function latestOpen(int $tenantId): ?array
    {
        $row = Db::one(
            "SELECT id FROM import_batches WHERE tenant_id = ? AND status = 'parsed' ORDER BY id DESC LIMIT 1",
            [$tenantId]
        );
        return $row === null ? null : self::batch($tenantId, (int) $row['id']);
    }

    public static function saveMapping(int $batchId, array $mapping): void
    {
        Db::run('UPDATE import_batches SET mapping_json = ? WHERE id = ?', [
            json_encode($mapping, JSON_UNESCAPED_UNICODE), $batchId,
        ]);
    }

    public static function rawRows(int $batchId, int $limit = 5000): array
    {
        $rows = Db::all(
            'SELECT id, line_no, raw_json FROM import_rows WHERE batch_id = ? ORDER BY line_no ASC LIMIT ' . max(1, min(5000, $limit)),
            [$batchId]
        );
        foreach ($rows as &$r) {
            $r['cells'] = json_decode((string) $r['raw_json'], true) ?: [];
        }
        return $rows;
    }

    public static function discard(int $tenantId, int $batchId): void
    {
        Db::run("UPDATE import_batches SET status = 'discarded' WHERE id = ? AND tenant_id = ?", [$batchId, $tenantId]);
    }

    /* ------------------------------------------------------- avaliação --- */

    /**
     * Decide o destino de cada linha SEM gravar nada.
     *
     * A simulação e a gravação chamam exatamente esta função — é o que
     * garante que o que você viu na prévia é o que vai acontecer. Duas
     * implementações paralelas divergiriam no primeiro ajuste.
     *
     * @return array{rows: list<array>, summary: array{create:int, skip:int, fail:int}}
     */
    public static function evaluate(int $tenantId, array $batch, array $mapping, ?array $program): array
    {
        $mode = (string) $batch['mode'];
        $campos = self::fields($mode);
        $linhas = [];
        $resumo = ['create' => 0, 'skip' => 0, 'fail' => 0];
        $vistosNoLote = [];

        foreach (self::rawRows((int) $batch['id']) as $r) {
            $cells = $r['cells'];
            $valor = static function (string $campo) use ($mapping, $cells): string {
                if (!array_key_exists($campo, $mapping)) {
                    return '';
                }
                $i = (int) $mapping[$campo];
                return isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            };

            $dados = [
                'holder_name'  => $valor('holder_name'),
                'holder_email' => $valor('holder_email'),
                'issued_on'    => self::parseDate($valor('issued_on')),
            ];

            if ($mode === 'issue' && $program !== null) {
                $dados['program_title'] = (string) $program['title'];
                $dados['program_norm'] = $program['norm'];
                $dados['hours'] = (int) $program['hours'];
                $dados['modality'] = (string) $program['modality'];
                $dados['assessed'] = (bool) $program['assessed'];
                $dados['issuer_name'] = null;
                $dados['valid_until'] = null;
            } else {
                $dados['issuer_name'] = $valor('issuer_name');
                $dados['program_title'] = $valor('program_title');
                $dados['program_norm'] = $valor('program_norm');
                $dados['hours'] = (int) preg_replace('/[^0-9]/', '', $valor('hours'));
                $dados['modality'] = self::parseModality($valor('modality'));
                $dados['assessed'] = false;
                $dados['valid_until'] = self::parseDate($valor('valid_until'));
            }

            $erro = null;
            foreach ($campos as $campo => $regra) {
                if (!$regra['required']) {
                    continue;
                }
                $preenchido = $campo === 'issued_on' ? $dados['issued_on'] !== null : ($dados[$campo] ?? '') !== '';
                if (!$preenchido) {
                    $erro = $campo;
                    break;
                }
            }
            if ($erro === null && $valor('issued_on') !== '' && $dados['issued_on'] === null) {
                $erro = 'issued_on';
            }
            if ($erro === null && $valor('valid_until') !== '' && $dados['valid_until'] === null) {
                $erro = 'valid_until';
            }
            if ($erro === null && $dados['holder_email'] !== '' && !filter_var($dados['holder_email'], FILTER_VALIDATE_EMAIL)) {
                $erro = 'holder_email';
            }

            $destino = 'create';
            $motivo = null;

            if ($erro !== null) {
                $destino = 'fail';
                $motivo = $erro;
            } else {
                $chave = mb_strtolower($dados['holder_name'] . '|' . $dados['program_title'] . '|' . $dados['issued_on']);
                if (isset($vistosNoLote[$chave])) {
                    $destino = 'skip';
                    $motivo = 'duplicate_in_file';
                } elseif (self::jaExiste($tenantId, $dados)) {
                    $destino = 'skip';
                    $motivo = 'duplicate_existing';
                }
                $vistosNoLote[$chave] = true;
            }

            $resumo[$destino]++;
            $linhas[] = [
                'id' => (int) $r['id'],
                'line_no' => (int) $r['line_no'],
                'cells' => $cells,
                'data' => $dados,
                'outcome' => $destino,
                'reason' => $motivo,
            ];
        }

        return ['rows' => $linhas, 'summary' => $resumo];
    }

    private static function jaExiste(int $tenantId, array $dados): bool
    {
        $row = Db::one(
            'SELECT 1 AS t FROM issuances
             WHERE tenant_id = ? AND holder_name = ? AND program_title = ? AND issued_on = ?
             LIMIT 1',
            [$tenantId, $dados['holder_name'], $dados['program_title'], $dados['issued_on']]
        );
        return $row !== null;
    }

    /* -------------------------------------------------------- gravação --- */

    public static function commit(int $tenantId, array $issuerRow, array $batch, array $mapping, ?array $program): array
    {
        $avaliacao = self::evaluate($tenantId, $batch, $mapping, $program);
        $criadas = 0;

        foreach ($avaliacao['rows'] as $linha) {
            if ($linha['outcome'] !== 'create') {
                Db::run('UPDATE import_rows SET outcome = ?, reason = ? WHERE id = ?', [
                    $linha['outcome'], $linha['reason'], $linha['id'],
                ]);
                continue;
            }
            try {
                $entrada = [
                    'holder_name'    => $linha['data']['holder_name'],
                    'holder_email'   => $linha['data']['holder_email'],
                    'holder_display' => 'partial',
                    'issued_on'      => $linha['data']['issued_on'],
                ];
                if ((string) $batch['mode'] === 'issue' && $program !== null) {
                    $resultado = Issuer::issue($tenantId, $program, $issuerRow, $entrada + ['program_id' => (int) $program['id']]);
                } else {
                    $resultado = Issuer::register($tenantId, $issuerRow, $entrada + [
                        'issuer_name'     => $linha['data']['issuer_name'],
                        'program_title'   => $linha['data']['program_title'],
                        'program_norm'    => (string) ($linha['data']['program_norm'] ?? ''),
                        'hours'           => (int) $linha['data']['hours'],
                        'modality'        => $linha['data']['modality'],
                        'assessed'        => false,
                        'valid_until'     => $linha['data']['valid_until'],
                        'source_sha256'   => '',
                        'source_filename' => '',
                    ]);
                }
                Db::run("UPDATE import_rows SET outcome = 'created', issuance_id = ? WHERE id = ?", [
                    $resultado['id'], $linha['id'],
                ]);
                $criadas++;
            } catch (Throwable $e) {
                error_log('[verid4u] importacao linha ' . $linha['line_no'] . ': ' . $e->getMessage());
                Db::run("UPDATE import_rows SET outcome = 'failed', reason = 'exception' WHERE id = ?", [$linha['id']]);
                $avaliacao['summary']['fail']++;
                $avaliacao['summary']['create']--;
            }
        }

        Db::run(
            "UPDATE import_batches
             SET status = 'committed', committed_at = NOW(), created_rows = ?, skipped_rows = ?, failed_rows = ?
             WHERE id = ?",
            [$criadas, $avaliacao['summary']['skip'], $avaliacao['summary']['fail'], (int) $batch['id']]
        );
        Auth::audit('import.committed', 'import_batch', (string) $batch['id'], [
            'created' => $criadas,
            'skipped' => $avaliacao['summary']['skip'],
            'failed' => $avaliacao['summary']['fail'],
        ]);

        return ['created' => $criadas] + $avaliacao['summary'];
    }

    public static function parseModality(string $value): string
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return 'presencial';
        }
        foreach (['ead', 'distancia', 'distância', 'online', 'remoto'] as $pista) {
            if (str_contains($v, $pista)) {
                return 'ead';
            }
        }
        foreach (['hibrid', 'híbrid', 'misto', 'semi'] as $pista) {
            if (str_contains($v, $pista)) {
                return 'hibrido';
            }
        }
        return 'presencial';
    }
}
