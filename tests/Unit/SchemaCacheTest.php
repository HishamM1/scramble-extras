<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use PawelJadanowski\ScrambleExtras\SchemaCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemaCacheTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/scramble-extras-test-'.uniqid().'/schemas.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
        @rmdir(dirname($this->path));
        parent::tearDown();
    }

    #[Test]
    public function put_does_not_write_until_flush(): void
    {
        $cache = new SchemaCache($this->path);
        $cache->put('Foo.out', ['mtime' => 1, 'deps' => [], 'array' => ['type' => 'object']]);

        $this->assertFileDoesNotExist($this->path, 'put() must defer the disk write');

        $cache->flush();

        $this->assertFileExists($this->path);
    }

    #[Test]
    public function roundtrip_persists_entries(): void
    {
        $cache = new SchemaCache($this->path);
        $entry = ['mtime' => 42, 'deps' => ['App\\Foo'], 'array' => ['type' => 'object']];
        $cache->put('Foo.out', $entry);
        $cache->flush();

        $fresh = new SchemaCache($this->path);

        $this->assertSame($entry, $fresh->get('Foo.out'));
    }

    #[Test]
    public function signature_is_not_exposed_as_an_entry(): void
    {
        $cache = new SchemaCache($this->path);
        $cache->put('Foo.out', ['mtime' => 1, 'deps' => [], 'array' => []]);
        $cache->flush();

        $fresh = new SchemaCache($this->path);

        $this->assertNull($fresh->get('__signature'));
        $this->assertNotNull($fresh->get('Foo.out'));
    }

    #[Test]
    public function clear_removes_file(): void
    {
        $cache = new SchemaCache($this->path);
        $cache->put('Foo.out', ['mtime' => 1, 'deps' => [], 'array' => []]);
        $cache->flush();
        $this->assertFileExists($this->path);

        $cache->clear();

        $this->assertFileDoesNotExist($this->path);
        $this->assertNull($cache->get('Foo.out'));
    }

    #[Test]
    public function stale_signature_invalidates_cache(): void
    {
        // Simulate a cache file written by an older/different signature.
        @mkdir(dirname($this->path), 0755, true);
        file_put_contents(
            $this->path,
            "<?php\n\nreturn ".var_export([
                'Foo.out' => ['mtime' => 1, 'deps' => [], 'array' => []],
                '__signature' => 'totally-different',
            ], true).";\n",
        );

        $cache = new SchemaCache($this->path);

        $this->assertNull($cache->get('Foo.out'), 'A mismatched signature should discard the cache');
    }

    #[Test]
    public function flush_is_noop_when_not_dirty(): void
    {
        $cache = new SchemaCache($this->path);
        $cache->flush();

        $this->assertFileDoesNotExist($this->path);
    }
}
