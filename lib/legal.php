<?php
declare(strict_types=1);

/** Carrega e prepara os documentos legais. */
final class Legal
{
    public const DOCS = ['termos', 'privacidade'];

    public static function empresa(): array
    {
        $f = __DIR__ . '/../content/legal/empresa.php';
        $d = is_file($f) ? require $f : [];
        return is_array($d) ? $d : [];
    }

    /** Campos obrigatórios ainda em branco. Ficam visíveis no documento. */
    public static function pendencias(): array
    {
        $e = self::empresa();
        $rotulos = ['cnpj' => 'CNPJ', 'endereco' => 'endereço', 'foro' => 'comarca do foro'];
        $faltando = [];
        foreach ($rotulos as $k => $rotulo) {
            if (trim((string) ($e[$k] ?? '')) === '') {
                $faltando[] = $rotulo;
            }
        }
        return $faltando;
    }

    /** @return array{titulo:string, resumo:string, secoes:list<array>}|null */
    public static function doc(string $nome, string $locale): ?array
    {
        if (!in_array($nome, self::DOCS, true)) {
            return null;
        }
        foreach ([$locale, 'pt-br'] as $tentativa) {
            $f = __DIR__ . '/../content/legal/' . $nome . '.' . $tentativa . '.php';
            if (is_file($f)) {
                $d = require $f;
                return is_array($d) ? $d : null;
            }
        }
        return null;
    }

    /** Substitui os marcadores pelos dados da empresa. */
    public static function fill(string $texto): string
    {
        $e = self::empresa();
        $cnpj = trim((string) ($e['cnpj'] ?? ''));
        $end  = trim((string) ($e['endereco'] ?? ''));
        $foro = trim((string) ($e['foro'] ?? ''));

        return strtr($texto, [
            '{razao_social}'  => (string) ($e['razao_social'] ?? 'Inspire4U'),
            '{email_dpo}'     => (string) ($e['email_dpo'] ?? ''),
            '{email_contato}' => (string) ($e['email_contato'] ?? ''),
            '{cnpj_frase}'    => $cnpj !== '' ? ', inscrita no CNPJ sob o nº ' . $cnpj : '',
            '{endereco_frase}'=> $end !== '' ? ', com sede em ' . $end : '',
            // Enquanto a comarca não está definida, o foro é o do contratante.
            // O padrão inverso — foro da contratada — é cláusula que o CDC
            // trata como abusiva quando o outro lado é consumidor, e boa parte
            // dos nossos clientes pequenos é. Errar para o lado do cliente aqui
            // não custa nada; errar para o nosso lado derruba a cláusula.
            '{foro_frase}'    => $foro !== '' ? 'da comarca de ' . $foro : 'do domicílio do contratante',
        ]);
    }

    /**
     * Converte o texto para HTML.
     * Escapa tudo primeiro e só então reconhece **negrito** — nesta ordem,
     * nunca na inversa, senão o escape desfaz a marcação.
     */
    public static function html(string $texto): string
    {
        $seguro = e(self::fill($texto));
        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $seguro) ?? $seguro;
    }
}
