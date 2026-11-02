<?php
declare(strict_types=1);

/**
 * A marca do cliente na página de verificação.
 *
 * É a tela que o mundo vê ao escanear o QR de um certificado daquele cliente:
 * a página mais visitada do produto, e a que mais vale. A partir de 500
 * emissões num mês — bastando um dos últimos três — ela passa a carregar a
 * logo e o acento do emissor.
 *
 * Três travas que não se negociam, e estão aqui porque a tentação de afrouxar
 * cada uma delas vem do próprio cliente:
 *
 * 1. **A marca do cliente é pele, nunca substituição.** A nossa marca e
 *    «Registrado no VerID4U» continuam visíveis e não removíveis. Uma página
 *    que parecesse só da escola deixaria a escola atestando a si mesma — que
 *    é exatamente o problema que viemos resolver.
 *
 * 2. **As quatro cores de estado são reservadas.** O acento do cliente troca
 *    detalhe e link. Nunca o verde, o âmbar, o cinza e o vermelho do veredito:
 *    se a cor do resultado pudesse ser escolhida por quem emitiu, o resultado
 *    deixaria de significar alguma coisa.
 *
 * 3. **Contraste é medido, não confiado.** Paleta que reprova 4,5:1 sobre
 *    fundo claro é recusada no momento de salvar, com a medida na tela. Não é
 *    preciosismo: a página existe para ser lida por quem está com o celular na
 *    mão, de pé, na portaria de uma obra.
 */
final class Skin
{
    /** Piso da WCAG para texto normal. */
    public const MIN_RATIO = 4.5;

    /** O fundo contra o qual medimos: a superfície clara do registro. */
    public const ON = '#FFFFFF';

    public static function normalizeHex(string $valor): ?string
    {
        $v = strtoupper(trim($valor));
        if ($v !== '' && $v[0] !== '#') {
            $v = '#' . $v;
        }
        if (preg_match('/^#[0-9A-F]{3}$/', $v) === 1) {
            $v = '#' . $v[1] . $v[1] . $v[2] . $v[2] . $v[3] . $v[3];
        }

        return preg_match('/^#[0-9A-F]{6}$/', $v) === 1 ? $v : null;
    }

    /** @return array{0:float,1:float,2:float} canais em 0..1 */
    private static function channels(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
    }

    /** Luminância relativa, WCAG 2.x. */
    public static function luminance(string $hex): float
    {
        $l = 0.0;
        foreach (self::channels($hex) as $i => $c) {
            $linear = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            $l += [0.2126, 0.7152, 0.0722][$i] * $linear;
        }

        return $l;
    }

    /** Razão de contraste entre duas cores. 1,0 é igual; 21,0 é preto no branco. */
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        $claro = max($la, $lb);
        $escuro = min($la, $lb);

        return round(($claro + 0.05) / ($escuro + 0.05), 2);
    }

    /**
     * O acento serve para texto sobre fundo claro?
     *
     * @return array{ok:bool,ratio:float}
     */
    public static function check(string $hex): array
    {
        $r = self::ratio($hex, self::ON);

        return ['ok' => $r >= self::MIN_RATIO, 'ratio' => $r];
    }

    /**
     * Escurece uma cor até ela passar no contraste.
     *
     * Em vez de só recusar, oferecemos o tom mais próximo que serve. Recusar
     * sem alternativa faz o cliente desistir ou insistir; oferecer o ajuste
     * resolve em um clique e mantém a marca dele reconhecível.
     */
    public static function darkenToPass(string $hex): string
    {
        [$r, $g, $b] = self::channels($hex);
        for ($passo = 0; $passo < 40; $passo++) {
            $cor = sprintf('#%02X%02X%02X', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
            if (self::ratio($cor, self::ON) >= self::MIN_RATIO) {
                return $cor;
            }
            $r *= 0.94;
            $g *= 0.94;
            $b *= 0.94;
        }

        return '#0A1020';
    }

    /* ------------------------------------------------------ na página /v */

    /**
     * A pele a aplicar num registro, ou null quando não há.
     *
     * Exige as três coisas ao mesmo tempo: o cliente ligou, tem direito pelo
     * volume, e a cor passa no contraste. Falha em qualquer uma e a página sai
     * com a nossa cara — que é o padrão, não o castigo.
     *
     * @return array{color:string,logo:?string,slug:?string,name:string}|null
     */
    public static function forRecord(array $reg): ?array
    {
        if (empty($reg['brand_on'])) {
            return null;
        }
        $cor = self::normalizeHex((string) ($reg['brand_color'] ?? ''));
        if ($cor === null || !self::check($cor)['ok']) {
            return null;
        }
        $tenantId = (int) ($reg['tenant_id'] ?? 0);
        if ($tenantId === 0 || !Plan::brandEligible($tenantId)) {
            return null;
        }

        return [
            'color' => $cor,
            'logo'  => !empty($reg['issuer_logo']) && !empty($reg['issuer_slug'])
                ? '/marca/' . $reg['issuer_slug'] : null,
            'slug'  => $reg['issuer_slug'] ?? null,
            'name'  => (string) ($reg['issuer_name'] ?? ''),
        ];
    }

    /**
     * O CSS da pele.
     *
     * Só três variáveis, e nenhuma delas é cor de estado. O `--brand-tint`
     * sai clareado do próprio acento, para fundo de detalhe continuar legível
     * sem o cliente precisar escolher uma segunda cor.
     */
    public static function css(string $cor): string
    {
        [$r, $g, $b] = self::channels($cor);
        $tint = sprintf(
            '#%02X%02X%02X',
            (int) round(255 - (255 - $r * 255) * 0.12),
            (int) round(255 - (255 - $g * 255) * 0.12),
            (int) round(255 - (255 - $b * 255) * 0.12)
        );
        $forte = sprintf(
            '#%02X%02X%02X',
            (int) round($r * 255 * 0.82),
            (int) round($g * 255 * 0.82),
            (int) round($b * 255 * 0.82)
        );

        return ':root{--brand:' . $cor . ';--brand-strong:' . $forte . ';--brand-tint:' . $tint . '}';
    }
}
