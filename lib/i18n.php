<?php
declare(strict_types=1);

final class I18n
{
    public const DEFAULT = 'pt-br';

    /** @var array<string, array{tag:string,name:string,dir:string}> */
    public const LOCALES = [
        'pt-br' => ['tag' => 'pt-BR', 'name' => 'Português (Brasil)', 'short' => 'PT-BR'],
        'pt'    => ['tag' => 'pt-PT', 'name' => 'Português',          'short' => 'PT'],
        'es'    => ['tag' => 'es',    'name' => 'Español',            'short' => 'ES'],
        'en'    => ['tag' => 'en-GB', 'name' => 'English',            'short' => 'EN'],
    ];

    private string $locale;
    /** @var array<string, mixed> */
    private array $strings;

    public function __construct(string $locale)
    {
        $this->locale = isset(self::LOCALES[$locale]) ? $locale : self::DEFAULT;
        $file = __DIR__ . '/../i18n/' . $this->locale . '.php';
        $loaded = is_file($file) ? require $file : [];
        $this->strings = is_array($loaded) ? $loaded : [];
    }

    public static function isLocale(string $candidate): bool
    {
        return isset(self::LOCALES[$candidate]);
    }

    /** Lê o Accept-Language e escolhe o local mais próximo. */
    public static function detect(): string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (!is_string($header) || $header === '') {
            return self::DEFAULT;
        }
        foreach (explode(',', $header) as $chunk) {
            $tag = strtolower(trim(explode(';', $chunk)[0]));
            if ($tag === 'pt-br') {
                return 'pt-br';
            }
            if ($tag === 'pt' || str_starts_with($tag, 'pt-')) {
                return 'pt';
            }
            if ($tag === 'es' || str_starts_with($tag, 'es-')) {
                return 'es';
            }
            if ($tag === 'en' || str_starts_with($tag, 'en-')) {
                return 'en';
            }
        }
        return self::DEFAULT;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function tag(): string
    {
        return self::LOCALES[$this->locale]['tag'];
    }

    /** @param array<string,string|int> $params */
    public function t(string $key, array $params = []): string
    {
        $value = $this->strings[$key] ?? $key;
        if (!is_string($value)) {
            return $key;
        }
        foreach ($params as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }
        return $value;
    }

    public function has(string $key): bool
    {
        return isset($this->strings[$key]);
    }

    /** Data por extenso: evita a ambiguidade entre 03/10 e 10/03. */
    public function date(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return $this->t('value.none');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        if ($date === false) {
            return $iso;
        }
        $months = $this->strings['months'] ?? [];
        $month = is_array($months) ? ($months[(int) $date->format('n') - 1] ?? $date->format('M')) : $date->format('M');
        $pattern = $this->t('format.date');
        return str_replace(
            ['{d}', '{m}', '{y}'],
            [$date->format('j'), (string) $month, $date->format('Y')],
            $pattern
        );
    }

    public function url(string $path = ''): string
    {
        return '/' . $this->locale . ($path === '' ? '/' : $path);
    }
}
