<?php
declare(strict_types=1);

/**
 * Fonte de dados dos registros.
 *
 * Nesta primeira entrega os registros são de DEMONSTRAÇÃO e vivem em memória.
 * A interface pública (find / state) é a mesma que a camada MySQL vai expor,
 * então trocar daqui para o banco não muda nada acima desta linha.
 *
 * Instituições, pessoas e números abaixo são fictícios e estão marcados como
 * demonstração em toda a interface.
 */
final class Store
{
    public const DEMO = true;

    /** Janela de aviso antes do vencimento, em dias. */
    public const EXPIRING_WINDOW_DAYS = 90;

    /** @return array<string, array<string, mixed>> indexado pela base de 11 caracteres */
    public static function all(): array
    {
        return [
            'K7M293FQ5XB' => [
                'holder_name'   => 'Maria Souza Lima',
                'holder_display'=> 'partial',
                'program'       => 'NR-35 — Trabalho em Altura',
                'norm'          => 'NR-35',
                'hours'         => 8,
                'modality'      => 'presencial',
                'assessed'      => true,
                'issuer_name'   => 'Centro de Treinamento Alvorada',
                'issuer_cnpj'   => '**.***.***/0001-90',
                'issuer_level'  => 3,
                'issued_on'     => '2026-03-10',
                'valid_from'    => '2026-03-10',
                'valid_until'   => '2028-03-10',
                'revoked_on'    => null,
                'revoke_reason' => null,
                'anchored_on'   => '2026-03-10',
            ],
            'H4T8R2WX1NQ' => [
                'holder_name'   => 'João Carlos Ferreira',
                'holder_display'=> 'partial',
                'program'       => 'NR-33 — Espaço Confinado, Trabalhador Autorizado',
                'norm'          => 'NR-33',
                'hours'         => 16,
                'modality'      => 'presencial',
                'assessed'      => true,
                'issuer_name'   => 'Escola Técnica Horizonte',
                'issuer_cnpj'   => '**.***.***/0001-24',
                'issuer_level'  => 2,
                'issued_on'     => '2025-12-05',
                'valid_from'    => '2025-12-05',
                'valid_until'   => '2026-12-05',
                'revoked_on'    => null,
                'revoke_reason' => null,
                'anchored_on'   => '2025-12-05',
            ],
            'B9CD5K3ZY7M' => [
                'holder_name'   => 'Ana Paula Rodrigues',
                'holder_display'=> 'partial',
                'program'       => 'NR-10 — Segurança em Instalações e Serviços em Eletricidade (Básico)',
                'norm'          => 'NR-10',
                'hours'         => 40,
                'modality'      => 'presencial',
                'assessed'      => true,
                'issuer_name'   => 'Instituto Meridiano de Educação Profissional',
                'issuer_cnpj'   => '**.***.***/0001-07',
                'issuer_level'  => 3,
                'issued_on'     => '2023-04-18',
                'valid_from'    => '2023-04-18',
                'valid_until'   => '2025-04-18',
                'revoked_on'    => null,
                'revoke_reason' => null,
                'anchored_on'   => '2023-04-18',
            ],
            'P2V6S8GJ4TR' => [
                'holder_name'   => 'Roberto Nunes',
                'holder_display'=> 'partial',
                'program'       => 'Operação de Empilhadeira — Capacitação Inicial',
                'norm'          => 'NR-11',
                'hours'         => 16,
                'modality'      => 'hibrido',
                'assessed'      => false,
                'issuer_name'   => 'Escola Técnica Horizonte',
                'issuer_cnpj'   => '**.***.***/0001-24',
                'issuer_level'  => 2,
                'issued_on'     => '2026-05-22',
                'valid_from'    => '2026-05-22',
                'valid_until'   => '2027-05-22',
                'revoked_on'    => '2026-09-02',
                'revoke_reason' => 'Emitido por engano para pessoa que não concluiu a avaliação prática.',
                'anchored_on'   => '2026-05-22',
            ],
            'X5N1QW9D3FH' => [
                'holder_name'   => 'Luciana Teixeira de Andrade',
                'holder_display'=> 'full',
                'program'       => 'Gestão de Riscos Psicossociais na NR-1',
                'norm'          => null,
                'hours'         => 60,
                'modality'      => 'ead',
                'assessed'      => true,
                'issuer_name'   => 'Instituto Meridiano de Educação Profissional',
                'issuer_cnpj'   => '**.***.***/0001-07',
                'issuer_level'  => 3,
                'issued_on'     => '2026-07-14',
                'valid_from'    => '2026-07-14',
                'valid_until'   => null,
                'revoked_on'    => null,
                'revoke_reason' => null,
                'anchored_on'   => '2026-07-14',
            ],
        ];
    }

    /** Bases usadas como exemplo clicável na home. */
    public static function showcase(): array
    {
        return ['K7M293FQ5XB', 'H4T8R2WX1NQ', 'B9CD5K3ZY7M', 'P2V6S8GJ4TR'];
    }

    public static function find(string $base): ?array
    {
        $record = self::all()[$base] ?? null;
        if ($record === null) {
            return null;
        }
        $record['base'] = $base;
        $record['code'] = Code::full($base);
        $record['state'] = self::state($record);
        $record['days_left'] = self::daysLeft($record);
        $record['hash'] = self::hash($base, $record);
        return $record;
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
        if ($days <= self::EXPIRING_WINDOW_DAYS) {
            return 'expiring';
        }
        return 'verified';
    }

    public static function daysLeft(array $r): ?int
    {
        if (empty($r['valid_until'])) {
            return null;
        }
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
        $until = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $r['valid_until'], new DateTimeZone('America/Sao_Paulo'));
        if ($until === false) {
            return null;
        }
        return (int) $today->diff($until)->format('%r%a');
    }

    /**
     * Hash real do conteúdo canônico do registro. É este valor que entraria
     * na árvore de Merkle do dia — nenhum dado pessoal sai da base, só o hash.
     */
    public static function hash(string $base, array $r): string
    {
        $canonical = implode('|', [
            $base,
            (string) $r['holder_name'],
            (string) $r['program'],
            (string) $r['hours'],
            (string) $r['modality'],
            (string) $r['issuer_name'],
            (string) $r['issued_on'],
            (string) ($r['valid_until'] ?? ''),
        ]);
        return hash('sha256', $canonical);
    }

    /** "Maria Souza Lima" -> "Maria S. L." */
    public static function displayName(array $r): string
    {
        $name = trim((string) $r['holder_name']);
        if (($r['holder_display'] ?? 'partial') === 'full') {
            return $name;
        }
        if (($r['holder_display'] ?? 'partial') === 'hidden') {
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
}
