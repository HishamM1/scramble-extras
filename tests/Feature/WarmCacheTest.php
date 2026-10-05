<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Feature;

use Dedoc\Scramble\Generator;
use PawelJadanowski\ScrambleExtras\SchemaCache;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\CollidingController;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserController;
use PawelJadanowski\ScrambleExtras\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class WarmCacheTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $path = sys_get_temp_dir().'/scramble-extras-warm-'.uniqid().'.php';
        $app['config']->set('scramble-extras.cache.enabled', true);
        $app['config']->set('scramble-extras.cache.path', $path);
    }

    protected function defineRoutes($router): void
    {
        $router->get('api/colliding', [CollidingController::class, 'show']);
        $router->post('api/colliding', [CollidingController::class, 'store']);
        $router->get('api/filtered-first', [UserController::class, 'filtered']);
        $router->get('api/filtered-default', [UserController::class, 'filteredWithDefault']);
        $router->post('api/multipart-lines', [UserController::class, 'multipartLines']);
        $router->post('api/ruled', [UserController::class, 'ruled']);
        $router->get('api/filtered-second', [UserController::class, 'filteredAgain']);
    }

    #[Test]
    public function every_reference_resolves_on_cold_and_warm_runs(): void
    {
        foreach (['cold', 'warm'] as $run) {
            $result = app(Generator::class)();
            $openApi = is_array($result) ? $result : $result->toArray();
            $schemas = $openApi['components']['schemas'];

            $this->assertGreaterThanOrEqual(3, count($schemas), $run);
            $this->assertSame([], $this->unresolved($openApi, $schemas), $run);
            $this->assertArrayNotHasKey('description', $schemas['CollidingData'] ?? [], $run);
        }
    }

    #[Test]
    public function query_parameters_survive_a_cache_hit_within_one_run(): void
    {
        $result = app(Generator::class)();
        $paths = (is_array($result) ? $result : $result->toArray())['paths'];

        foreach (['/filtered-first', '/filtered-second'] as $path) {
            $names = array_column($paths[$path]['get']['parameters'], 'name');

            $this->assertContains('name', $names, $path);
            $this->assertContains('contact', $names, $path);
        }

        $this->assertContains('tags[]', array_column($paths['/filtered-first']['get']['parameters'], 'name'));
        $this->assertSame($paths['/filtered-first']['get']['parameters'], $paths['/filtered-second']['get']['parameters']);
    }

    #[Test]
    public function warm_document_equals_uncached_document(): void
    {
        config()->set('scramble-extras.cache.enabled', false);
        $baseline = $this->document();

        config()->set('scramble-extras.cache.enabled', true);
        $this->document();
        app(SchemaCache::class)->flush();
        app()->forgetInstance(SchemaCache::class);
        $warm = $this->document();

        $this->assertEquals($baseline, $warm);

        $default = collect($warm['paths']['/filtered-default']['get']['parameters'])->firstWhere('name', 'age');
        $this->assertSame(25, $default['schema']['default']);
    }

    #[Test]
    public function multipart_array_of_references_is_not_flattened_on_a_warm_cache(): void
    {
        $cold = $this->document();
        app(SchemaCache::class)->flush();
        app()->forgetInstance(SchemaCache::class);
        $warm = $this->document();

        foreach (['cold' => $cold, 'warm' => $warm] as $run => $document) {
            $schema = $document['components']['schemas']['MultipartLinesDataInput'];

            $this->assertArrayHasKey('lines', $schema['properties'], $run);
            $this->assertArrayNotHasKey('lines[]', $schema['properties'], $run);
            $this->assertContains('lines', $schema['required'], $run);
            $this->assertNotContains('lines[]', $schema['required'], $run);
            $this->assertArrayHasKey('files[]', $schema['properties'], $run);
        }
    }

    private function document(): array
    {
        $result = app(Generator::class)();

        return is_array($result) ? $result : $result->toArray();
    }

    private function unresolved(array $openApi, array $schemas): array
    {
        $missing = [];
        array_walk_recursive($openApi, function ($value, $key) use (&$missing, $schemas) {
            if ($key === '$ref' && str_starts_with($value, '#/components/schemas/')) {
                $name = substr($value, strlen('#/components/schemas/'));
                if (! array_key_exists($name, $schemas)) {
                    $missing[] = $name;
                }
            }
        });

        return $missing;
    }
}
