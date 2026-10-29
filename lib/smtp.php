<?php
declare(strict_types=1);

/**
 * Cliente SMTP mínimo, sem dependência externa.
 *
 * Existe para que o provedor de e-mail seja uma linha de configuração, não
 * uma decisão de arquitetura: Resend, SES, Postmark, Brevo e ZeptoMail todos
 * falam SMTP. Trocar de provedor passa a ser trocar host, usuário e senha —
 * e o documento de estratégia pede justamente um segundo provedor pronto,
 * porque e-mail é ponto único de falha do negócio.
 */
final class Smtp
{
    private $socket = null;
    private array $config;
    private array $log = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @return list<string> as linhas da conversa, úteis para diagnóstico */
    public function transcript(): array
    {
        return $this->log;
    }

    public function send(string $fromEmail, string $fromName, string $to, string $rawMessage): void
    {
        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 587);
        $secure = (string) ($this->config['secure'] ?? 'tls');
        $timeout = (int) ($this->config['timeout'] ?? 20);

        if ($host === '') {
            throw new RuntimeException('SMTP sem host configurado.');
        }

        $endereco = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $errNo = 0;
        $errStr = '';
        $this->socket = @stream_socket_client($endereco, $errNo, $errStr, $timeout, STREAM_CLIENT_CONNECT, $contexto);
        if ($this->socket === false) {
            $this->socket = null;
            throw new RuntimeException('Não foi possível conectar ao SMTP: ' . $errStr);
        }
        stream_set_timeout($this->socket, $timeout);

        try {
            $this->expect(220);
            $ehlo = $this->config['ehlo'] ?? (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
            $this->command('EHLO ' . $ehlo, 250);

            if ($secure === 'tls') {
                $this->command('STARTTLS', 220);
                $ok = stream_socket_enable_crypto(
                    $this->socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                );
                if ($ok !== true) {
                    throw new RuntimeException('Falha ao iniciar TLS.');
                }
                $this->command('EHLO ' . $ehlo, 250);
            }

            $user = (string) ($this->config['user'] ?? '');
            $pass = (string) ($this->config['pass'] ?? '');
            if ($user !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($user), 334);
                $this->command(base64_encode($pass), 235);
            }

            $this->command('MAIL FROM:<' . $fromEmail . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', 354);

            // Dot-stuffing: linha que começa com ponto ganha um ponto a mais,
            // senão o servidor a lê como fim da mensagem.
            $corpo = preg_replace('/^\./m', '..', $rawMessage);
            $this->write($corpo . "\r\n.\r\n");
            $this->expect(250);

            $this->command('QUIT', [221, 250]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    /** @param int|list<int> $esperado */
    private function command(string $linha, $esperado): string
    {
        $this->write($linha . "\r\n");
        $visivel = str_starts_with($linha, 'AUTH') || strlen($linha) > 120 ? '[omitido]' : $linha;
        $this->log[] = '> ' . $visivel;
        return $this->expect($esperado);
    }

    private function write(string $dados): void
    {
        if (!is_resource($this->socket) || fwrite($this->socket, $dados) === false) {
            throw new RuntimeException('Conexão SMTP interrompida ao escrever.');
        }
    }

    /** @param int|list<int> $esperado */
    private function expect($esperado): string
    {
        $codigos = is_array($esperado) ? $esperado : [$esperado];
        $resposta = '';
        while (is_resource($this->socket)) {
            $linha = fgets($this->socket, 1024);
            if ($linha === false) {
                throw new RuntimeException('Conexão SMTP interrompida ao ler.');
            }
            $resposta .= $linha;
            // "250-" indica continuação; "250 " encerra.
            if (strlen($linha) >= 4 && $linha[3] === ' ') {
                break;
            }
        }
        $this->log[] = '< ' . trim($resposta);
        $codigo = (int) substr(ltrim($resposta), 0, 3);
        if (!in_array($codigo, $codigos, true)) {
            throw new RuntimeException('SMTP respondeu ' . trim($resposta));
        }
        return $resposta;
    }
}
