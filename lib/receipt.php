<?php
declare(strict_types=1);

/**
 * Comprovante de autenticação: o retrato de uma consulta.
 *
 * A diferença entre a página de verificação e o comprovante não é de formato,
 * é de tempo. A página responde agora. O comprovante responde pelo instante
 * em que foi tirado — e por isso guarda o retrato em vez de recalcular.
 *
 * É a razão pela qual o selo 4 não diz "verificação ativa": no papel não há
 * verificação ativa nenhuma. O que há é a data, a hora com fuso, e o convite
 * explícito a reconsultar. Um comprovante que se dissesse "ativo" mentiria no
 * dia seguinte à revogação, que é justamente o dia em que ele mais importa.
 *
 * O código do comprovante usa o mesmo esquema do código do certificado —
 * Base32 de Crockford com verificador Luhn mod 32 — porque um segundo esquema
 * de código seria um segundo lugar para errar. O prefixo CP na exibição é o
 * que impede confundir um com o outro ao ler em voz alta.
 */
final class Receipt
{
    public const PREFIX = 'CP';

    /**
     * Tira o retrato e devolve o código do comprovante.
     *
     * @param ?array $record registro verificado, ou null quando não há
     */
    public static function mint(string $codeBase, string $outcome, ?array $record, string $locale): ?string
    {
        for ($try = 0; $try < 8; $try++) {
            $base = Code::generateBase();
            try {
                Db::run(
                    'INSERT INTO receipts (receipt_base, code_base, issuance_id, outcome, locale, snapshot)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [
                        $base,
                        $codeBase,
                        isset($record['id']) ? (int) $record['id'] : null,
                        $outcome,
                        $locale,
                        json_encode(self::snapshot($record), JSON_UNESCAPED_UNICODE),
                    ]
                );

                return $base;
            } catch (Throwable $e) {
                // Código sorteado já existe: sorteia outro. 32^11 bases tornam
                // isto praticamente impossível, mas praticamente não é nunca.
                continue;
            }
        }

        return null;
    }

    /**
     * O que entra no retrato.
     *
     * Entra o que o comprovante afirma, e nada além. O hash do conteúdo entra
     * porque é o que torna o retrato conferível depois; o nome do titular entra
     * já abreviado, do mesmo jeito que aparece na verificação — o comprovante
     * não é lugar para revelar o que a página esconde.
     */
    private static function snapshot(?array $record): array
    {
        if ($record === null) {
            return [];
        }

        return [
            'code'          => $record['code'] ?? null,
            'state'         => $record['state'] ?? null,
            'origin'        => $record['origin'] ?? null,
            'holder'        => Store::displayName($record),
            'program'       => $record['program'] ?? null,
            'norm'          => $record['norm'] ?? null,
            'hours'         => $record['hours'] ?? null,
            'modality'      => $record['modality'] ?? null,
            'assessed'      => $record['assessed'] ?? null,
            'issuer_name'   => $record['issuer_name'] ?? null,
            'issuer_cnpj'   => $record['issuer_cnpj'] ?? null,
            'issuer_level'  => $record['issuer_level'] ?? null,
            'registered_by' => $record['registered_by'] ?? null,
            'issued_on'     => $record['issued_on'] ?? null,
            'valid_until'   => $record['valid_until'] ?? null,
            'revoked_on'    => $record['revoked_on'] ?? null,
            'revoke_reason' => $record['revoke_reason'] ?? null,
            'days_left'     => $record['days_left'] ?? null,
            'hash'          => $record['hash'] ?? null,
            'is_demo'       => !empty($record['is_demo']),
        ];
    }

    /** @return ?array{base:string,code:string,code_base:string,outcome:string,locale:string,checked_at:string,snapshot:array} */
    public static function find(string $base): ?array
    {
        $row = Db::one(
            'SELECT receipt_base, code_base, outcome, locale, snapshot, checked_at
               FROM receipts WHERE receipt_base = ? LIMIT 1',
            [$base]
        );
        if ($row === null) {
            return null;
        }

        $snap = json_decode((string) ($row['snapshot'] ?? '[]'), true);

        return [
            'base'       => (string) $row['receipt_base'],
            'code'       => Code::full((string) $row['code_base']),
            'code_base'  => (string) $row['code_base'],
            'outcome'    => (string) $row['outcome'],
            'locale'     => (string) $row['locale'],
            'checked_at' => (string) $row['checked_at'],
            'snapshot'   => is_array($snap) ? $snap : [],
        ];
    }

    /** CP 4K7M-93FQ-5XB1 — o prefixo é o que separa do código do certificado. */
    public static function display(string $base): string
    {
        return self::PREFIX . ' ' . Code::format(Code::full($base));
    }

    /** Aceita "CP 4K7M-93FQ-5XB1", "cp4k7m93fq5xb1" e o código cru. */
    public static function normalize(string $raw): string
    {
        $s = Code::normalize($raw);

        // Só tira o prefixo quando ele é prefixo de verdade. C e P estão no
        // alfabeto: um código de comprovante pode começar com CP, e cortar
        // dois caracteres de um código válido inventaria um código inválido.
        if (strlen($s) === Code::FULL_LENGTH + 2 && strncmp($s, 'CP', 2) === 0) {
            $s = substr($s, 2);
        }

        return $s;
    }
}
