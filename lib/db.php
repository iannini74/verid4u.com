<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) Config::get('db.host', '127.0.0.1'),
            (int) Config::get('db.port', 3306),
            (string) Config::get('db.name')
        );
        self::$pdo = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }

    /** @param array<string|int, mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /* ------------------------------------------------------------ migrações */

    public const SCHEMA_VERSION = 3;

    private static function marker(): string
    {
        return __DIR__ . '/../storage/schema.version';
    }

    /**
     * Roda as migrações pendentes. Idempotente e protegida por trava nomeada
     * do MySQL, para que duas requisições simultâneas não apliquem a mesma
     * migração duas vezes. Depois de aplicada, um arquivo marcador evita
     * qualquer consulta extra nas requisições seguintes.
     */
    public static function migrateIfNeeded(): void
    {
        $marker = self::marker();
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === (string) self::SCHEMA_VERSION) {
            return;
        }

        $pdo = self::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version INT UNSIGNED NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $locked = (bool) $pdo->query("SELECT GET_LOCK('verid4u_migrate', 10)")->fetchColumn();
        if (!$locked) {
            return;
        }

        try {
            $applied = array_map(
                static fn (array $r): int => (int) $r['version'],
                self::all('SELECT version FROM schema_migrations')
            );
            $pending = self::migrations();
            ksort($pending);
            foreach ($pending as $version => $statements) {
                if (in_array($version, $applied, true)) {
                    continue;
                }
                foreach ($statements as $sql) {
                    $pdo->exec($sql);
                }
                self::run('INSERT IGNORE INTO schema_migrations (version) VALUES (?)', [$version]);
            }
            Seeder::run();

            $dir = dirname($marker);
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            @file_put_contents($marker, (string) self::SCHEMA_VERSION);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('verid4u_migrate')");
        }
    }

    /** @return array<int, list<string>> */
    private static function migrations(): array
    {
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            1 => [
                "CREATE TABLE IF NOT EXISTS tenants (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(160) NOT NULL,
                    slug VARCHAR(80) NOT NULL,
                    plan VARCHAR(32) NOT NULL DEFAULT 'free',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_tenants_slug (slug)
                ) $engine",

                "CREATE TABLE IF NOT EXISTS issuers (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    name VARCHAR(190) NOT NULL,
                    legal_id VARCHAR(40) NULL,
                    domain VARCHAR(190) NULL,
                    level TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_issuers_tenant (tenant_id),
                    CONSTRAINT fk_issuers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    name VARCHAR(160) NOT NULL,
                    email_enc TEXT NOT NULL,
                    email_index CHAR(64) NOT NULL,
                    role VARCHAR(24) NOT NULL DEFAULT 'owner',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_login_at DATETIME NULL,
                    UNIQUE KEY uq_users_email (email_index),
                    KEY idx_users_tenant (tenant_id),
                    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS login_tokens (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_login_token (token_hash),
                    KEY idx_login_user (user_id),
                    CONSTRAINT fk_login_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS sessions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_session_token (token_hash),
                    KEY idx_session_user (user_id),
                    CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS programs (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    issuer_id INT UNSIGNED NOT NULL,
                    title VARCHAR(190) NOT NULL,
                    norm VARCHAR(40) NULL,
                    hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    modality ENUM('presencial','ead','hibrido') NOT NULL DEFAULT 'presencial',
                    assessed TINYINT(1) NOT NULL DEFAULT 0,
                    validity_months SMALLINT UNSIGNED NULL,
                    archived_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_programs_tenant (tenant_id),
                    CONSTRAINT fk_programs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
                    CONSTRAINT fk_programs_issuer FOREIGN KEY (issuer_id) REFERENCES issuers (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS holders (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    name VARCHAR(190) NOT NULL,
                    email_enc TEXT NULL,
                    email_index CHAR(64) NULL,
                    document_hmac CHAR(64) NULL,
                    display_mode ENUM('full','partial','hidden') NOT NULL DEFAULT 'partial',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_holders_tenant (tenant_id),
                    KEY idx_holders_email (email_index),
                    CONSTRAINT fk_holders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                ) $engine",

                /* Os campos do curso, do emissor e do titular são copiados para
                   a emissão no momento em que ela acontece. Certificado emitido
                   nunca muda: alterar o curso depois não pode reescrever o que
                   já foi atestado. */
                "CREATE TABLE IF NOT EXISTS issuances (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    issuer_id INT UNSIGNED NOT NULL,
                    program_id INT UNSIGNED NULL,
                    holder_id BIGINT UNSIGNED NOT NULL,
                    code_base CHAR(11) NOT NULL,
                    program_title VARCHAR(190) NOT NULL,
                    program_norm VARCHAR(40) NULL,
                    hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    modality ENUM('presencial','ead','hibrido') NOT NULL DEFAULT 'presencial',
                    assessed TINYINT(1) NOT NULL DEFAULT 0,
                    issuer_name VARCHAR(190) NOT NULL,
                    issuer_legal_id VARCHAR(40) NULL,
                    issuer_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    holder_name VARCHAR(190) NOT NULL,
                    holder_display ENUM('full','partial','hidden') NOT NULL DEFAULT 'partial',
                    issued_on DATE NOT NULL,
                    valid_from DATE NULL,
                    valid_until DATE NULL,
                    revoked_on DATE NULL,
                    revoke_reason VARCHAR(255) NULL,
                    superseded_by BIGINT UNSIGNED NULL,
                    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                    content_hash CHAR(64) NOT NULL,
                    anchored_on DATE NULL,
                    is_demo TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_issuances_code (code_base),
                    KEY idx_issuances_expiry (tenant_id, valid_until),
                    KEY idx_issuances_holder (holder_id),
                    CONSTRAINT fk_issuances_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
                    CONSTRAINT fk_issuances_holder FOREIGN KEY (holder_id) REFERENCES holders (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS audit_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NULL,
                    actor_user_id INT UNSIGNED NULL,
                    action VARCHAR(64) NOT NULL,
                    entity VARCHAR(40) NULL,
                    entity_id VARCHAR(64) NULL,
                    detail JSON NULL,
                    ip_hash CHAR(64) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_audit_tenant (tenant_id, created_at)
                ) $engine",
            ],

            3 => [
                "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER role",
                "ALTER TABLE users ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active' AFTER password_hash",
            ],

            2 => [
                "CREATE TABLE IF NOT EXISTS verification_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    issuance_id BIGINT UNSIGNED NULL,
                    code_base CHAR(11) NOT NULL,
                    outcome VARCHAR(24) NOT NULL,
                    channel VARCHAR(16) NOT NULL DEFAULT 'web',
                    locale VARCHAR(8) NULL,
                    ip_hash CHAR(64) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_events_issuance (issuance_id, created_at),
                    KEY idx_events_created (created_at)
                ) $engine",
            ],
        ];
    }
}
