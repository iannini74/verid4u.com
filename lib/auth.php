<?php
declare(strict_types=1);

final class Auth
{
    private const SESSION_COOKIE = 'v4u_session';
    private const CSRF_COOKIE = 'v4u_csrf';
    private const SESSION_DAYS = 14;

    private static ?array $cached = null;
    private static bool $resolved = false;

    /* ------------------------------------------------------------- sessão */

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cached;
        }
        self::$resolved = true;
        self::$cached = null;

        $token = $_COOKIE[self::SESSION_COOKIE] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }
        try {
            $row = Db::one(
                'SELECT u.id, u.tenant_id, u.name, u.email_enc, u.role, s.id AS session_id
                 FROM sessions s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.token_hash = ? AND s.expires_at > NOW() AND u.status = "active"
                 LIMIT 1',
                [Crypto::tokenHash($token)]
            );
        } catch (Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }
        $row['email'] = Crypto::decrypt($row['email_enc']);
        unset($row['email_enc']);
        self::$cached = $row;
        return $row;
    }

    public static function startSession(int $userId): void
    {
        $token = Crypto::token();
        Db::run(
            'INSERT INTO sessions (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$userId, Crypto::tokenHash($token), self::SESSION_DAYS]
        );
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
        self::setCookie(self::SESSION_COOKIE, $token, time() + self::SESSION_DAYS * 86400);
        self::$resolved = false;
    }

    public static function logout(): void
    {
        $token = $_COOKIE[self::SESSION_COOKIE] ?? null;
        if (is_string($token) && $token !== '') {
            try {
                Db::run('DELETE FROM sessions WHERE token_hash = ?', [Crypto::tokenHash($token)]);
            } catch (Throwable) {
            }
        }
        self::setCookie(self::SESSION_COOKIE, '', time() - 3600);
        self::$resolved = false;
        self::$cached = null;
    }

    private static function setCookie(string $name, string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /* ------------------------------------------------------------ entrada */

    public static function attempt(string $email, string $password): ?array
    {
        $row = Db::one(
            'SELECT id, password_hash, status FROM users WHERE email_index = ? LIMIT 1',
            [Crypto::blindIndex($email)]
        );
        // Comparação em tempo constante mesmo quando o usuário não existe,
        // para não revelar quais e-mails estão cadastrados.
        $hash = is_array($row) ? (string) ($row['password_hash'] ?? '') : '';
        $ok = $hash !== '' && password_verify($password, $hash);
        if (!$ok || !is_array($row) || $row['status'] !== 'active') {
            password_verify($password, '$2y$12$usuarioInexistenteParaIgualarOTempo0000000000000000000000');
            return null;
        }
        if (password_needs_rehash($hash, self::algo())) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [self::hash($password), $row['id']]);
        }
        self::startSession((int) $row['id']);
        return self::user();
    }

    public static function algo(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo());
    }

    public static function ownerExists(): bool
    {
        try {
            return (int) (Db::one('SELECT COUNT(*) AS n FROM users')['n'] ?? 0) > 0;
        } catch (Throwable) {
            return true; // na dúvida, não abre a ativação
        }
    }

    /* --------------------------------------------------------------- CSRF */

    public static function csrf(): string
    {
        $token = $_COOKIE[self::CSRF_COOKIE] ?? null;
        if (!is_string($token) || strlen($token) < 20) {
            $token = Crypto::token(16);
            self::setCookie(self::CSRF_COOKIE, $token, time() + 86400);
            $_COOKIE[self::CSRF_COOKIE] = $token;
        }
        return $token;
    }

    public static function checkCsrf(?string $sent): bool
    {
        $cookie = $_COOKIE[self::CSRF_COOKIE] ?? '';
        return is_string($sent) && $sent !== '' && is_string($cookie) && $cookie !== ''
            && hash_equals($cookie, $sent);
    }

    public static function audit(string $action, ?string $entity = null, ?string $entityId = null, array $detail = []): void
    {
        try {
            $user = self::user();
            Db::run(
                'INSERT INTO audit_log (tenant_id, actor_user_id, action, entity, entity_id, detail, ip_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $user['tenant_id'] ?? null,
                    $user['id'] ?? null,
                    $action,
                    $entity,
                    $entityId,
                    $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
                    client_fingerprint(),
                ]
            );
        } catch (Throwable) {
        }
    }
}
