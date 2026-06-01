<?php

declare(strict_types=1);

/**
 * Request-scoped in-memory cache (single PHP request lifetime).
 * Use namespaces to group related keys (schema, checklist, app settings, etc.).
 */
final class RequestCache
{
    /** @var array<string, mixed> */
    private static array $store = [];

    public static function key(string $namespace, string $id): string
    {
        return $namespace . ':' . $id;
    }

    public static function has(string $namespace, string $id): bool
    {
        return array_key_exists(self::key($namespace, $id), self::$store);
    }

    public static function get(string $namespace, string $id, mixed $default = null): mixed
    {
        $k = self::key($namespace, $id);

        return array_key_exists($k, self::$store) ? self::$store[$k] : $default;
    }

    public static function set(string $namespace, string $id, mixed $value): void
    {
        self::$store[self::key($namespace, $id)] = $value;
    }

    /**
     * @template T
     * @param callable(): T $factory
     * @return T
     */
    public static function remember(string $namespace, string $id, callable $factory): mixed
    {
        $k = self::key($namespace, $id);
        if (array_key_exists($k, self::$store)) {
            return self::$store[$k];
        }

        $value = $factory();
        self::$store[$k] = $value;

        return $value;
    }

    public static function forget(string $namespace, string $id): void
    {
        unset(self::$store[self::key($namespace, $id)]);
    }

    public static function forgetNamespace(string $namespace): void
    {
        $prefix = $namespace . ':';
        foreach (array_keys(self::$store) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset(self::$store[$k]);
            }
        }
    }

    public static function clear(): void
    {
        self::$store = [];
    }
}
