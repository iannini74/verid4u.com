<?php
declare(strict_types=1);

/**
 * Cifragem de campos de contato em repouso (AES-256-GCM) e HMAC para
 * documentos.
 *
 * O e-mail do titular nunca aparece em página pública; guardá-lo cifrado
 * garante que um vazamento de banco não entregue a lista de contatos.
 * O documento (CPF) nunca é guardado em claro: só o HMAC, que permite a
 * conferência por prova de conhecimento sem nunca exibir o dado.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $raw = base64_decode((string) Config::get('app_key'), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('app_key ausente ou inválida: gere com "openssl rand -base64 32".');
        }
        return $raw;
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipherText === false) {
            throw new RuntimeException('Falha ao cifrar.');
        }
        return base64_encode($iv . $tag . $cipherText);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(
            substr($raw, 28),
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16)
        );
        return $plain === false ? null : $plain;
    }

    /** Busca determinística sem guardar o dado em claro. */
    public static function blindIndex(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), self::key());
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
