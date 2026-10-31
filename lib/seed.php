<?php
declare(strict_types=1);

/**
 * Semeia os registros de demonstração.
 *
 * São fictícios de ponta a ponta e ficam marcados com is_demo = 1, o que faz
 * a página de verificação exibir o aviso. Rodam uma única vez, logo após as
 * migrações, e nunca sobrescrevem dado existente.
 */
final class Seeder
{
    public static function run(): void
    {
        $already = (int) (Db::one('SELECT COUNT(*) AS n FROM issuances')['n'] ?? 0);
        if ($already > 0) {
            return;
        }

        Db::run(
            'INSERT INTO tenants (name, slug, plan) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name)',
            ['Demonstração VerID4U', 'demo', 'free']
        );
        $tenantId = (int) (Db::one('SELECT id FROM tenants WHERE slug = ?', ['demo'])['id'] ?? 0);
        if ($tenantId === 0) {
            return;
        }

        $issuers = [
            'alvorada'  => ['Centro de Treinamento Alvorada', '**.***.***/0001-90', 3, 'centro-treinamento-alvorada', 'treinamento'],
            'horizonte' => ['Escola Técnica Horizonte', '**.***.***/0001-24', 2, 'escola-tecnica-horizonte', 'ambos'],
            'meridiano' => ['Instituto Meridiano de Educação Profissional', '**.***.***/0001-07', 3, 'instituto-meridiano', 'profissional'],
        ];
        $issuerIds = [];
        foreach ($issuers as $key => [$name, $legalId, $level, $slug, $category]) {
            Db::run(
                'INSERT INTO issuers (tenant_id, name, legal_id, level, slug, category) VALUES (?, ?, ?, ?, ?, ?)',
                [$tenantId, $name, $legalId, $level, $slug, $category]
            );
            $issuerIds[$key] = (int) Db::pdo()->lastInsertId();
        }

        $records = [
            [
                'base' => 'K7M293FQ5XB', 'issuer' => 'alvorada',
                'holder' => 'Maria Souza Lima', 'display' => 'partial',
                'program' => 'NR-35 — Trabalho em Altura', 'norm' => 'NR-35',
                'hours' => 8, 'modality' => 'presencial', 'assessed' => 1,
                'issued_on' => '2026-03-10', 'valid_until' => '2028-03-10',
                'revoked_on' => null, 'reason' => null,
            ],
            [
                'base' => 'H4T8R2WX1NQ', 'issuer' => 'horizonte',
                'holder' => 'João Carlos Ferreira', 'display' => 'partial',
                'program' => 'NR-33 — Espaço Confinado, Trabalhador Autorizado', 'norm' => 'NR-33',
                'hours' => 16, 'modality' => 'presencial', 'assessed' => 1,
                'issued_on' => '2025-12-05', 'valid_until' => '2026-12-05',
                'revoked_on' => null, 'reason' => null,
            ],
            [
                'base' => 'B9CD5K3ZY7M', 'issuer' => 'meridiano',
                'holder' => 'Ana Paula Rodrigues', 'display' => 'partial',
                'program' => 'NR-10 — Segurança em Instalações e Serviços em Eletricidade (Básico)', 'norm' => 'NR-10',
                'hours' => 40, 'modality' => 'presencial', 'assessed' => 1,
                'issued_on' => '2023-04-18', 'valid_until' => '2025-04-18',
                'revoked_on' => null, 'reason' => null,
            ],
            [
                'base' => 'P2V6S8GJ4TR', 'issuer' => 'horizonte',
                'holder' => 'Roberto Nunes', 'display' => 'partial',
                'program' => 'Operação de Empilhadeira — Capacitação Inicial', 'norm' => 'NR-11',
                'hours' => 16, 'modality' => 'hibrido', 'assessed' => 0,
                'issued_on' => '2026-05-22', 'valid_until' => '2027-05-22',
                'revoked_on' => '2026-09-02',
                'reason' => 'Emitido por engano para pessoa que não concluiu a avaliação prática.',
            ],
            [
                'base' => 'X5N1QW9D3FH', 'issuer' => 'meridiano',
                'holder' => 'Luciana Teixeira de Andrade', 'display' => 'full',
                'program' => 'Gestão de Riscos Psicossociais na NR-1', 'norm' => null,
                'hours' => 60, 'modality' => 'ead', 'assessed' => 1,
                'issued_on' => '2026-07-14', 'valid_until' => null,
                'revoked_on' => null, 'reason' => null,
            ],
        ];

        foreach ($records as $r) {
            [$issuerName, $issuerLegalId, $issuerLevel] = $issuers[$r['issuer']];

            Db::run(
                'INSERT INTO holders (tenant_id, name, display_mode) VALUES (?, ?, ?)',
                [$tenantId, $r['holder'], $r['display']]
            );
            $holderId = (int) Db::pdo()->lastInsertId();

            $hash = Store::contentHash($r['base'], [
                'holder_name' => $r['holder'],
                'program_title' => $r['program'],
                'hours' => $r['hours'],
                'modality' => $r['modality'],
                'issuer_name' => $issuerName,
                'issued_on' => $r['issued_on'],
                'valid_until' => $r['valid_until'],
            ]);

            Db::run(
                'INSERT INTO issuances (
                    tenant_id, issuer_id, program_id, holder_id, code_base,
                    program_title, program_norm, hours, modality, assessed,
                    issuer_name, issuer_legal_id, issuer_level,
                    holder_name, holder_display,
                    issued_on, valid_from, valid_until, revoked_on, revoke_reason,
                    content_hash, anchored_on, is_demo
                 ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [
                    $tenantId, $issuerIds[$r['issuer']], $holderId, $r['base'],
                    $r['program'], $r['norm'], $r['hours'], $r['modality'], $r['assessed'],
                    $issuerName, $issuerLegalId, $issuerLevel,
                    $r['holder'], $r['display'],
                    $r['issued_on'], $r['issued_on'], $r['valid_until'], $r['revoked_on'], $r['reason'],
                    $hash, $r['issued_on'],
                ]
            );
        }
    }
}
