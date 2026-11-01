<?php
declare(strict_types=1);

/**
 * Chaves da API.
 *
 * A chave é mostrada uma vez, no instante em que nasce, e nunca mais. O que
 * fica guardado é o hash e um prefixo curto — o prefixo existe só para o
 * cliente reconhecer qual chave é qual na tela. Nem nós conseguimos devolver
 * uma chave perdida, e é assim que tem de ser: se conseguíssemos, alguém com
 * acesso ao nosso banco também conseguiria.
 *
 * O formato carrega o ambiente no próprio texto (`vk_live_`). Chave que não
 * diz de onde é acaba colada no lugar errado, e a hora de descobrir isso é
 * olhando a chave, não depurando a integração.
 */
final class ApiKey
{
    public const PREFIX = 'vk_live_';
    public const BYTES = 24;

    /** Quantas chamadas por janela de dez minutos, por chave. */
    public const RATE_PER_10MIN = 600;

    /**
     * Cria a chave e devolve o texto em claro — a única vez que ele existe.
     *
     * @return array{id:int,key:string,prefix:string}
     */
    public static function create(int $tenantId, string $name): array
    {
        $segredo = bin2hex(random_bytes(self::BYTES));
        $chave = self::PREFIX . $segredo;
        $prefixo = substr($chave, 0, 16);

        Db::run(
            'INSERT INTO api_keys (tenant_id, name, prefix, key_hash) VALUES (?, ?, ?, ?)',
            [$tenantId, mb_substr(trim($name) !== '' ? $name : 'Integração', 0, 80), $prefixo, hash('sha256', $chave)]
        );

        return ['id' => (int) Db::pdo()->lastInsertId(), 'key' => $chave, 'prefix' => $prefixo];
    }

    /**
     * Resolve o cabeçalho Authorization para um cliente.
     *
     * Devolve null para chave ausente, malformada, desconhecida ou revogada —
     * de propósito sem distinguir qual dos quatro. Dizer «esta chave existe,
     * mas está revogada» confirma a existência da chave para quem a encontrou
     * no lugar errado.
     */
    public static function resolve(?string $header): ?array
    {
        $header = trim((string) $header);
        if (strncasecmp($header, 'Bearer ', 7) !== 0) {
            return null;
        }
        $chave = trim(substr($header, 7));
        if (strncmp($chave, self::PREFIX, strlen(self::PREFIX)) !== 0) {
            return null;
        }
        if (preg_match('/^' . preg_quote(self::PREFIX, '/') . '[a-f0-9]{' . (self::BYTES * 2) . '}$/', $chave) !== 1) {
            return null;
        }

        $linha = Db::one(
            'SELECT k.id, k.tenant_id, k.name, k.prefix, t.name AS tenant_name, t.status AS tenant_status
               FROM api_keys k
               JOIN tenants t ON t.id = k.tenant_id
              WHERE k.key_hash = ? AND k.revoked_at IS NULL
              LIMIT 1',
            [hash('sha256', $chave)]
        );
        if ($linha === null || (string) $linha['tenant_status'] !== 'active') {
            return null;
        }

        // Carimbo de uso para o cliente ver qual chave está viva. Uma vez por
        // minuto basta: gravar a cada chamada seria uma escrita por leitura.
        Db::run(
            'UPDATE api_keys SET last_used_at = NOW()
              WHERE id = ? AND (last_used_at IS NULL OR last_used_at < NOW() - INTERVAL 1 MINUTE)',
            [(int) $linha['id']]
        );

        return $linha;
    }

    /** @return list<array<string,mixed>> */
    public static function forTenant(int $tenantId): array
    {
        return Db::all(
            'SELECT id, name, prefix, last_used_at, revoked_at, created_at
               FROM api_keys WHERE tenant_id = ? ORDER BY revoked_at IS NOT NULL, created_at DESC',
            [$tenantId]
        );
    }

    public static function revoke(int $tenantId, int $id): bool
    {
        Db::run(
            'UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL',
            [$id, $tenantId]
        );

        return true;
    }
}
