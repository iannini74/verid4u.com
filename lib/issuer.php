<?php
declare(strict_types=1);

/** Regras de negócio do emissor: cursos, emissão e revogação. */
final class Issuer
{
    public static function defaultIssuer(int $tenantId): ?array
    {
        return Db::one('SELECT * FROM issuers WHERE tenant_id = ? ORDER BY id ASC LIMIT 1', [$tenantId]);
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
            $pdo->commit();

            Auth::audit('issuance.created', 'issuance', (string) $id, ['code' => Code::full($base)]);
            return ['id' => $id, 'base' => $base, 'code' => Code::full($base), 'valid_until' => $validUntil];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function issuances(int $tenantId, int $limit = 100): array
    {
        return Db::all(
            'SELECT id, code_base, program_title, holder_name, holder_display, issued_on,
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

    public static function revoke(int $tenantId, int $issuanceId, string $reason): bool
    {
        $done = Db::run(
            'UPDATE issuances SET revoked_on = CURDATE(), revoke_reason = ?
             WHERE id = ? AND tenant_id = ? AND revoked_on IS NULL',
            [$reason, $issuanceId, $tenantId]
        )->rowCount() > 0;
        if ($done) {
            Auth::audit('issuance.revoked', 'issuance', (string) $issuanceId, ['reason' => $reason]);
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

    public static function byCode(int $tenantId, string $base): ?array
    {
        $row = Db::one('SELECT * FROM issuances WHERE tenant_id = ? AND code_base = ? LIMIT 1', [$tenantId, $base]);
        return $row;
    }
}
