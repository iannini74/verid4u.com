<?php
declare(strict_types=1);

/**
 * Blog.
 *
 * Artigos em arquivos versionados, pela mesma razão das variações da
 * primeira dobra: texto é conteúdo, não dado. Cada publicação passa por
 * commit, tem histórico e pode ser revertida — e não foi preciso construir
 * um editor para escrever artigo.
 *
 * Cada artigo carrega 'verificado_em'. Texto sobre norma apodrece: quando a
 * NR-35 passou a exigir modalidade presencial, todo artigo anterior que
 * dizia «pode ser EAD» virou desinformação e continua no ar por aí. A data
 * de verificação, visível, é o que separa um artigo mantido de um artigo
 * abandonado.
 */
final class Blog
{
    /** @return list<array<string, mixed>> do mais recente para o mais antigo */
    public static function all(string $locale): array
    {
        $dir = __DIR__ . '/../content/blog';
        if (!is_dir($dir)) {
            return [];
        }
        $artigos = [];
        foreach (glob($dir . '/*.php') ?: [] as $arquivo) {
            $base = basename($arquivo, '.php');
            if (!str_ends_with($base, '.' . $locale) && !str_ends_with($base, '.pt-br')) {
                continue;
            }
            $slug = preg_replace('/\.[a-z-]+$/', '', $base) ?? $base;
            // o idioma pedido tem prioridade sobre o padrão
            $preferido = $dir . '/' . $slug . '.' . $locale . '.php';
            $escolhido = is_file($preferido) ? $preferido : $arquivo;
            if (isset($artigos[$slug]) && $escolhido !== $preferido) {
                continue;
            }
            $d = require $escolhido;
            if (!is_array($d) || empty($d['titulo'])) {
                continue;
            }
            $d['slug'] = $slug;
            $artigos[$slug] = $d;
        }
        $lista = array_values($artigos);
        usort($lista, static fn (array $a, array $b): int => strcmp((string) $b['publicado_em'], (string) $a['publicado_em']));
        return array_values(array_filter($lista, static fn (array $a): bool => ($a['publicado_em'] ?? '9999') <= date('Y-m-d')));
    }

    public static function find(string $slug, string $locale): ?array
    {
        if (preg_match('/^[a-z0-9-]{3,80}$/', $slug) !== 1) {
            return null;
        }
        foreach ([$locale, 'pt-br'] as $tentativa) {
            $f = __DIR__ . '/../content/blog/' . $slug . '.' . $tentativa . '.php';
            if (is_file($f)) {
                $d = require $f;
                if (is_array($d) && ($d['publicado_em'] ?? '9999') <= date('Y-m-d')) {
                    $d['slug'] = $slug;
                    return $d;
                }
            }
        }
        return null;
    }

    /** Converte o corpo em HTML. Escapa primeiro, marca depois. */
    public static function html(array $blocos): string
    {
        $out = '';
        foreach ($blocos as $b) {
            $tipo = (string) ($b['t'] ?? 'p');
            $texto = (string) ($b['v'] ?? '');
            $seguro = self::inline($texto);
            $out .= match ($tipo) {
                'h'     => '<h2>' . $seguro . '</h2>',
                'h3'    => '<h3>' . $seguro . '</h3>',
                'nota'  => '<aside class="post-note">' . $seguro . '</aside>',
                'lista' => '<ul>' . implode('', array_map(
                               static fn ($i): string => '<li>' . self::inline((string) $i) . '</li>',
                               (array) ($b['itens'] ?? [])
                           )) . '</ul>',
                'tabela'=> self::tabela((array) ($b['cabecalho'] ?? []), (array) ($b['linhas'] ?? [])),
                default => '<p>' . $seguro . '</p>',
            };
        }
        return $out;
    }

    private static function inline(string $texto): string
    {
        $s = e($texto);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
        // [texto](url) — só http(s), para o link não virar vetor de injeção
        return preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u',
            static fn (array $m): string => '<a href="' . $m[2] . '" rel="noopener">' . $m[1] . '</a>',
            $s
        ) ?? $s;
    }

    private static function tabela(array $cabecalho, array $linhas): string
    {
        $h = implode('', array_map(static fn ($c): string => '<th>' . self::inline((string) $c) . '</th>', $cabecalho));
        $corpo = '';
        foreach ($linhas as $linha) {
            $corpo .= '<tr>' . implode('', array_map(
                static fn ($c): string => '<td>' . self::inline((string) $c) . '</td>',
                (array) $linha
            )) . '</tr>';
        }
        return '<div class="post-table"><table><thead><tr>' . $h . '</tr></thead><tbody>' . $corpo . '</tbody></table></div>';
    }
}
