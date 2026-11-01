<?php
declare(strict_types=1);

/**
 * Criação de conta pelo próprio cliente.
 *
 * Até aqui a plataforma só sabia nascer: a ativação criava o dono único e se
 * fechava. Esta classe separa «criar uma organização» de «ativar a
 * plataforma», e passa a mesma transação a servir aos dois.
 *
 * Três campos e nenhum cartão. Cada campo a mais na porta é uma fração dos
 * visitantes que não entra, e o que precisamos saber do cliente dá para
 * perguntar depois — com ele já dentro, e com um certificado emitido.
 */
final class Onboarding
{
    /** Validade do link de confirmação de e-mail. */
    public const CONFIRM_TTL = '+3 days';

    /**
     * Cria organização, emissor e usuário dono numa transação só.
     *
     * @param array{org:string,name:string,email:string,password:string} $input
     * @return array{ok:bool,error:?string,user_id:?int,token:?string}
     */
    public static function create(array $input, string $via = 'signup'): array
    {
        $org   = trim($input['org'] ?? '');
        $nome  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = (string) ($input['password'] ?? '');

        if ($org === '') {
            return self::fail('signup.bad_org');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::fail('signup.bad_email');
        }
        if (mb_strlen($senha) < 12) {
            return self::fail('signup.weak');
        }
        if (Db::one('SELECT id FROM users WHERE email_index = ? LIMIT 1', [Crypto::blindIndex($email)]) !== null) {
            return self::fail('signup.email_taken');
        }

        // O endereço público sai limpo quando dá, porque vai em peça impressa
        // e precisa ser ditável. Nunca muda depois: trocá-lo cegaria todo selo
        // já instalado por aí.
        $slug = Issuer::slugify($org);
        if (Db::one('SELECT id FROM issuers WHERE slug = ? LIMIT 1', [$slug]) !== null) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run(
                'INSERT INTO tenants (name, slug, plan, status, created_via) VALUES (?, ?, ?, ?, ?)',
                [
                    $org,
                    substr(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($org)) ?: 'org', 0, 60)
                        . '-' . bin2hex(random_bytes(2)),
                    'free',
                    'active',
                    $via,
                ]
            );
            $tenantId = (int) $pdo->lastInsertId();

            Db::run(
                'INSERT INTO issuers (tenant_id, name, level, slug, category) VALUES (?, ?, 0, ?, ?)',
                [$tenantId, $org, $slug, Brand::CATEGORY_DEFAULT]
            );

            // Nasce pendente. Confirmar o e-mail é o que libera emitir — ver
            // self::canIssue(). Deixar um endereço não confirmado pôr registro
            // no cadastro público é abrir a porta para quem quer sujá-lo.
            Db::run(
                'INSERT INTO users (tenant_id, name, email_enc, email_index, role, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    $nome !== '' ? $nome : $org,
                    Crypto::encrypt($email),
                    Crypto::blindIndex($email),
                    'owner',
                    Auth::hash($senha),
                    $via === 'setup' ? 'active' : 'pending',
                ]
            );
            $userId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[verid4u] criar conta: ' . $e->getMessage());

            return self::fail('error.unavailable');
        }

        $token = $via === 'setup' ? null : self::mintConfirmToken($userId);

        return ['ok' => true, 'error' => null, 'user_id' => $userId, 'token' => $token];
    }

    /** @return array{ok:bool,error:string,user_id:null,token:null} */
    private static function fail(string $chave): array
    {
        return ['ok' => false, 'error' => $chave, 'user_id' => null, 'token' => null];
    }

    /* ------------------------------------------------ confirmação de e-mail */

    public static function mintConfirmToken(int $userId): string
    {
        $bruto = bin2hex(random_bytes(32));
        Db::run(
            'INSERT INTO login_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, ?)',
            [$userId, 'confirm', hash('sha256', $bruto), date('Y-m-d H:i:s', strtotime(self::CONFIRM_TTL))]
        );

        return $bruto;
    }

    /** Consome o token. Devolve o usuário confirmado, ou null. */
    public static function confirm(string $bruto): ?array
    {
        if ($bruto === '' || preg_match('/^[a-f0-9]{64}$/', $bruto) !== 1) {
            return null;
        }
        $row = Db::one(
            "SELECT id, user_id FROM login_tokens
              WHERE token_hash = ? AND purpose = 'confirm' AND used_at IS NULL AND expires_at > NOW()
              LIMIT 1",
            [hash('sha256', $bruto)]
        );
        if ($row === null) {
            return null;
        }

        Db::run('UPDATE login_tokens SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        Db::run("UPDATE users SET status = 'active' WHERE id = ?", [(int) $row['user_id']]);

        return Db::one('SELECT id, tenant_id, name, status FROM users WHERE id = ? LIMIT 1', [(int) $row['user_id']]);
    }

    public static function sendConfirmation(int $userId, string $email, string $org, string $token, string $locale): void
    {
        Mailer::queue(
            'account_confirm',
            $email,
            $org,
            $locale,
            [
                'org' => $org,
                'url' => base_url() . '/' . $locale . '/app/confirmar/' . $token,
            ],
            null,
            null,
            'transactional'
        );
    }

    /* ----------------------------------------------------------- permissões */

    /**
     * Pode pôr registro no cadastro público?
     *
     * Duas condições, e as duas existem por motivos diferentes: e-mail
     * confirmado é contra quem quer sujar o registro; faixa não esgotada é o
     * limite do plano. A mensagem para o cliente precisa dizer qual das duas
     * é, senão ele conserta a errada.
     */
    public static function canIssue(array $user): bool
    {
        return self::emailConfirmed($user) && !Plan::exhausted((int) $user['tenant_id']);
    }

    public static function emailConfirmed(array $user): bool
    {
        return (string) ($user['status'] ?? 'active') === 'active';
    }

    /** Chave do motivo do bloqueio, ou null se pode emitir. */
    public static function blockReason(array $user): ?string
    {
        if (!self::emailConfirmed($user)) {
            return 'signup.confirm_first';
        }
        if (Plan::exhausted((int) $user['tenant_id'])) {
            return 'plan.exhausted';
        }

        return null;
    }
}
