<?php
declare(strict_types=1);

/**
 * Primeira dobra rotativa.
 *
 * A escolha da variação é aritmética de data: a cada 15 dias a lista avança
 * uma posição. Não há cron, não há estado gravado, não há o que corromper —
 * e duas visitas no mesmo dia veem a mesma coisa, o que é condição para a
 * medição significar alguma coisa.
 *
 * O que é gravado é só o desempenho: quantas vezes cada variação apareceu e
 * quantas levaram alguém a verificar um certificado.
 */
final class Hero
{
    public const DIAS_POR_CICLO = 15;
    /** Âncora fixa do calendário de rotação. */
    private const EPOCA = '2026-01-01';

    /** @return list<array<string, string>> */
    public static function variants(string $locale): array
    {
        foreach ([$locale, 'pt-br'] as $tentativa) {
            $arquivo = __DIR__ . '/../content/hero.' . $tentativa . '.php';
            if (is_file($arquivo)) {
                $lista = require $arquivo;
                if (is_array($lista) && $lista !== []) {
                    return array_values($lista);
                }
            }
        }
        return [];
    }

    /** Ciclo atual desde a época, para que a janela seja igual em qualquer idioma. */
    public static function cycle(): int
    {
        $zona = new DateTimeZone(APP_TZ);
        $hoje = new DateTimeImmutable('today', $zona);
        $epoca = new DateTimeImmutable(self::EPOCA, $zona);
        $dias = (int) $epoca->diff($hoje)->format('%r%a');
        return intdiv(max(0, $dias), self::DIAS_POR_CICLO);
    }

    /** @return array<string, string>|null */
    public static function current(string $locale): ?array
    {
        $lista = self::variants($locale);
        if ($lista === []) {
            return null;
        }
        $fixada = (string) Config::get('hero_pin', '');
        if ($fixada !== '') {
            foreach ($lista as $v) {
                if (($v['slug'] ?? '') === $fixada) {
                    return $v + ['fixada' => '1'];
                }
            }
        }
        return $lista[self::cycle() % count($lista)];
    }

    /** Dias que faltam para a próxima troca. */
    public static function daysLeft(): int
    {
        $zona = new DateTimeZone(APP_TZ);
        $hoje = new DateTimeImmutable('today', $zona);
        $epoca = new DateTimeImmutable(self::EPOCA, $zona);
        $dias = (int) $epoca->diff($hoje)->format('%r%a');
        return self::DIAS_POR_CICLO - (max(0, $dias) % self::DIAS_POR_CICLO);
    }

    /* ----------------------------------------------------------- medição */

    public static function recordImpression(string $slug, string $locale): void
    {
        self::bump($slug, $locale, 'impressions');
    }

    public static function recordConversion(string $slug, string $locale): void
    {
        self::bump($slug, $locale, 'conversions');
    }

    private static function bump(string $slug, string $locale, string $coluna): void
    {
        if ($slug === '' || !in_array($coluna, ['impressions', 'conversions'], true)) {
            return;
        }
        try {
            Db::run(
                "INSERT INTO hero_stats (variant_slug, locale, day, $coluna)
                 VALUES (?, ?, CURDATE(), 1)
                 ON DUPLICATE KEY UPDATE $coluna = $coluna + 1",
                [mb_substr($slug, 0, 40), mb_substr($locale, 0, 8)]
            );
        } catch (Throwable) {
            // medição nunca pode derrubar a página
        }
    }

    /** Desempenho acumulado por variação, da melhor para a pior. */
    public static function performance(): array
    {
        try {
            $linhas = Db::all(
                'SELECT variant_slug,
                        SUM(impressions) AS impressions,
                        SUM(conversions) AS conversions,
                        MIN(day) AS primeiro_dia,
                        MAX(day) AS ultimo_dia
                 FROM hero_stats GROUP BY variant_slug'
            );
        } catch (Throwable) {
            return [];
        }
        foreach ($linhas as &$l) {
            $imp = (int) $l['impressions'];
            $l['impressions'] = $imp;
            $l['conversions'] = (int) $l['conversions'];
            $l['taxa'] = $imp > 0 ? round($l['conversions'] / $imp * 100, 2) : 0.0;
        }
        usort($linhas, static fn (array $a, array $b): int => $b['taxa'] <=> $a['taxa']);
        return $linhas;
    }
}
