<?php
declare(strict_types=1);

/**
 * Catálogo de planos e cupons.
 *
 * Preço vive em tabela e em centavos. Em tabela porque mudar preço não pode
 * exigir publicar código; em centavos porque dinheiro em ponto flutuante é
 * como a conta fecha com um centavo de diferença e ninguém descobre de onde
 * veio.
 *
 * O desconto é sempre calculado a partir do preço vigente — promocional
 * quando há promoção na data, cheio quando não há. Cupom sobre preço cheio
 * em cima de promoção já vigente seria desconto dobrado sem ninguém ter
 * decidido isso.
 */
final class Catalog
{
    /* ------------------------------------------------------------ planos */

    /** @return list<array<string,mixed>> */
    public static function plans(): array
    {
        return Db::all('SELECT * FROM plans WHERE active = 1 ORDER BY sort_order ASC');
    }

    public static function plan(string $code): ?array
    {
        return Db::one('SELECT * FROM plans WHERE code = ? AND active = 1 LIMIT 1', [$code]);
    }

    /**
     * Preço vigente de um plano, em centavos, para o ciclo pedido.
     *
     * @return array{cents:int,full:int,promo:bool}
     */
    public static function price(array $plan, string $cycle = 'monthly'): array
    {
        if ($cycle === 'yearly') {
            $cheio = (int) ($plan['yearly_cents'] ?? 0);

            return ['cents' => $cheio, 'full' => $cheio, 'promo' => false];
        }

        $cheio = (int) $plan['price_cents'];
        $promo = $plan['promo_cents'] === null ? null : (int) $plan['promo_cents'];
        $ate = $plan['promo_until'] ?? null;
        $vigente = $promo !== null && ($ate === null || $ate >= date('Y-m-d'));

        return [
            'cents' => $vigente ? $promo : $cheio,
            'full'  => $cheio,
            'promo' => $vigente,
        ];
    }

    public static function money(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    /* ------------------------------------------------------------ cupons */

    /**
     * Confere um cupom contra um plano e um cliente.
     *
     * @return array{ok:bool,error:?string,coupon:?array,discount:int,final:int}
     */
    public static function checkCoupon(string $code, array $plan, string $cycle, ?int $tenantId): array
    {
        $preco = self::price($plan, $cycle);
        $vazio = ['ok' => false, 'coupon' => null, 'discount' => 0, 'final' => $preco['cents']];

        $code = strtoupper(trim($code));
        if ($code === '') {
            return $vazio + ['error' => 'coupon.empty'];
        }

        $c = Db::one('SELECT * FROM coupons WHERE code = ? AND active = 1 LIMIT 1', [$code]);
        if ($c === null) {
            return $vazio + ['error' => 'coupon.unknown'];
        }

        $hoje = date('Y-m-d');
        if ($c['starts_on'] !== null && $hoje < $c['starts_on']) {
            return $vazio + ['error' => 'coupon.not_yet'];
        }
        if ($c['ends_on'] !== null && $hoje > $c['ends_on']) {
            return $vazio + ['error' => 'coupon.expired'];
        }
        if ($c['plan_code'] !== null && $c['plan_code'] !== $plan['code']) {
            return $vazio + ['error' => 'coupon.other_plan'];
        }
        if ($c['max_redemptions'] !== null && (int) $c['redemptions'] >= (int) $c['max_redemptions']) {
            return $vazio + ['error' => 'coupon.used_up'];
        }
        if ($tenantId !== null) {
            $usos = Db::one(
                'SELECT COUNT(*) AS n FROM coupon_redemptions WHERE coupon_id = ? AND tenant_id = ?',
                [(int) $c['id'], $tenantId]
            );
            if ((int) ($usos['n'] ?? 0) >= (int) $c['per_tenant_limit']) {
                return $vazio + ['error' => 'coupon.already_used'];
            }
        }

        // O desconto incide sobre o preço vigente, nunca sobre o cheio: somar
        // cupom a uma promoção em curso seria desconto dobrado que ninguém
        // decidiu dar.
        $base = $preco['cents'];
        $desconto = $c['kind'] === 'percent'
            ? (int) floor($base * min(100, (int) $c['value']) / 100)
            : min($base, (int) $c['value']);

        return [
            'ok'       => true,
            'error'    => null,
            'coupon'   => $c,
            'discount' => $desconto,
            'final'    => max(0, $base - $desconto),
        ];
    }

    public static function redeem(int $couponId, int $tenantId): void
    {
        Db::run('INSERT INTO coupon_redemptions (coupon_id, tenant_id) VALUES (?, ?)', [$couponId, $tenantId]);
        Db::run('UPDATE coupons SET redemptions = redemptions + 1 WHERE id = ?', [$couponId]);
    }

    /* ----------------------------------------------------------- assinatura */

    public static function subscription(int $tenantId): ?array
    {
        return Db::one('SELECT * FROM subscriptions WHERE tenant_id = ? LIMIT 1', [$tenantId]);
    }

    /**
     * O plano que vale para este cliente agora.
     *
     * Assinatura vencida ou cancelada cai para o gratuito — e cair para o
     * gratuito limita emitir, nunca verificar. Quem parou de pagar continua
     * com todos os certificados dele conferíveis para sempre: um registro que
     * apaga quem saiu não é registro, é refém.
     */
    public static function currentPlan(int $tenantId): array
    {
        $assinatura = self::subscription($tenantId);
        $viva = $assinatura !== null
            && in_array((string) $assinatura['status'], ['active', 'trialing'], true)
            && ($assinatura['current_period_end'] === null || $assinatura['current_period_end'] >= date('Y-m-d'));

        $code = $viva ? (string) $assinatura['plan_code'] : 'free';

        return self::plan($code) ?? self::plan('free') ?? [
            'code' => 'free', 'name' => 'Gratuito', 'monthly_quota' => Plan::FREE_MONTHLY,
            'price_cents' => 0, 'promo_cents' => null, 'promo_until' => null,
            'yearly_cents' => 0, 'brand_included' => 0,
        ];
    }
}
