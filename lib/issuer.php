<?php
declare(strict_types=1);

/** Regras de negócio do emissor: cursos, emissão e revogação. */
final class Issuer
{
    public static function defaultIssuer(int $tenantId): ?array
    {
        return Db::one('SELECT * FROM issuers WHERE tenant_id = ? ORDER BY id ASC LIMIT 1', [$tenantId]);
    }

    /* ------------------------------------------- endereço público do emissor */

    /**
     * Slug a partir do nome. ASCII, minúsculo, sem acento e sem palavra vazia.
     * É endereço público e vai em peça impressa, então precisa ser curto e
     * ditável ao telefone.
     */
    public static function slugify(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $base = strtolower((string) ($ascii !== false ? $ascii : $name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');

        // Palavras que só alongam o endereço sem distinguir ninguém.
        $drop = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'ltda', 'me', 'eireli', 'sa'];
        $parts = array_values(array_filter(
            explode('-', $base),
            static fn (string $w): bool => $w !== '' && !in_array($w, $drop, true)
        ));
        $slug = substr(implode('-', array_slice($parts, 0, 5)), 0, 60);

        return rtrim($slug, '-') !== '' ? rtrim($slug, '-') : 'emissor';
    }

    /** Garante que o emissor tenha slug, resolvendo colisão com sufixo curto. */
    public static function ensureSlug(int $issuerId, string $name): string
    {
        $row = Db::one('SELECT slug FROM issuers WHERE id = ? LIMIT 1', [$issuerId]);
        $current = (string) ($row['slug'] ?? '');
        if ($current !== '') {
            return $current;
        }

        $wanted = self::slugify($name);
        for ($try = 0; $try < 12; $try++) {
            $slug = $try === 0 ? $wanted : $wanted . '-' . substr(bin2hex(random_bytes(2)), 0, 3);
            try {
                Db::run('UPDATE issuers SET slug = ? WHERE id = ?', [$slug, $issuerId]);
                return $slug;
            } catch (Throwable $e) {
                // slug tomado: tenta outro sufixo
            }
        }

        return 'emissor-' . $issuerId;
    }

    public static function setCategory(int $tenantId, int $issuerId, string $category): bool
    {
        if (!in_array($category, Brand::CATEGORIES, true)) {
            return false;
        }
        Db::run('UPDATE issuers SET category = ? WHERE id = ? AND tenant_id = ?', [$category, $issuerId, $tenantId]);

        return true;
    }

    /**
     * Retrato público do emissor, para a página /e/{slug} e para o selo.
     *
     * Só agregados: nenhum nome de titular sai daqui. A página do emissor
     * existe para provar que o emissor está no registro, não para listar
     * quem estudou lá — isso seria divulgar dado pessoal sem nenhuma
     * necessidade, e a LGPD chama essa necessidade de finalidade.
     */
    public static function publicBySlug(string $slug): ?array
    {
        $row = Db::one(
            'SELECT id, tenant_id, name, slug, legal_id, level, category, created_at
               FROM issuers WHERE slug = ? LIMIT 1',
            [$slug]
        );
        if ($row === null) {
            return null;
        }

        $agg = Db::one(
            "SELECT COUNT(*) AS total,
                    SUM(origin = 'issued') AS issued,
                    SUM(origin = 'registered') AS registered,
                    SUM(revoked_on IS NOT NULL) AS revoked,
                    MIN(issued_on) AS first_on,
                    MAX(issued_on) AS last_on
               FROM issuances WHERE issuer_id = ?",
            [(int) $row['id']]
        ) ?? [];

        return [
            'id'         => (int) $row['id'],
            'tenant_id'  => (int) $row['tenant_id'],
            'name'       => (string) $row['name'],
            'slug'       => (string) $row['slug'],
            'legal_id'   => $row['legal_id'],
            'level'      => (int) $row['level'],
            'category'   => (string) ($row['category'] ?: Brand::CATEGORY_DEFAULT),
            'created_at' => $row['created_at'],
            'total'      => (int) ($agg['total'] ?? 0),
            'issued'     => (int) ($agg['issued'] ?? 0),
            'registered' => (int) ($agg['registered'] ?? 0),
            'revoked'    => (int) ($agg['revoked'] ?? 0),
            'first_on'   => $agg['first_on'] ?? null,
            'last_on'    => $agg['last_on'] ?? null,
        ];
    }

    public static function programs(int $tenantId): array
    {
        return Db::all(
            'SELECT * FROM programs WHERE tenant_id = ? AND archived_at IS NULL ORDER BY title ASC',
            [$tenantId]
        );
    }

    public static function createProgram(int $tenantId, int $issuerId, array $input): int
    {
        Db::run(
            'INSERT INTO programs (tenant_id, issuer_id, title, norm, hours, modality, assessed, validity_months)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                $issuerId,
                $input['title'],
                $input['norm'] !== '' ? $input['norm'] : null,
                $input['hours'],
                $input['modality'],
                $input['assessed'] ? 1 : 0,
                $input['validity_months'] > 0 ? $input['validity_months'] : null,
            ]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    /** Gera uma base de 11 caracteres que ainda não existe. */
    private static function freshCodeBase(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $base = Code::generateBase();
            $taken = Db::one('SELECT 1 AS t FROM issuances WHERE code_base = ? LIMIT 1', [$base]);
            if ($taken === null) {
                return $base;
            }
        }
        throw new RuntimeException('Não foi possível gerar um código livre.');
    }

    /**
     * Emite um certificado. Copia os dados do curso, do emissor e do titular
     * para a emissão: o que foi atestado não muda se o cadastro mudar depois.
     */
    public static function issue(int $tenantId, array $program, array $issuerRow, array $input): array
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $email = trim((string) ($input['holder_email'] ?? ''));
            Db::run(
                'INSERT INTO holders (tenant_id, name, email_enc, email_index, display_mode)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    $input['holder_name'],
                    $email !== '' ? Crypto::encrypt($email) : null,
                    $email !== '' ? Crypto::blindIndex($email) : null,
                    $input['holder_display'],
                ]
            );
            $holderId = (int) $pdo->lastInsertId();

            $issuedOn = $input['issued_on'];
            $validUntil = null;
            $months = $program['validity_months'] !== null ? (int) $program['validity_months'] : 0;
            if ($months > 0) {
                $from = DateTimeImmutable::createFromFormat('!Y-m-d', $issuedOn);
                if ($from !== false) {
                    $validUntil = $from->add(new DateInterval('P' . $months . 'M'))->format('Y-m-d');
                }
            }

            $base = self::freshCodeBase();
            $hash = Store::contentHash($base, [
                'holder_name' => $input['holder_name'],
                'program_title' => $program['title'],
                'hours' => $program['hours'],
                'modality' => $program['modality'],
                'issuer_name' => $issuerRow['name'],
                'issued_on' => $issuedOn,
                'valid_until' => $validUntil,
            ]);

            Db::run(
                'INSERT INTO issuances (
                    tenant_id, issuer_id, program_id, holder_id, code_base,
                    program_title, program_norm, hours, modality, assessed,
                    issuer_name, issuer_legal_id, issuer_level,
                    holder_name, holder_display,
                    issued_on, valid_from, valid_until,
                    content_hash, anchored_on, is_demo
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 0)',
                [
                    $tenantId, (int) $issuerRow['id'], (int) $program['id'], $holderId, $base,
                    $program['title'], $program['norm'], (int) $program['hours'], $program['modality'], (int) $program['assessed'],
                    $issuerRow['name'], $issuerRow['legal_id'], (int) $issuerRow['level'],
                    $input['holder_name'], $input['holder_display'],
                    $issuedOn, $issuedOn, $validUntil,
                    $hash,
                ]
            );
            $id = (int) $pdo->lastInsertId();
            Plan::record($tenantId, 'issued', $issuedOn);
            $pdo->commit();

            Auth::audit('issuance.created', 'issuance', (string) $id, ['code' => Code::full($base)]);

            self::notificar('issuance_created', [
                'id' => $id, 'tenant_id' => $tenantId, 'code' => Code::full($base),
                'holder_name' => $input['holder_name'], 'program_title' => $program['title'],
                'issuer_name' => $issuerRow['name'], 'issued_on' => $issuedOn, 'valid_until' => $validUntil,
            ], (string) ($input['holder_email'] ?? ''), (string) ($input['locale'] ?? 'pt-br'));

            return ['id' => $id, 'base' => $base, 'code' => Code::full($base), 'valid_until' => $validUntil];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra um certificado emitido FORA da plataforma.
     *
     * A diferença para issue() é de honestidade, não de tecnologia: aqui o
     * VerID4U não produziu o documento. O que ele atesta é que este conteúdo
     * foi registrado nesta data por este cliente — e, quando há impressão
     * digital do arquivo, que o arquivo em mãos é bit a bit o registrado.
     * A página de verificação precisa dizer isso com todas as letras.
     */
    public static function register(int $tenantId, array $issuerRow, array $input): array
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $email = trim((string) ($input['holder_email'] ?? ''));
            Db::run(
                'INSERT INTO holders (tenant_id, name, email_enc, email_index, display_mode)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    $input['holder_name'],
                    $email !== '' ? Crypto::encrypt($email) : null,
                    $email !== '' ? Crypto::blindIndex($email) : null,
                    $input['holder_display'],
                ]
            );
            $holderId = (int) $pdo->lastInsertId();

            $base = self::freshCodeBase();
            $hash = Store::contentHash($base, [
                'holder_name' => $input['holder_name'],
                'program_title' => $input['program_title'],
                'hours' => $input['hours'],
                'modality' => $input['modality'],
                'issuer_name' => $input['issuer_name'],
                'issued_on' => $input['issued_on'],
                'valid_until' => $input['valid_until'],
            ]);

            Db::run(
                'INSERT INTO issuances (
                    tenant_id, issuer_id, program_id, holder_id, code_base, origin,
                    program_title, program_norm, hours, modality, assessed,
                    issuer_name, issuer_legal_id, issuer_level, registered_by,
                    holder_name, holder_display,
                    issued_on, valid_from, valid_until,
                    content_hash, source_sha256, source_filename, anchored_on, is_demo
                 ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 0)',
                [
                    $tenantId, (int) $issuerRow['id'], $holderId, $base, 'registered',
                    $input['program_title'], $input['program_norm'] !== '' ? $input['program_norm'] : null,
                    (int) $input['hours'], $input['modality'], $input['assessed'] ? 1 : 0,
                    $input['issuer_name'], null, (int) $issuerRow['level'], $issuerRow['name'],
                    $input['holder_name'], $input['holder_display'],
                    $input['issued_on'], $input['issued_on'], $input['valid_until'],
                    $hash, $input['source_sha256'] !== '' ? $input['source_sha256'] : null,
                    $input['source_filename'] !== '' ? $input['source_filename'] : null,
                ]
            );
            $id = (int) $pdo->lastInsertId();
            Plan::record($tenantId, 'registered', (string) $input['issued_on']);
            $pdo->commit();

            Auth::audit('issuance.registered', 'issuance', (string) $id, ['code' => Code::full($base)]);

            self::notificar('issuance_registered', [
                'id' => $id, 'tenant_id' => $tenantId, 'code' => Code::full($base),
                'holder_name' => $input['holder_name'], 'program_title' => $input['program_title'],
                'issuer_name' => $input['issuer_name'], 'issued_on' => $input['issued_on'],
                'valid_until' => $input['valid_until'],
            ], (string) ($input['holder_email'] ?? ''), (string) ($input['locale'] ?? 'pt-br'));

            return ['id' => $id, 'base' => $base, 'code' => Code::full($base)];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Põe o aviso ao titular na fila.
     *
     * Nunca envia aqui: emitir mil certificados não pode esperar mil
     * conexões SMTP. A fila é despachada por cron.
     */
    private static function notificar(string $template, array $registro, string $email, string $locale, array $extra = []): void
    {
        if ($email === '') {
            return;
        }
        $validade = $registro['valid_until'] !== null
            ? Mailer::phrase('common.validity_until', $locale, ['valid_until' => Mailer::formatDate($registro['valid_until'], $locale)])
            : Mailer::phrase('common.validity_none', $locale);

        $codigo = Code::format($registro['code']);
        Mailer::queue(
            $template,
            $email,
            $registro['holder_name'] ?? null,
            $locale,
            [
                'program'     => $registro['program_title'],
                'issuer'      => $registro['issuer_name'],
                'code'        => $codigo,
                'issued_on'   => Mailer::formatDate($registro['issued_on'], $locale),
                'valid_until' => Mailer::formatDate($registro['valid_until'], $locale),
                'validity'    => $validade,
                'url'         => base_url() . '/' . $locale . '/v/' . $codigo,
            ] + $extra,
            $registro['tenant_id'] ?? null,
            $registro['id'] ?? null
        );
    }

    public static function issuances(int $tenantId, int $limit = 100): array
    {
        return Db::all(
            'SELECT id, code_base, origin, program_title, holder_name, holder_display, issued_on,
                    valid_until, revoked_on, is_demo
             FROM issuances WHERE tenant_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)),
            [$tenantId]
        );
    }

    public static function expiring(int $tenantId, int $days = 90): array
    {
        return Db::all(
            'SELECT id, code_base, program_title, holder_name, holder_display, valid_until
             FROM issuances
             WHERE tenant_id = ? AND revoked_on IS NULL AND valid_until IS NOT NULL
               AND valid_until <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY valid_until ASC LIMIT 50',
            [$tenantId, $days]
        );
    }

    public static function revoke(int $tenantId, int $issuanceId, string $reason, string $locale = 'pt-br'): bool
    {
        $done = Db::run(
            'UPDATE issuances SET revoked_on = CURDATE(), revoke_reason = ?
             WHERE id = ? AND tenant_id = ? AND revoked_on IS NULL',
            [$reason, $issuanceId, $tenantId]
        )->rowCount() > 0;
        if ($done) {
            Auth::audit('issuance.revoked', 'issuance', (string) $issuanceId, ['reason' => $reason]);
            try {
                $r = Db::one(
                    'SELECT i.id, i.tenant_id, i.code_base, i.holder_name, i.program_title, i.issuer_name,
                            i.issued_on, i.valid_until, h.email_enc
                     FROM issuances i JOIN holders h ON h.id = i.holder_id
                     WHERE i.id = ? LIMIT 1',
                    [$issuanceId]
                );
                if ($r !== null) {
                    $email = Crypto::decrypt($r['email_enc']) ?? '';
                    self::notificar('issuance_revoked', [
                        'id' => (int) $r['id'], 'tenant_id' => (int) $r['tenant_id'],
                        'code' => Code::full((string) $r['code_base']),
                        'holder_name' => $r['holder_name'], 'program_title' => $r['program_title'],
                        'issuer_name' => $r['issuer_name'], 'issued_on' => $r['issued_on'],
                        'valid_until' => $r['valid_until'],
                    ], $email, $locale, ['reason' => $reason]);
                }
            } catch (Throwable $e) {
                error_log('[verid4u] aviso de revogacao: ' . $e->getMessage());
            }
        }
        return $done;
    }

    public static function stats(int $tenantId): array
    {
        $row = Db::one(
            'SELECT
                COUNT(*) AS total,
                SUM(revoked_on IS NOT NULL) AS revoked,
                SUM(valid_until IS NOT NULL AND revoked_on IS NULL
                    AND valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)) AS expiring,
                SUM(valid_until IS NOT NULL AND revoked_on IS NULL AND valid_until < CURDATE()) AS expired
             FROM issuances WHERE tenant_id = ?',
            [$tenantId]
        ) ?? [];
        $checks = Db::one(
            'SELECT COUNT(*) AS n FROM verification_events ve
             JOIN issuances i ON i.id = ve.issuance_id WHERE i.tenant_id = ?',
            [$tenantId]
        ) ?? ['n' => 0];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'revoked' => (int) ($row['revoked'] ?? 0),
            'expiring' => (int) ($row['expiring'] ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
            'verifications' => (int) ($checks['n'] ?? 0),
        ];
    }

    /**
     * A emissão, com a marca viva do emissor ao lado do retrato.
     *
     * O nome do emissor guardado na emissão é retrato do momento em que ela
     * foi criada, e continua sendo — é ele que o certificado imprime. A logo
     * e o endereço público são vivos, e vêm por fora: trocar de logo não pode
     * reescrever o que já foi atestado, mas reimprimir um certificado antigo
     * com a logo velha também não faria sentido.
     */
    public static function byCode(int $tenantId, string $base): ?array
    {
        return Db::one(
            'SELECT n.*, e.logo_path AS issuer_logo, e.slug AS issuer_slug
               FROM issuances n
          LEFT JOIN issuers e ON e.id = n.issuer_id
              WHERE n.tenant_id = ? AND n.code_base = ? LIMIT 1',
            [$tenantId, $base]
        );
    }
}
