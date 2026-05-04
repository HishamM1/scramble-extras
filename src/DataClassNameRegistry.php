<?php

namespace PawelJadanowski\ScrambleExtras;

/**
 * Tracks the short-name → FQCN map for Data classes whose schemas have been
 * registered with Scramble during the current process. Used by
 * LaravelDataReflector to translate `$ref: '#/components/schemas/JobData'`
 * back to `App\Data\JobData` when persisting cache dependencies.
 */
class DataClassNameRegistry
{
    /** @var array<string, string> */
    private static array $shortToFqcn = [];

    public static function register(string $fqcn): void
    {
        $short = class_basename($fqcn);
        static::$shortToFqcn[$short] = $fqcn;
    }

    public static function resolve(string $shortName): ?string
    {
        return static::$shortToFqcn[$shortName] ?? null;
    }

    public static function reset(): void
    {
        static::$shortToFqcn = [];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return static::$shortToFqcn;
    }
}
