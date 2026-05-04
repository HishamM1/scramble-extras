<?php

namespace PawelJadanowski\ScrambleExtras;

/**
 * File-based cache for fully-built Data class schemas. Persists across runs of
 * `php artisan scramble:export` so we don't re-reflect every Data class and
 * re-parse every PHPDoc on every export.
 *
 * Cache shape:
 *   [ "Fully\\Qualified\\Name.out" => [
 *       'mtime' => 1714000000,
 *       'deps'  => ['App\\Data\\JobData', ...],   // FQCNs to materialize on hit
 *       'array' => [...],                          // schema array form
 *   ], ... ]
 */
class SchemaCache
{
    /** @var array<string, array{mtime: int, deps: array<int, string>, array: array<string, mixed>}>|null */
    private ?array $entries = null;

    public function __construct(protected string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array{mtime: int, deps: array<int, string>, array: array<string, mixed>}|null
     */
    public function get(string $key): ?array
    {
        return $this->load()[$key] ?? null;
    }

    /**
     * @param  array{mtime: int, deps: array<int, string>, array: array<string, mixed>}  $entry
     */
    public function put(string $key, array $entry): void
    {
        $this->load();
        $this->entries[$key] = $entry;
        $this->persist();
    }

    public function clear(): void
    {
        $this->entries = [];
        if (file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    /**
     * @return array<string, array{mtime: int, deps: array<int, string>, array: array<string, mixed>}>
     */
    private function load(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }
        if (! file_exists($this->path)) {
            return $this->entries = [];
        }

        $loaded = require $this->path;

        return $this->entries = is_array($loaded) ? $loaded : [];
    }

    private function persist(): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $contents = "<?php\n\nreturn ".var_export($this->entries, true).";\n";
        $tmp = $this->path.'.tmp';
        file_put_contents($tmp, $contents);
        rename($tmp, $this->path);
    }
}
