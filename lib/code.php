<?php
declare(strict_types=1);

/**
 * Código público do certificado.
 *
 * 12 caracteres em Base32 de Crockford, exibidos em três grupos de quatro:
 *   K7M2-93FQ-5XB1
 *
 * - Alfabeto de 32 símbolos sem I, L, O e U: elimina a confusão 0/O e 1/I/L
 *   na leitura humana e evita palavras acidentais.
 * - 11 caracteres aleatórios + 1 dígito verificador (Luhn mod 32).
 *   O verificador rejeita erro de digitação antes de tocar o banco.
 * - Nunca sequencial: 32^11 ≈ 3,6 x 10^16 bases possíveis, o que torna
 *   inviável enumerar a base e descobrir quem fez qual curso.
 */
final class Code
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    public const BASE_LENGTH = 11;
    public const FULL_LENGTH = 12;

    /** Normaliza a entrada humana: caixa, separadores e leituras tolerantes. */
    public static function normalize(string $raw): string
    {
        $s = strtoupper(trim($raw));
        $s = preg_replace('/[^0-9A-Z]/', '', $s);
        if ($s === null) {
            return '';
        }
        // Crockford: I e L lêem-se como 1, O lê-se como 0.
        return strtr($s, ['I' => '1', 'L' => '1', 'O' => '0']);
    }

    public static function isWellFormed(string $normalized): bool
    {
        return strlen($normalized) === self::FULL_LENGTH
            && strspn($normalized, self::ALPHABET) === self::FULL_LENGTH;
    }

    /** Dígito verificador Luhn mod 32. */
    public static function checkChar(string $base): string
    {
        $n = 32;
        $factor = 2;
        $sum = 0;

        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $point = strpos(self::ALPHABET, $base[$i]);
            if ($point === false) {
                throw new InvalidArgumentException('Caractere fora do alfabeto.');
            }
            $addend = $factor * $point;
            $factor = $factor === 2 ? 1 : 2;
            $addend = intdiv($addend, $n) + ($addend % $n);
            $sum += $addend;
        }

        return self::ALPHABET[($n - ($sum % $n)) % $n];
    }

    public static function full(string $base): string
    {
        return $base . self::checkChar($base);
    }

    public static function isValid(string $normalized): bool
    {
        if (!self::isWellFormed($normalized)) {
            return false;
        }
        $base = substr($normalized, 0, self::BASE_LENGTH);
        return self::checkChar($base) === $normalized[self::FULL_LENGTH - 1];
    }

    public static function base(string $normalized): string
    {
        return substr($normalized, 0, self::BASE_LENGTH);
    }

    /** K7M293FQ5XB1 -> K7M2-93FQ-5XB1 */
    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    public static function generateBase(): string
    {
        $out = '';
        for ($i = 0; $i < self::BASE_LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, 31)];
        }
        return $out;
    }
}
