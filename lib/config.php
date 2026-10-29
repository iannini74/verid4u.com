<?php
declare(strict_types=1);

final class Config
{
    private static ?array $values = null;

    public static function load(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }
        $local = __DIR__ . '/config.local.php';
        if (!is_file($local)) {
            throw new RuntimeException('config.local.php ausente. Copie lib/config.example.php.');
        }
        $loaded = require $local;
        self::$values = is_array($loaded) ? $loaded : [];
        return self::$values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::load();
        if (!str_contains($key, '.')) {
            return $values[$key] ?? $default;
        }
        $cursor = $values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }
        return $cursor;
    }
}
