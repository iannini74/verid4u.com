<?php
declare(strict_types=1);

/**
 * Segundo fator por TOTP (RFC 6238), em PHP puro.
 *
 * Só para quem administra a plataforma. Exigir segundo fator de todo cliente
 * afastaria cliente; mas quem pode abrir a conta de todo mundo não é todo
 * mundo, e senha sozinha não basta para esse papel.
 *
 * Sem biblioteca: são trinta linhas de HMAC e base32, e uma dependência a
 * mais numa hospedagem compartilhada sem Composer é mais risco que as trinta
 * linhas.
 */
final class Totp
{
    public const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;
    public const DIGITS = 6;

    /**
     * Janelas aceitas para trás e para frente.
     *
     * Uma de cada lado: relógio de celular erra alguns segundos, e exigir
     * exatidão faria o segundo fator falhar por motivo que a pessoa não
     * entende nem controla. Mais que isso alargaria a janela de reuso sem
     * ganho real de usabilidade.
     */
    public const DRIFT = 1;

    public static function secret(int $bytes = 20): string
    {
        $cru = random_bytes($bytes);
        $bits = '';
        for ($i = 0, $n = strlen($cru); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($cru[$i])), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $pedaco) {
            $saida .= self::ALPHABET[bindec(str_pad($pedaco, 5, '0', STR_PAD_RIGHT))];
        }

        return $saida;
    }

    private static function decode(string $segredo): string
    {
        $segredo = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $segredo) ?? '');
        $bits = '';
        for ($i = 0, $n = strlen($segredo); $i < $n; $i++) {
            $pos = strpos(self::ALPHABET, $segredo[$i]);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }

        return $saida;
    }

    public static function at(string $segredo, int $contador): string
    {
        $chave = self::decode($segredo);
        if ($chave === '') {
            return '';
        }
        $bin = pack('N*', 0, $contador);
        $hash = hash_hmac('sha1', $bin, $chave, true);
        $deslocamento = ord($hash[19]) & 0x0F;
        $trecho = ((ord($hash[$deslocamento]) & 0x7F) << 24)
            | ((ord($hash[$deslocamento + 1]) & 0xFF) << 16)
            | ((ord($hash[$deslocamento + 2]) & 0xFF) << 8)
            | (ord($hash[$deslocamento + 3]) & 0xFF);

        return str_pad((string) ($trecho % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Confere o código, aceitando uma janela de folga de cada lado.
     *
     * A comparação é em tempo constante. Comparar código de seis dígitos com
     * `===` vaza o prefixo certo pelo tempo de resposta, e seis dígitos é
     * pouco o bastante para isso importar.
     */
    public static function check(string $segredo, string $codigo): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';
        if (strlen($codigo) !== self::DIGITS) {
            return false;
        }
        $agora = intdiv(time(), self::PERIOD);
        for ($d = -self::DRIFT; $d <= self::DRIFT; $d++) {
            if (hash_equals(self::at($segredo, $agora + $d), $codigo)) {
                return true;
            }
        }

        return false;
    }

    /** URI para o aplicativo autenticador ler como QR Code. */
    public static function uri(string $segredo, string $conta, string $emissor = APP_NAME): string
    {
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
            . '?secret=' . $segredo
            . '&issuer=' . rawurlencode($emissor)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }
}
