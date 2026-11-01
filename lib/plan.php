<?php
declare(strict_types=1);

/**
 * Planos e medição de uso.
 *
 * Enquanto a cobrança não existe, a faixa gratuita é o único plano — e ela é
 * limitada desde o primeiro dia. Abrir cadastro sem limite seria abrir conta
 * ilimitada de graça, e retirar depois o que já foi dado é pior do que nunca
 * ter dado.
 *
 * A medição é por competência (mês civil), contando **emissões no período** e
 * não certificados ativos. Cobrar por ativo puniria o cliente que mantém o
 * histórico no registro, que é exatamente o comportamento que queremos: um
 * registro com buracos não serve para conferir nada.
 *
 * Os dois números abaixo foram decididos com o Tulio em 2026-11-01. Vivem em
 * constante, e não em tabela, porque enquanto só existe um plano uma tabela
 * de planos seria uma linha só com cara de arquitetura.
 */
final class Plan
{
    /** Faixa gratuita: emissões por competência. */
    public const FREE_MONTHLY = 50;

    /** A partir daqui o cliente pode pôr a marca dele na verificação. */
    public const BRAND_THRESHOLD = 500;

    /** Meses olhados para trás ao decidir o direito à marca. */
    public const BRAND_LOOKBACK = 3;

    /** Avisa o cliente a partir desta fração da faixa. */
    public const WARN_AT = 0.8;

    public static function competencia(?string $date = null): string
    {
        return substr($date ?? date('Y-m-d'), 0, 7);
    }

    /** Emissões e registros somados na competência. */
    public static function used(int $tenantId, ?string $competencia = null): int
    {
        $row = Db::one(
            'SELECT issuances + registrations AS total FROM usage_counters
              WHERE tenant_id = ? AND competencia = ? LIMIT 1',
            [$tenantId, $competencia ?? self::competencia()]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Registra uma entrada no contador.
     *
     * Chamada de dentro da transação da emissão: se a emissão não gravar, o
     * contador não anda. Contador que anda sem certificado cobra pelo que não
     * existe, e é o tipo de erro que o cliente descobre antes de nós.
     */
    public static function record(int $tenantId, string $kind, ?string $date = null): void
    {
        $coluna = $kind === 'registered' ? 'registrations' : 'issuances';
        Db::run(
            "INSERT INTO usage_counters (tenant_id, competencia, $coluna)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE $coluna = $coluna + 1",
            [$tenantId, self::competencia($date)]
        );
    }

    public static function limit(int $tenantId): int
    {
        $row = Db::one('SELECT plan FROM tenants WHERE id = ? LIMIT 1', [$tenantId]);
        $plano = (string) ($row['plan'] ?? 'free');

        // Um único plano hoje. 'unlimited' existe para a conta da própria
        // Inspire4U e para quem for liberado na mão enquanto não há cobrança.
        return $plano === 'unlimited' ? PHP_INT_MAX : self::FREE_MONTHLY;
    }

    public static function remaining(int $tenantId): int
    {
        $limite = self::limit($tenantId);

        return $limite === PHP_INT_MAX ? PHP_INT_MAX : max(0, $limite - self::used($tenantId));
    }

    public static function exhausted(int $tenantId): bool
    {
        return self::remaining($tenantId) <= 0;
    }

    public static function nearLimit(int $tenantId): bool
    {
        $limite = self::limit($tenantId);
        if ($limite === PHP_INT_MAX) {
            return false;
        }

        return self::used($tenantId) >= (int) floor($limite * self::WARN_AT);
    }

    /**
     * Direito à marca do cliente na página de verificação.
     *
     * Olha para trás, nunca para a frente: basta um mês dos últimos três ter
     * passado do limiar. Quem já provou o volume não perde a marca por causa
     * de um mês fraco — tirar a marca do ar seria quebrar o selo instalado no
     * site dele por um motivo que ele não controla.
     */
    public static function brandEligible(int $tenantId): bool
    {
        $row = Db::one(
            'SELECT MAX(issuances + registrations) AS pico FROM usage_counters
              WHERE tenant_id = ? AND competencia >= ?',
            [$tenantId, self::competencia(date('Y-m-d', strtotime('-' . self::BRAND_LOOKBACK . ' months')))]
        );

        return (int) ($row['pico'] ?? 0) >= self::BRAND_THRESHOLD;
    }

    /** @return array{used:int,limit:int,remaining:int,competencia:string,unlimited:bool} */
    public static function summary(int $tenantId): array
    {
        $limite = self::limit($tenantId);
        $usado = self::used($tenantId);

        return [
            'used'        => $usado,
            'limit'       => $limite,
            'remaining'   => $limite === PHP_INT_MAX ? PHP_INT_MAX : max(0, $limite - $usado),
            'competencia' => self::competencia(),
            'unlimited'   => $limite === PHP_INT_MAX,
        ];
    }
}
