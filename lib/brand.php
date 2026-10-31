<?php
declare(strict_types=1);

/**
 * A marca VerID4U e os quatro selos, num só lugar.
 *
 * A marca é um bloco com uma cunha faltando e a cunha ao lado. Duas posições,
 * e a diferença entre elas é toda a semântica do produto:
 *
 *   cunha solta     o registro existe          (registrado)
 *   cunha encaixada foi conferido agora        (conferido)
 *
 * A fresta nunca fecha de todo — no mínimo 4% da altura. Se fechasse, a marca
 * passaria a ser um bloco só, e o olho perderia a prova de que são duas peças.
 *
 * Geometria em caixa de 100×100. O bloco entalhado é um único path, sem máscara:
 * assim a marca atravessa tudo (favicon, e-mail, SVG servido como imagem, PDF
 * impresso) sem depender de referência a id, que é o que costuma quebrar.
 */
final class Brand
{
    /** Bloco com o entalhe triangular recortado na borda direita. */
    public const BLOCK = 'M8 16H62V24L40 50L62 76V84H8Z';

    /** Cunha solta, à direita do bloco. */
    public const WEDGE_OPEN = 'M94 24L72 50L94 76Z';

    /** Cunha encaixada. A 5 unidades do entalhe: a fresta que prova duas peças. */
    public const WEDGE_FIT = 'M66 27L44 50L66 73Z';

    /** Ramos de atuação do emissor e o qualificador que cada um imprime no selo. */
    public const CATEGORIES = ['ensino', 'treinamento', 'ambos', 'participacao', 'profissional'];

    public const CATEGORY_DEFAULT = 'ambos';

    /* ----------------------------------------------------------- a marca */

    /**
     * A marca, em SVG embutido, herdando currentColor.
     *
     * @param string  $state 'open' (registrado) ou 'fit' (conferido)
     * @param string  $class classe CSS do <svg>
     * @param ?string $label rótulo acessível; null deixa o SVG decorativo
     */
    public static function mark(string $state = 'open', string $class = 'brand-mark', ?string $label = null): string
    {
        $fit = $state === 'fit';
        $wedge = $fit ? self::WEDGE_FIT : self::WEDGE_OPEN;
        $a11y = $label === null
            ? ' aria-hidden="true" focusable="false"'
            : ' role="img" aria-label="' . e($label) . '"';

        // viewBox colado no desenho: a altura definida no CSS é a altura que
        // aparece, e a largura acompanha. Encaixada a marca é mais estreita,
        // porque a cunha entrou — é a própria ideia, não um defeito de caixa.
        $box = $fit ? '8 16 58 68' : '8 16 86 68';

        return '<svg class="' . e($class) . '" viewBox="' . $box . '"' . $a11y . '>'
            . '<path d="' . self::BLOCK . '" fill="currentColor"/>'
            . '<path d="' . $wedge . '" fill="currentColor"/>'
            . '</svg>';
    }

    /** A marca como documento SVG autônomo, para arquivo em disco. */
    public static function markFile(string $state, string $color, string $label): string
    {
        $fit = $state === 'fit';
        $wedge = $fit ? self::WEDGE_FIT : self::WEDGE_OPEN;
        $box = $fit ? '8 16 58 68' : '8 16 86 68';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $box . '" role="img" aria-label="' . e($label) . '">'
            . "\n" . '  <path d="' . self::BLOCK . '" fill="' . $color . '"/>'
            . "\n" . '  <path d="' . $wedge . '" fill="' . $color . '"/>'
            . "\n" . '</svg>' . "\n";
    }

    /* --------------------------------------------------- o qualificador */

    /** "EMISSOR DE CERTIFICADO DE ENSINO E TREINAMENTO" e afins. */
    public static function qualifier(string $category, I18n $i18n): string
    {
        $cat = in_array($category, self::CATEGORIES, true) ? $category : self::CATEGORY_DEFAULT;

        return $i18n->t('badge.qualifier.' . $cat);
    }

    /* ------------------------------------------------ largura do texto */

    /**
     * Largura aproximada de uma linha, em unidades do viewBox.
     *
     * O selo é servido como imagem: não há como carregar nossa fonte, então
     * o texto sai na fonte do sistema de quem olha — e a largura varia por
     * plataforma. A conta abaixo estima, e o <text> sai com textLength fixo,
     * o que obriga o desenho a caber na caixa em qualquer máquina. Erro de
     * alguns por cento vira um aperto imperceptível; sem isso, o selo
     * estouraria a borda em metade dos sistemas.
     */
    public static function textWidth(string $text, float $size, float $tracking = 0.0): float
    {
        static $narrow = ['i' => 1, 'l' => 1, 'j' => 1, 'I' => 1, 't' => 1, 'f' => 1, 'r' => 1, '.' => 1, ',' => 1, ':' => 1, ';' => 1, "'" => 1, '!' => 1, '|' => 1, ' ' => 1];
        static $wide = ['m' => 1, 'w' => 1, 'M' => 1, 'W' => 1, '@' => 1];

        $units = 0.0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($chars as $ch) {
            if (isset($narrow[$ch])) {
                $units += 0.30;
            } elseif (isset($wide[$ch])) {
                $units += 0.86;
            } elseif ($ch === mb_strtoupper($ch) && $ch !== mb_strtolower($ch)) {
                $units += 0.66;   // maiúscula
            } else {
                $units += 0.53;   // minúscula e dígito
            }
        }

        return $units * $size + max(0.0, (float) count($chars) - 1) * $tracking;
    }

    private static function line(
        string $text,
        float $x,
        float $y,
        float $size,
        int $weight,
        string $fill,
        float $tracking = 0.0,
        float $opacity = 1.0
    ): string {
        $len = self::textWidth($text, $size, $tracking);
        $attrs = 'x="' . self::n($x) . '" y="' . self::n($y) . '"'
            . ' font-size="' . self::n($size) . '" font-weight="' . $weight . '"'
            . ' fill="' . $fill . '" textLength="' . self::n($len) . '" lengthAdjust="spacingAndGlyphs"';

        if ($tracking > 0) {
            $attrs .= ' letter-spacing="' . self::n($tracking) . '"';
        }
        if ($opacity < 1.0) {
            $attrs .= ' opacity="' . self::n($opacity) . '"';
        }

        return '<text ' . $attrs . '>' . e($text) . '</text>';
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private const FONT = "system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    /** Largura e altura da caixa do desenho, por estado, em unidades de 100. */
    public const BOX = ['open' => [86.0, 68.0], 'fit' => [58.0, 68.0]];

    /**
     * A marca posicionada pela caixa do desenho.
     *
     * O desenho não começa em 0,0 — o bloco abre em x=8, y=16. Posicionar pela
     * origem do viewBox é como alinhar um texto pela margem da folha: funciona
     * até alguém trocar o estado da cunha e a largura mudar. Aqui entra onde o
     * desenho de fato começa, e a conta de centralizar fecha.
     */
    private static function markGroup(string $state, float $x, float $y, float $scale, string $fill): string
    {
        $wedge = $state === 'fit' ? self::WEDGE_FIT : self::WEDGE_OPEN;
        $tx = $x - 8.0 * $scale;
        $ty = $y - 16.0 * $scale;

        return '<g transform="translate(' . self::n($tx) . ' ' . self::n($ty) . ') scale(' . self::n($scale) . ')">'
            . '<path d="' . self::BLOCK . '" fill="' . $fill . '"/>'
            . '<path d="' . $wedge . '" fill="' . $fill . '"/>'
            . '</g>';
    }

    /* ---------------------------------------------- selo 1: do emissor */

    /**
     * O selo que o emissor põe no site dele.
     *
     * Servido pelo nosso domínio e não distribuído como arquivo: se o selo
     * fosse um PNG que qualquer um baixa, provaria exatamente nada. Vindo
     * daqui, ele só existe enquanto o emissor existir no registro — e é por
     * isso que a linha "verificação ativa" pode ser dita com honestidade.
     *
     * @param array{name:string,verified:string,qualifier:string,active:string,active_short:string,fit:bool} $d
     */
    public static function issuerBadge(array $d, string $variant = 'horizontal', bool $dark = false): string
    {
        $ink   = $dark ? '#F2F6FC' : '#0A1020';
        $muted = $dark ? '#9AA7B8' : '#566376';
        $brand = $dark ? '#8DB2FA' : '#1D5BE0';
        $bg    = $dark ? '#0C1119' : '#FFFFFF';
        $line  = $dark ? '#2E3F52' : '#D8E0EA';
        $live  = $dark ? '#4FC395' : '#0E7C5A';

        return $variant === 'compacto'
            ? self::badgeCompact($d, $ink, $muted, $brand, $bg, $line, $live)
            : self::badgeHorizontal($d, $ink, $muted, $brand, $bg, $line, $live);
    }

    private static function badgeHorizontal(array $d, string $ink, string $muted, string $brand, string $bg, string $line, string $live): string
    {
        $h = 64.0;
        $padL = 13.0;
        $scale = 0.50;
        $estado = !empty($d['fit']) ? 'fit' : 'open';
        [$markW, $markH] = self::BOX[$estado];
        $markW *= $scale;
        $markH *= $scale;
        $textX = $padL + $markW + 12.0;

        $l1 = (string) $d['verified'];
        $l2 = (string) $d['qualifier'];
        $l3 = (string) $d['active'];

        // O qualificador saiu de 7,8 para 9,2 depois de medir: a 44px de altura
        // de uso, 7,8 em 64 unidades dava 5,4px de letra maiúscula. Era enfeite,
        // não informação — e o qualificador é justamente o que diz de que tipo
        // de certificado se trata. Custa 11% de largura e passa a ser lido.
        $w1 = self::textWidth($l1, 13.5, 0);
        $w2 = self::textWidth($l2, 9.2, 0.40);
        $w3 = self::textWidth($l3, 9.2, 0) + 11.0;   // mais o ponto verde
        $w = $textX + max($w1, $w2, $w3) + 14.0;

        $svg = self::open($w, $h, (string) $d['name'] . ' — ' . $l1);
        $svg .= '<rect x=".6" y=".6" width="' . self::n($w - 1.2) . '" height="' . self::n($h - 1.2) . '"'
             . ' rx="9" fill="' . $bg . '" stroke="' . $line . '" stroke-width="1.2"/>';

        // A geometria carrega a mesma distinção que o texto: encaixada quando
        // o emissor foi conferido, solta quando ele apenas registra aqui. Se a
        // cunha encaixasse nos dois casos, a forma estaria afirmando mais do
        // que a linha de cima afirma — e seria a forma que o olho lê primeiro.
        $svg .= self::markGroup($estado, $padL, ($h - $markH) / 2, $scale, $brand);

        $svg .= self::line($l1, $textX, 23.5, 13.5, 700, $ink);
        $svg .= self::line($l2, $textX, 38.0, 9.2, 700, $muted, 0.40);
        $svg .= '<circle cx="' . self::n($textX + 3.2) . '" cy="49.0" r="3.2" fill="' . $live . '"/>';
        $svg .= self::line($l3, $textX + 11.0, 52.4, 9.2, 500, $muted);

        return $svg . '</svg>';
    }

    private static function badgeCompact(array $d, string $ink, string $muted, string $brand, string $bg, string $line, string $live): string
    {
        $l1 = (string) $d['verified'];
        $l2 = (string) $d['active_short'];

        $w = max(132.0, self::textWidth($l1, 13.0, 0) + 26.0, self::textWidth($l2, 8.2, 0.4) + 36.0);
        $h = 124.0;

        $svg = self::open($w, $h, (string) $d['name'] . ' — ' . $l1);
        $svg .= '<rect x=".7" y=".7" width="' . self::n($w - 1.4) . '" height="' . self::n($h - 1.4) . '"'
             . ' rx="12" fill="' . $bg . '" stroke="' . $line . '" stroke-width="1.4"/>';

        $estado = !empty($d['fit']) ? 'fit' : 'open';
        $scale = 0.62;
        $markW = self::BOX[$estado][0] * $scale;
        $svg .= self::markGroup($estado, $w / 2 - $markW / 2, 20.0, $scale, $brand);

        $svg .= '<path d="M18 80H' . self::n($w - 18) . '" stroke="' . $line . '" stroke-width="1"/>';
        $svg .= self::centered($l1, $w / 2, 97.0, 13.0, 700, $ink);
        $svg .= '<circle cx="' . self::n($w / 2 - self::textWidth($l2, 8.2, 0.4) / 2 - 7.0) . '" cy="109.4" r="2.9" fill="' . $live . '"/>';
        $svg .= self::centered($l2, $w / 2, 112.0, 8.2, 700, $muted, 0.4);

        return $svg . '</svg>';
    }

    private static function centered(string $text, float $cx, float $y, float $size, int $weight, string $fill, float $tracking = 0.0): string
    {
        $len = self::textWidth($text, $size, $tracking);

        return self::line($text, $cx - $len / 2, $y, $size, $weight, $fill, $tracking);
    }

    private static function open(float $w, float $h, string $label): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::n($w) . '" height="' . self::n($h) . '"'
            . ' viewBox="0 0 ' . self::n($w) . ' ' . self::n($h) . '" role="img" aria-label="' . e($label) . '"'
            . ' font-family="' . self::FONT . '">';
    }

    /**
     * Selo que não reconhece ninguém.
     *
     * Responde 200 de propósito. O recurso pedido era o selo, e o selo foi
     * entregue — o que ele diz é que aquele emissor não está no registro.
     * Um 404 devolveria imagem quebrada, e imagem quebrada não informa nada
     * a quem está olhando a página: some do jeito que um selo falso gostaria
     * de sumir. Aqui ele fica visível, cinza, com a cunha solta, dizendo o
     * que é. O status HTTP serve a máquina; o desenho serve a pessoa.
     */
    public static function voidBadge(string $line1, string $line2, bool $dark = false): string
    {
        $ink   = $dark ? '#9AA7B8' : '#566376';
        $faint = $dark ? '#6F7E92' : '#8A97A8';
        $bg    = $dark ? '#0C1119' : '#F4F7FB';
        $line  = $dark ? '#2E3F52' : '#D8E0EA';

        $h = 64.0;
        $scale = 0.40;
        [$markW, $markH] = self::BOX['open'];   // a cunha solta ocupa mais largura
        $markW *= $scale;
        $markH *= $scale;
        $textX = 13.0 + $markW + 12.0;
        $w = $textX + max(self::textWidth($line1, 13.0, 0), self::textWidth($line2, 9.4, 0)) + 14.0;

        $svg = self::open($w, $h, $line1);
        $svg .= '<rect x=".6" y=".6" width="' . self::n($w - 1.2) . '" height="' . self::n($h - 1.2) . '"'
             . ' rx="9" fill="' . $bg . '" stroke="' . $line . '" stroke-width="1.2" stroke-dasharray="5 3.4"/>';
        $svg .= self::markGroup('open', 13.0, ($h - $markH) / 2, $scale, $faint);
        $svg .= self::line($line1, $textX, 29.0, 13.0, 700, $ink);
        $svg .= self::line($line2, $textX, 44.0, 9.4, 500, $faint);

        return $svg . '</svg>';
    }

    /* ------------------------------------- selo 3: nossos certificados */

    /**
     * Selo circular. O círculo é o que separa, de longe, "emitido aqui" de
     * "registrado aqui" — nenhum outro selo nosso é redondo.
     *
     * Mínimo de 26 mm de diâmetro, e não os 18 mm que eu tinha estimado no
     * estudo. A conta é simples e eu só fiz depois de desenhar: a linha de
     * baixo tem 31 caracteres dando a volta em meia circunferência, o corpo
     * do texto é 11,2 em 200 unidades, e 18 mm põem a altura de maiúscula em
     * 1,0 mm. Depois de uma fotocópia, 1,0 mm não se lê. A 26 mm dá 1,5 mm,
     * que é o piso para texto que ainda precisa ser lido no papel.
     *
     * Vai embutido na página, então pode usar currentColor e nossa fonte.
     */
    public static function roundSeal(string $top, string $bottom, string $class = 'cert-round-seal'): string
    {
        $uid = 'rs' . substr(sha1($top . '|' . $bottom), 0, 6);

        return '<svg class="' . e($class) . '" viewBox="0 0 200 200" aria-hidden="true" focusable="false">'
            . '<defs>'
            . '<path id="' . $uid . 't" d="M22 100A78 78 0 0 1 178 100" fill="none"/>'
            . '<path id="' . $uid . 'b" d="M22 100A78 78 0 0 0 178 100" fill="none"/>'
            . '</defs>'
            . '<circle cx="100" cy="100" r="96" fill="none" stroke="currentColor" stroke-width="3.2"/>'
            . '<circle cx="100" cy="100" r="88" fill="none" stroke="currentColor" stroke-width="1.4"/>'
            . '<text class="cert-ring" font-size="13.4" font-weight="700" letter-spacing="1.1" fill="currentColor">'
            . '<textPath href="#' . $uid . 't" startOffset="50%" text-anchor="middle">' . e($top) . '</textPath></text>'
            . '<text class="cert-ring" font-size="11.2" font-weight="700" letter-spacing=".9" fill="currentColor">'
            . '<textPath href="#' . $uid . 'b" startOffset="50%" text-anchor="middle">' . e($bottom) . '</textPath></text>'
            . self::markGroup('fit', 100 - self::BOX['fit'][0] * 0.8 / 2, 100 - self::BOX['fit'][1] * 0.8 / 2, 0.8, 'currentColor')
            . '</svg>';
    }
}
