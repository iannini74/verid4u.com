<?php
declare(strict_types=1);

/**
 * Fila de saída e envio.
 *
 * Nada sai direto de uma requisição web: tudo entra na fila e um processo
 * separado despacha. Emitir mil certificados não pode ficar esperando mil
 * conexões SMTP.
 *
 * A supressão é consultada DUAS vezes — ao enfileirar e ao enviar — porque
 * entre uma coisa e outra pode ter chegado um retorno permanente. Reputação
 * de envio é o ponto único de falha deste negócio.
 */
final class Mailer
{
    public const MAX_ATTEMPTS = 4;

    public static function enabled(): bool
    {
        return (string) Config::get('mail.driver', 'log') !== 'off';
    }

    /* ----------------------------------------------------------- supressão */

    public static function isSuppressed(string $email): bool
    {
        if ($email === '') {
            return true;
        }
        try {
            return Db::one('SELECT 1 AS t FROM mail_suppressions WHERE email_index = ? LIMIT 1',
                [Crypto::blindIndex($email)]) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public static function suppress(string $email, string $reason, ?string $note = null): void
    {
        if ($email === '') {
            return;
        }
        try {
            Db::run(
                'INSERT INTO mail_suppressions (email_index, reason, note) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason), note = VALUES(note)',
                [Crypto::blindIndex($email), $reason, $note]
            );
        } catch (Throwable $e) {
            error_log('[verid4u] supressao: ' . $e->getMessage());
        }
    }

    /* --------------------------------------------------------------- fila */

    /**
     * Põe uma mensagem na fila. Devolve o id, ou null se não havia o que
     * enfileirar (sem endereço, suprimido, ou já enfileirado antes).
     */
    public static function queue(
        string $template,
        string $toEmail,
        ?string $toName,
        string $locale,
        array $payload = [],
        ?int $tenantId = null,
        ?int $issuanceId = null,
        string $stream = 'transactional',
        ?string $scheduledFor = null
    ): ?int {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if (self::isSuppressed($toEmail)) {
            return null;
        }
        try {
            // Não manda a mesma coisa duas vezes para o mesmo certificado.
            if ($issuanceId !== null) {
                $ja = Db::one(
                    "SELECT 1 AS t FROM mail_messages
                     WHERE template = ? AND issuance_id = ? AND status <> 'failed' LIMIT 1",
                    [$template, $issuanceId]
                );
                if ($ja !== null) {
                    return null;
                }
            }
            Db::run(
                'INSERT INTO mail_messages
                 (tenant_id, issuance_id, stream, template, locale, to_email_enc, to_email_index, to_name, payload_json, scheduled_for)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, NOW()))',
                [
                    $tenantId, $issuanceId, $stream, $template, $locale,
                    Crypto::encrypt($toEmail), Crypto::blindIndex($toEmail),
                    $toName !== null ? mb_substr($toName, 0, 190) : null,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $scheduledFor,
                ]
            );
            return (int) Db::pdo()->lastInsertId();
        } catch (Throwable $e) {
            error_log('[verid4u] fila de e-mail: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Despacha o que está vencido na fila. Chamado por cron, nunca por uma
     * requisição de usuário.
     *
     * @return array{sent:int, failed:int, suppressed:int, left:int}
     */
    public static function runQueue(int $limit = 40): array
    {
        $resumo = ['sent' => 0, 'failed' => 0, 'suppressed' => 0, 'left' => 0];
        if (!self::enabled()) {
            return $resumo;
        }

        $pendentes = Db::all(
            "SELECT * FROM mail_messages
             WHERE status = 'queued' AND scheduled_for <= NOW() AND attempts < ?
             ORDER BY id ASC LIMIT " . max(1, min(200, $limit)),
            [self::MAX_ATTEMPTS]
        );

        foreach ($pendentes as $m) {
            $email = Crypto::decrypt((string) $m['to_email_enc']);
            if ($email === null || $email === '') {
                Db::run("UPDATE mail_messages SET status = 'failed', last_error = 'endereco ilegivel' WHERE id = ?", [$m['id']]);
                $resumo['failed']++;
                continue;
            }
            if (self::isSuppressed($email)) {
                Db::run("UPDATE mail_messages SET status = 'suppressed' WHERE id = ?", [$m['id']]);
                $resumo['suppressed']++;
                continue;
            }
            try {
                $payload = json_decode((string) ($m['payload_json'] ?? '[]'), true) ?: [];
                $peca = self::render((string) $m['template'], (string) $m['locale'], $payload);
                self::deliver($email, (string) ($m['to_name'] ?? ''), $peca);
                Db::run("UPDATE mail_messages SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?", [$m['id']]);
                $resumo['sent']++;
            } catch (Throwable $e) {
                $erro = mb_substr($e->getMessage(), 0, 255);
                $definitivo = self::isPermanent($e->getMessage());
                $tentativas = (int) $m['attempts'] + 1;
                $status = ($definitivo || $tentativas >= self::MAX_ATTEMPTS) ? 'failed' : 'queued';
                // Espera crescente: 5 min, 25 min, 2 h.
                $espera = (int) (5 * (5 ** max(0, $tentativas - 1)));
                Db::run(
                    'UPDATE mail_messages SET status = ?, attempts = ?, last_error = ?,
                     scheduled_for = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?',
                    [$status, $tentativas, $erro, $espera, $m['id']]
                );
                if ($definitivo) {
                    self::suppress($email, 'hard_bounce', $erro);
                }
                $resumo['failed']++;
                error_log('[verid4u] envio #' . $m['id'] . ': ' . $erro);
            }
        }

        $resto = Db::one("SELECT COUNT(*) AS n FROM mail_messages WHERE status = 'queued' AND scheduled_for <= NOW()");
        $resumo['left'] = (int) ($resto['n'] ?? 0);
        return $resumo;
    }

    /** Recusa definitiva: insistir só piora a reputação de envio. */
    private static function isPermanent(string $erro): bool
    {
        if (preg_match('/SMTP respondeu 5\d\d/', $erro) !== 1) {
            return false;
        }
        return (bool) preg_match('/(no such user|does not exist|unknown|invalid recipient|mailbox unavailable|user unknown)/i', $erro);
    }

    /* ------------------------------------------------------------ entrega */

    /** @param array{subject:string, text:string, html:string} $peca */
    public static function deliver(string $toEmail, string $toName, array $peca): void
    {
        $driver = (string) Config::get('mail.driver', 'log');
        $fromEmail = (string) Config::get('mail.from_email', 'nao-responda@' . APP_HOST);
        $fromName = (string) Config::get('mail.from_name', APP_NAME);
        $raw = self::build($toEmail, $toName, $fromEmail, $fromName, $peca);

        if ($driver === 'log') {
            error_log('[verid4u] e-mail simulado para ' . $toEmail . ': ' . $peca['subject']);
            return;
        }
        if ($driver === 'php') {
            $cabecalhos = 'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . ">\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n";
            if (!@mail($toEmail, self::encodeHeader($peca['subject']), $peca['text'], $cabecalhos)) {
                throw new RuntimeException('mail() recusou a mensagem.');
            }
            return;
        }
        $smtp = new Smtp((array) Config::get('mail', []));
        $smtp->send($fromEmail, $fromName, $toEmail, $raw);
    }

    /** @param array{subject:string, text:string, html:string} $peca */
    public static function build(string $toEmail, string $toName, string $fromEmail, string $fromName, array $peca): string
    {
        $limite = '=_v4u_' . bin2hex(random_bytes(12));
        $replyTo = (string) Config::get('mail.reply_to', '');

        $cabecalhos = [
            'Date: ' . date('r'),
            'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . '>',
            'To: ' . ($toName !== '' ? self::encodeName($toName) . ' <' . $toEmail . '>' : $toEmail),
            'Subject: ' . self::encodeHeader($peca['subject']),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . APP_HOST . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $limite . '"',
            'Auto-Submitted: auto-generated',
        ];
        if ($replyTo !== '') {
            $cabecalhos[] = 'Reply-To: ' . $replyTo;
        }

        $corpo = "--" . $limite . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($peca['text']), 76, "\r\n")
            . "--" . $limite . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($peca['html']), 76, "\r\n")
            . "--" . $limite . "--\r\n";

        return implode("\r\n", $cabecalhos) . "\r\n\r\n" . $corpo;
    }

    private static function encodeHeader(string $valor): string
    {
        return preg_match('/[^\x20-\x7E]/', $valor) === 1
            ? '=?UTF-8?B?' . base64_encode($valor) . '?='
            : $valor;
    }

    private static function encodeName(string $nome): string
    {
        $limpo = str_replace(['"', "\r", "\n"], '', $nome);
        return preg_match('/[^\x20-\x7E]/', $limpo) === 1
            ? self::encodeHeader($limpo)
            : '"' . $limpo . '"';
    }

    /* ------------------------------------------------------------- modelos */

    /**
     * Enfileira os avisos de vencimento.
     *
     * Dispara em marcos exatos — 90, 30 e 7 dias — e não numa faixa, para
     * que o titular receba três avisos ao longo do tempo e não noventa.
     * O nome do modelo leva o marco, e é isso que impede a repetição.
     *
     * @return array{queued:int, scanned:int}
     */
    public static function scheduleExpiryWarnings(array $marcos = [90, 30, 7]): array
    {
        $enfileirados = 0;
        $vistos = 0;

        foreach ($marcos as $dias) {
            $linhas = Db::all(
                'SELECT i.id, i.tenant_id, i.code_base, i.holder_name, i.program_title, i.issuer_name,
                        i.issued_on, i.valid_until, h.email_enc
                 FROM issuances i
                 JOIN holders h ON h.id = i.holder_id
                 WHERE i.revoked_on IS NULL
                   AND i.valid_until = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                   AND h.email_enc IS NOT NULL
                 LIMIT 500',
                [$dias]
            );
            foreach ($linhas as $r) {
                $vistos++;
                $email = Crypto::decrypt($r['email_enc']);
                if ($email === null || $email === '') {
                    continue;
                }
                $locale = 'pt-br';
                $codigo = Code::format(Code::full((string) $r['code_base']));
                $id = self::queue(
                    'issuance_expiring_' . $dias,
                    $email,
                    (string) $r['holder_name'],
                    $locale,
                    [
                        'program'     => $r['program_title'],
                        'issuer'      => $r['issuer_name'],
                        'code'        => $codigo,
                        'days'        => $dias,
                        'issued_on'   => self::formatDate($r['issued_on'], $locale),
                        'valid_until' => self::formatDate($r['valid_until'], $locale),
                        'url'         => base_url() . '/' . $locale . '/v/' . $codigo,
                    ],
                    (int) $r['tenant_id'],
                    (int) $r['id']
                );
                if ($id !== null) {
                    $enfileirados++;
                }
            }
        }
        return ['queued' => $enfileirados, 'scanned' => $vistos];
    }

    /** Resolve uma frase solta do arquivo de textos do idioma. */
    public static function phrase(string $key, string $locale, array $vars = []): string
    {
        $arquivo = __DIR__ . '/../mail/strings.' . $locale . '.php';
        if (!is_file($arquivo)) {
            $arquivo = __DIR__ . '/../mail/strings.pt-br.php';
        }
        $strings = require $arquivo;
        $valor = is_array($strings) ? ($strings[$key] ?? '') : '';
        if (!is_string($valor)) {
            return '';
        }
        foreach ($vars as $k => $v) {
            $valor = str_replace('{' . $k . '}', (string) $v, $valor);
        }
        return $valor;
    }

    public static function formatDate(?string $iso, string $locale): string
    {
        return $iso === null || $iso === '' ? '' : (new I18n($locale))->date($iso);
    }

    /** @return array{subject:string, text:string, html:string} */
    public static function render(string $template, string $locale, array $payload): array
    {
        $arquivo = __DIR__ . '/../mail/strings.' . $locale . '.php';
        if (!is_file($arquivo)) {
            $arquivo = __DIR__ . '/../mail/strings.pt-br.php';
        }
        $strings = require $arquivo;
        if (!is_array($strings)) {
            throw new RuntimeException('Textos de e-mail ausentes.');
        }

        // issuance_expiring_30 usa os textos de issuance_expiring: o marco
        // só existe para a fila não mandar o mesmo aviso duas vezes.
        $base = preg_replace('/_\d+$/', '', $template) ?? $template;

        $t = static function (string $chave, array $vars = []) use ($strings, $base): string {
            $valor = $strings[$base . '.' . $chave] ?? $strings['common.' . $chave] ?? '';
            if (!is_string($valor)) {
                return '';
            }
            foreach ($vars as $k => $v) {
                $valor = str_replace('{' . $k . '}', (string) $v, $valor);
            }
            return $valor;
        };

        $assunto = $t('subject', $payload);
        $titulo = $t('heading', $payload);
        $paragrafos = array_values(array_filter([
            $t('p1', $payload), $t('p2', $payload), $t('p3', $payload),
        ], static fn (string $p): bool => $p !== ''));
        $cta = $t('cta', $payload);
        $url = (string) ($payload['url'] ?? '');

        $texto = $titulo . "\n\n" . implode("\n\n", $paragrafos);
        if ($url !== '') {
            $texto .= "\n\n" . ($cta !== '' ? $cta . ': ' : '') . $url;
        }
        $texto .= "\n\n" . $t('footer', $payload) . "\n" . APP_HOST . "\n";

        ob_start();
        $mailTitulo = $titulo;
        $mailParagrafos = $paragrafos;
        $mailCta = $cta;
        $mailUrl = $url;
        $mailRodape = $t('footer', $payload);
        $mailPre = $t('preheader', $payload);
        require __DIR__ . '/../mail/layout.php';
        $html = (string) ob_get_clean();

        return ['subject' => $assunto, 'text' => $texto, 'html' => $html];
    }
}
