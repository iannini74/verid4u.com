<?php
declare(strict_types=1);

/**
 * Acesso aos registros, agora sobre MySQL.
 *
 * A interface pública é a mesma de antes (find / state / displayName), então
 * as views não sabem — nem precisam saber — de onde o registro veio.
 */
final class Store
{
    /** Janela de aviso antes do vencimento, em dias. */
    public const EXPIRING_WINDOW_DAYS = 90;

    public static function isDemo(): bool
    {
        return (bool) Config::get('demo', false);
    }

    public static function find(string $base): ?array
    {
        $row = Db::one(
            'SELECT id, code_base, program_title, program_norm, hours, modality, assessed,
                    issuer_name, issuer_legal_id, issuer_level,
                    holder_name, holder_display,
                    issued_on, valid_from, valid_until, revoked_on, revoke_reason,
                    version, superseded_by, content_hash, anchored_on, is_demo
             FROM issuances WHERE code_base = ? LIMIT 1',
            [$base]
        );
        if ($row === null) {
            return null;
        }
        return self::hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): array
    {
        $record = [
            'id'            => (int) $row['id'],
            'base'          => (string) $row['code_base'],
            'code'          => Code::full((string) $row['code_base']),
            'program'       => (string) $row['program_title'],
            'norm'          => $row['program_norm'],
            'hours'         => (int) $row['hours'],
            'modality'      => (string) $row['modality'],
            'assessed'      => (bool) $row['assessed'],
            'issuer_name'   => (string) $row['issuer_name'],
            'issuer_cnpj'   => $row['issuer_legal_id'],
            'issuer_level'  => (int) $row['issuer_level'],
            'holder_name'   => (string) $row['holder_name'],
            'holder_display'=> (string) $row['holder_display'],
            'issued_on'     => $row['issued_on'],
            'valid_from'    => $row['valid_from'],
            'valid_until'   => $row['valid_until'],
            'revoked_on'    => $row['revoked_on'],
            'revoke_reason' => $row['revoke_reason'],
            'version'       => (int) $row['version'],
            'superseded_by' => $row['superseded_by'],
            'hash'          => (string) $row['content_hash'],
            'anchored_on'   => $row['anchored_on'],
            'is_demo'       => (bool) $row['is_demo'],
        ];
        $record['state'] = self::state($record);
        $record['days_left'] = self::daysLeft($record);
        return $record;
    }

    /** Bases usadas como exemplo clicável na home. */
    public static function showcase(): array
    {
        try {
            $rows = Db::all(
                'SELECT code_base FROM issuances WHERE is_demo = 1 ORDER BY id ASC LIMIT 4'
            );
            return array_map(static fn (array $r): string => (string) $r['code_base'], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Dois relógios independentes:
     *   documento   -> revogado / substituído
     *   habilitação -> vigente / a vencer / vencida
     * Estado derivado de data nunca é gravado, sempre calculado.
     */
    public static function state(array $r): string
    {
        if (!empty($r['revoked_on'])) {
            return 'revoked';
        }
        if (empty($r['valid_until'])) {
            return 'verified';
        }
        $days = self::daysLeft($r);
        if ($days === null) {
            return 'verified';
        }
        if ($days < 0) {
            return 'expired';
        }
        return $days <= self::EXPIRING_WINDOW_DAYS ? 'expiring' : 'verified';
    }

    public static function daysLeft(array $r): ?int
    {
        if (empty($r['valid_until'])) {
            return null;
        }
        $zone = new DateTimeZone(APP_TZ);
        $today = new DateTimeImmutable('today', $zone);
        $until = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $r['valid_until'], $zone);
        if ($until === false) {
            return null;
        }
        return (int) $today->diff($until)->format('%r%a');
    }

    /**
     * Hash do conteúdo canônico. É este valor que entra na árvore de Merkle
     * do dia — nenhum dado pessoal sai da base, só o resumo.
     */
    public static function contentHash(string $base, array $r): string
    {
        return hash('sha256', implode('|', [
            $base,
            (string) ($r['holder_name'] ?? ''),
            (string) ($r['program_title'] ?? $r['program'] ?? ''),
            (string) ($r['hours'] ?? ''),
            (string) ($r['modality'] ?? ''),
            (string) ($r['issuer_name'] ?? ''),
            (string) ($r['issued_on'] ?? ''),
            (string) ($r['valid_until'] ?? ''),
        ]));
    }

    /** "Maria Souza Lima" -> "Maria S. L." */
    public static function displayName(array $r): string
    {
        $name = trim((string) $r['holder_name']);
        $mode = $r['holder_display'] ?? 'partial';
        if ($mode === 'full') {
            return $name;
        }
        if ($mode === 'hidden') {
            return '—';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) < 2) {
            return $name;
        }
        $first = array_shift($parts);
        $initials = array_map(
            static fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)) . '.',
            array_filter($parts, static fn (string $p): bool => mb_strlen($p) > 2)
        );
        return trim($first . ' ' . implode(' ', $initials));
    }

    /** Registra a consulta. Nunca pode derrubar a verificação. */
    public static function recordVerification(string $base, string $outcome, ?int $issuanceId, string $locale, string $channel = 'web'): void
    {
        try {
            Db::run(
                'INSERT INTO verification_events (issuance_id, code_base, outcome, channel, locale, ip_hash)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$issuanceId, $base, $outcome, $channel, $locale, client_fingerprint()]
            );
        } catch (Throwable) {
            // silêncio proposital
        }
    }

    public static function counters(): array
    {
        try {
            $certs = (int) Db::one('SELECT COUNT(*) AS n FROM issuances')['n'];
            $checks = (int) Db::one('SELECT COUNT(*) AS n FROM verification_events')['n'];
            return ['certificates' => $certs, 'verifications' => $checks];
        } catch (Throwable) {
            return ['certificates' => 0, 'verifications' => 0];
        }
    }
}
