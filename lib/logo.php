<?php
declare(strict_types=1);

/**
 * A logo do emissor.
 *
 * Fica em storage/ e é servida por uma rota nossa, nunca por caminho direto.
 * Para ser exato: storage/ está dentro de public_html, e o que o protege é o
 * RedirectMatch do .htaccess mais o nome aleatório do arquivo — não é um
 * diretório fora da raiz. A diferença importa se o .htaccess um dia falhar,
 * e por isso o que entra ali é só imagem conferida pelo conteúdo, com nome e
 * extensão escolhidos por nós.
 *
 * Guardar arquivo enviado por cliente com o nome que ele mandou, num
 * diretório que o servidor executa, é o caminho mais curto para alguém subir
 * um .php chamado de .png. Nada do nome original sobrevive aqui.
 *
 * Só PNG e JPEG. SVG ficou de fora de propósito: SVG é um documento que pode
 * trazer script, e sanear SVG direito é um projeto, não uma função. Quando o
 * emissor precisar de vetor para impressão grande, ele põe o PNG grande; a
 * perda é real e é menor que a de servir XML de terceiro no nosso domínio.
 */
final class Logo
{
    public const MAX_BYTES = 1048576;        // 1 MB
    public const MIN_WIDTH = 240;
    public const MAX_SIDE  = 2000;

    /** @var array<int,string> tipo detectado => extensão que gravamos */
    private const ACEITOS = [
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
    ];

    public static function dir(): string
    {
        return __DIR__ . '/../storage/logos';
    }

    public static function pathFor(string $arquivo): string
    {
        return self::dir() . '/' . basename($arquivo);
    }

    /**
     * Recebe um item de $_FILES e grava.
     *
     * @return array{ok:bool,error:?string,file:?string}
     */
    public static function store(int $issuerId, array $file): array
    {
        $erro = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            return self::fail('logo.none');
        }
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            return self::fail('logo.too_big');
        }
        if ($erro !== UPLOAD_ERR_OK) {
            return self::fail('logo.failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return self::fail('logo.failed');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return self::fail('logo.too_big');
        }

        // getimagesize decide o tipo pelo conteúdo, nunca pelo nome nem pelo
        // Content-Type que o navegador mandou — os dois são escolha de quem
        // envia, e quem envia é exatamente de quem estamos nos protegendo.
        $info = @getimagesize($tmp);
        if ($info === false || !isset(self::ACEITOS[$info[2]])) {
            return self::fail('logo.bad_type');
        }
        [$largura, $altura] = [(int) $info[0], (int) $info[1]];
        if ($largura < self::MIN_WIDTH) {
            return self::fail('logo.too_small');
        }
        if ($largura > self::MAX_SIDE || $altura > self::MAX_SIDE) {
            return self::fail('logo.too_large_px');
        }

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return self::fail('logo.failed');
        }

        // O nome é nosso, e a extensão vem do tipo detectado. Nada do nome
        // original sobrevive: nome de arquivo enviado por terceiro não entra
        // em caminho de sistema de arquivos.
        $nome = $issuerId . '-' . bin2hex(random_bytes(8)) . '.' . self::ACEITOS[$info[2]];
        if (!@move_uploaded_file($tmp, $dir . '/' . $nome)) {
            return self::fail('logo.failed');
        }
        @chmod($dir . '/' . $nome, 0600);

        return ['ok' => true, 'error' => null, 'file' => $nome];
    }

    /** @return array{ok:bool,error:string,file:null} */
    private static function fail(string $chave): array
    {
        return ['ok' => false, 'error' => $chave, 'file' => null];
    }

    public static function remove(?string $arquivo): void
    {
        if ($arquivo === null || $arquivo === '') {
            return;
        }
        @unlink(self::pathFor($arquivo));
    }

    public static function contentType(string $arquivo): string
    {
        return substr($arquivo, -4) === '.png' ? 'image/png' : 'image/jpeg';
    }

    /** Nome de arquivo aceitável, para não deixar caminho entrar pela URL. */
    public static function isSafeName(string $arquivo): bool
    {
        return preg_match('/^[0-9]+-[a-f0-9]{16}\.(png|jpg)$/', $arquivo) === 1;
    }
}
