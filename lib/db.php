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

    public const SCHEMA_VERSION = 15;

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

            /* Origem do registro e impressão digital do arquivo original.
               O hash permite duas coisas que nenhum concorrente faz: conferir
               se o PDF em mãos é exatamente o registrado, e achar o registro
               a partir do próprio arquivo, sem código nenhum. */
            /* Um sexto registro de demonstração, desta vez REGISTRADO em vez de
               emitido, com a impressão digital de assets/demo/certificado-exemplo.pdf.
               Serve para que a distinção de origem e a conferência de arquivo
               possam ser vistas funcionando sem precisar de conta. */
            /* Área de espera da importação. O arquivo enviado é lido uma vez,
               vira linhas aqui e é apagado em seguida: o mapeamento, a
               simulação e a gravação trabalham sobre o banco, nunca sobre um
               arquivo parado no servidor. */
            /* A importação pergunta «esta linha já existe?» uma vez por linha.
               Sem índice isso varre a tabela inteira a cada pergunta. */
            /* Fila de saída e lista de supressão.
               A mensagem guarda o NOME do modelo e os dados, não o texto
               pronto: assim corrigir um modelo conserta o que ainda não saiu,
               e o corpo não incha o banco. O endereço vai cifrado, com índice
               cego para a supressão poder consultar sem decifrar nada. */
            /* Desempenho das variações da primeira dobra. Uma linha por
               variação e por dia: permite ver tanto o acumulado quanto a
               evolução, sem guardar nada sobre quem visitou. */
            15 => [
                // A paleta do cliente. Só o acento: as quatro cores de estado
                // são reservadas, e fundo claro é o que faz a página de
                // verificação parecer documento em vez de campanha.
                "ALTER TABLE tenants ADD COLUMN brand_color CHAR(7) NULL AFTER brand_enabled_at",
            ],

            14 => [
                // Preço em centavos, inteiro. Dinheiro em ponto flutuante é
                // como a conta fecha com um centavo de diferença e ninguém
                // descobre de onde veio.
                "CREATE TABLE IF NOT EXISTS plans (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(24) NOT NULL,
                    name VARCHAR(60) NOT NULL,
                    monthly_quota INT UNSIGNED NOT NULL,
                    price_cents INT UNSIGNED NOT NULL DEFAULT 0,
                    promo_cents INT UNSIGNED NULL,
                    promo_until DATE NULL,
                    yearly_cents INT UNSIGNED NULL,
                    brand_included TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_plan_code (code)
                ) $engine",

                "CREATE TABLE IF NOT EXISTS coupons (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(32) NOT NULL,
                    kind ENUM('percent','amount') NOT NULL,
                    value INT UNSIGNED NOT NULL,
                    plan_code VARCHAR(24) NULL,
                    starts_on DATE NULL,
                    ends_on DATE NULL,
                    max_redemptions INT UNSIGNED NULL,
                    per_tenant_limit TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    months INT UNSIGNED NULL,
                    redemptions INT UNSIGNED NOT NULL DEFAULT 0,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    note VARCHAR(190) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_coupon_code (code)
                ) $engine",

                "CREATE TABLE IF NOT EXISTS coupon_redemptions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    coupon_id INT UNSIGNED NOT NULL,
                    tenant_id INT UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_redeem (coupon_id, tenant_id),
                    CONSTRAINT fk_redeem_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
                    CONSTRAINT fk_redeem_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                ) $engine",

                "CREATE TABLE IF NOT EXISTS subscriptions (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    plan_code VARCHAR(24) NOT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'pending',
                    coupon_id INT UNSIGNED NULL,
                    cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
                    gateway VARCHAR(16) NULL,
                    gateway_ref VARCHAR(190) NULL,
                    current_period_end DATE NULL,
                    cancelled_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_sub_tenant (tenant_id),
                    KEY idx_sub_ref (gateway_ref)
                ) $engine",

                // O catálogo proposto. Vive em tabela e não em código para o
                // preço mudar sem publicar nada — e os valores abaixo são
                // proposta, não decisão: foram postos para a máquina existir.
                "INSERT INTO plans (code, name, monthly_quota, price_cents, yearly_cents, brand_included, sort_order)
                 VALUES
                   ('free',  'Gratuito',     50,     0,     0, 0, 1),
                   ('start', 'Essencial',   300,  9700, 97000, 0, 2),
                   ('pro',   'Profissional', 1000, 29700, 297000, 1, 3),
                   ('scale', 'Escala',      5000, 69700, 697000, 1, 4)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)",
            ],

            13 => [
                // A chave nunca é guardada: guardamos o hash e um prefixo
                // curto, que é o que a tela mostra para o cliente reconhecer
                // qual chave é qual. Quem perder a chave gera outra; nem nós
                // conseguimos devolvê-la, e é assim que tem de ser.
                "CREATE TABLE IF NOT EXISTS api_keys (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    name VARCHAR(80) NOT NULL,
                    prefix VARCHAR(20) NOT NULL,
                    key_hash CHAR(64) NOT NULL,
                    last_used_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_api_key (key_hash),
                    KEY idx_api_tenant (tenant_id, revoked_at)
                ) $engine",

                // Toda integração reenvia: timeout, retentativa, fila que
                // roda duas vezes. Sem isto, reenviar gera um segundo
                // certificado para o mesmo aluno — o pior defeito possível
                // num registro, porque ninguém descobre na hora.
                "CREATE TABLE IF NOT EXISTS api_idempotency (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    idem_key VARCHAR(190) NOT NULL,
                    endpoint VARCHAR(64) NOT NULL,
                    request_hash CHAR(64) NOT NULL,
                    status_code SMALLINT UNSIGNED NOT NULL,
                    response_json MEDIUMTEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_idem (tenant_id, idem_key),
                    KEY idx_idem_idade (created_at)
                ) $engine",
            ],

            12 => [
                // A cópia da landing promete «emissão em lote com a sua marca»
                // em três lugares. Até aqui o certificado saía só com o nome
                // do emissor em texto.
                "ALTER TABLE issuers ADD COLUMN logo_path VARCHAR(190) NULL AFTER category",
                "ALTER TABLE issuers ADD COLUMN logo_updated_at DATETIME NULL AFTER logo_path",
            ],

            11 => [
                // Um token serve a dois propósitos agora: entrar e confirmar
                // e-mail. Duas tabelas idênticas seriam dois lugares para
                // esquecer de expirar.
                "ALTER TABLE login_tokens ADD COLUMN purpose VARCHAR(24) NOT NULL DEFAULT 'login' AFTER user_id",

                "ALTER TABLE tenants ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active' AFTER plan",
                "ALTER TABLE tenants ADD COLUMN created_via VARCHAR(16) NOT NULL DEFAULT 'setup' AFTER status",
                "ALTER TABLE tenants ADD COLUMN brand_enabled_at DATETIME NULL AFTER created_via",

                // Medição por competência, e não por certificado ativo. Cobrar
                // por ativo puniria justamente o cliente que mantém histórico,
                // que é o comportamento que queremos.
                "CREATE TABLE IF NOT EXISTS usage_counters (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    competencia CHAR(7) NOT NULL,
                    issuances INT UNSIGNED NOT NULL DEFAULT 0,
                    registrations INT UNSIGNED NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_usage (tenant_id, competencia),
                    CONSTRAINT fk_usage_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                ) $engine",

                // O histórico que já existe vale para o limiar da marca do
                // cliente, que olha os últimos meses.
                "INSERT INTO usage_counters (tenant_id, competencia, issuances, registrations)
                 SELECT tenant_id, DATE_FORMAT(issued_on, '%Y-%m'),
                        SUM(origin = 'issued'), SUM(origin = 'registered')
                   FROM issuances
                  WHERE issued_on IS NOT NULL
                  GROUP BY tenant_id, DATE_FORMAT(issued_on, '%Y-%m')
                 ON DUPLICATE KEY UPDATE issuances = VALUES(issuances), registrations = VALUES(registrations)",
            ],

            10 => [
                // O emissor passa a ter endereço público e ramo declarado. O ramo
                // é o que imprime o qualificador no selo ("EMISSOR DE CERTIFICADO
                // DE ENSINO E TREINAMENTO"), então ele é dado do emissor, nunca
                // texto digitado no selo — selo com texto livre deixa de provar.
                "ALTER TABLE issuers ADD COLUMN slug VARCHAR(80) NULL AFTER name",
                "ALTER TABLE issuers ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'ambos' AFTER level",
                "ALTER TABLE issuers ADD UNIQUE KEY uq_issuers_slug (slug)",

                // A emissão já aponta para o emissor; o que faltava era poder
                // contar por emissor sem varrer a tabela, que é o que a página
                // pública do emissor faz a cada visita.
                "ALTER TABLE issuances ADD INDEX idx_issuances_issuer (issuer_id, issued_on)",

                "UPDATE issuers SET slug = 'centro-de-treinamento-alvorada', category = 'treinamento'
                  WHERE slug IS NULL AND name = 'Centro de Treinamento Alvorada'",
                "UPDATE issuers SET slug = 'escola-tecnica-horizonte', category = 'ambos'
                  WHERE slug IS NULL AND name = 'Escola Técnica Horizonte'",
                "UPDATE issuers SET slug = 'instituto-meridiano', category = 'profissional'
                  WHERE slug IS NULL AND name = 'Instituto Meridiano de Educação Profissional'",

                // O comprovante é um retrato de uma consulta, com código próprio.
                // Guardá-lo em vez de recalcular é a única forma honesta: o papel
                // diz o que era verdade naquele instante, e não o que é agora.
                "CREATE TABLE IF NOT EXISTS receipts (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    receipt_base CHAR(11) NOT NULL,
                    code_base CHAR(11) NOT NULL,
                    issuance_id BIGINT UNSIGNED NULL,
                    outcome VARCHAR(24) NOT NULL,
                    locale VARCHAR(8) NOT NULL DEFAULT 'pt-br',
                    snapshot JSON NULL,
                    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_receipts_base (receipt_base),
                    KEY idx_receipts_issuance (issuance_id, checked_at)
                ) $engine",
            ],

            9 => [
                "CREATE TABLE IF NOT EXISTS hero_stats (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    variant_slug VARCHAR(40) NOT NULL,
                    locale VARCHAR(8) NOT NULL DEFAULT 'pt-br',
                    day DATE NOT NULL,
                    impressions INT UNSIGNED NOT NULL DEFAULT 0,
                    conversions INT UNSIGNED NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_hero_day (variant_slug, locale, day)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            8 => [
                "CREATE TABLE IF NOT EXISTS mail_messages (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NULL,
                    issuance_id BIGINT UNSIGNED NULL,
                    stream ENUM('transactional','lifecycle') NOT NULL DEFAULT 'transactional',
                    template VARCHAR(48) NOT NULL,
                    locale VARCHAR(8) NOT NULL DEFAULT 'pt-br',
                    to_email_enc TEXT NOT NULL,
                    to_email_index CHAR(64) NOT NULL,
                    to_name VARCHAR(190) NULL,
                    payload_json JSON NULL,
                    status ENUM('queued','sent','failed','suppressed','cancelled') NOT NULL DEFAULT 'queued',
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    last_error VARCHAR(255) NULL,
                    scheduled_for DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    sent_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_mail_due (status, scheduled_for),
                    KEY idx_mail_tenant (tenant_id, created_at),
                    KEY idx_mail_dedup (template, issuance_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS mail_suppressions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    email_index CHAR(64) NOT NULL,
                    reason VARCHAR(40) NOT NULL,
                    note VARCHAR(190) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_suppression (email_index)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            7 => [
                "ALTER TABLE issuances ADD INDEX idx_issuances_dedup (tenant_id, issued_on, holder_name(60))",
            ],

            6 => [
                "CREATE TABLE IF NOT EXISTS import_batches (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NULL,
                    mode ENUM('issue','register') NOT NULL DEFAULT 'register',
                    program_id INT UNSIGNED NULL,
                    filename VARCHAR(190) NOT NULL,
                    delimiter_used VARCHAR(4) NOT NULL DEFAULT ',',
                    encoding_used VARCHAR(24) NOT NULL DEFAULT 'UTF-8',
                    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
                    header_json JSON NULL,
                    mapping_json JSON NULL,
                    status ENUM('parsed','committed','discarded') NOT NULL DEFAULT 'parsed',
                    created_rows INT UNSIGNED NOT NULL DEFAULT 0,
                    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
                    failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    committed_at DATETIME NULL,
                    KEY idx_batches_tenant (tenant_id, created_at),
                    CONSTRAINT fk_batches_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS import_rows (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    batch_id BIGINT UNSIGNED NOT NULL,
                    line_no INT UNSIGNED NOT NULL,
                    raw_json JSON NOT NULL,
                    outcome ENUM('pending','created','skipped','failed') NOT NULL DEFAULT 'pending',
                    reason VARCHAR(255) NULL,
                    issuance_id BIGINT UNSIGNED NULL,
                    KEY idx_rows_batch (batch_id, line_no),
                    CONSTRAINT fk_rows_batch FOREIGN KEY (batch_id) REFERENCES import_batches (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            5 => [
                "INSERT IGNORE INTO holders (tenant_id, name, display_mode)
                 SELECT id, 'Carlos Eduardo Pires', 'partial' FROM tenants WHERE slug = 'demo'",

                "INSERT IGNORE INTO issuances (
                    tenant_id, issuer_id, program_id, holder_id, code_base, origin,
                    program_title, program_norm, hours, modality, assessed,
                    issuer_name, issuer_legal_id, issuer_level, registered_by,
                    holder_name, holder_display,
                    issued_on, valid_from, valid_until,
                    content_hash, source_sha256, source_filename, anchored_on, is_demo
                 )
                 SELECT t.id, i.id, NULL, h.id, 'T3R9M2XK8PV', 'registered',
                        'NR-12 — Segurança no Trabalho em Máquinas e Equipamentos', 'NR-12', 16, 'presencial', 1,
                        'SENAI — Unidade de demonstração', NULL, i.level, i.name,
                        'Carlos Eduardo Pires', 'partial',
                        '2026-02-19', '2026-02-19', '2028-02-19',
                        SHA2(CONCAT_WS('|','T3R9M2XK8PV','Carlos Eduardo Pires','NR-12 — Segurança no Trabalho em Máquinas e Equipamentos','16','presencial','SENAI — Unidade de demonstração','2026-02-19','2028-02-19'), 256),
                        '4793a1130ac3a6fde30c6c3285ed32a48b03dd30546cc8c09e2aab59cda68eae',
                        'certificado-exemplo.pdf', '2026-02-19', 1
                 FROM tenants t
                 JOIN issuers i ON i.tenant_id = t.id AND i.name = 'Escola Técnica Horizonte'
                 JOIN holders h ON h.tenant_id = t.id AND h.name = 'Carlos Eduardo Pires'
                 WHERE t.slug = 'demo'
                   AND NOT EXISTS (SELECT 1 FROM issuances x WHERE x.code_base = 'T3R9M2XK8PV')
                 LIMIT 1",
            ],

            4 => [
                "ALTER TABLE issuances ADD COLUMN origin ENUM('issued','registered') NOT NULL DEFAULT 'issued' AFTER code_base",
                "ALTER TABLE issuances ADD COLUMN source_sha256 CHAR(64) NULL AFTER content_hash",
                "ALTER TABLE issuances ADD COLUMN source_filename VARCHAR(190) NULL AFTER source_sha256",
                "ALTER TABLE issuances ADD COLUMN registered_by VARCHAR(190) NULL AFTER issuer_level",
                "ALTER TABLE issuances ADD INDEX idx_issuances_source (source_sha256)",
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
