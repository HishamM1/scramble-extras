<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Feature;

use Dedoc\Scramble\Generator;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\CollidingController;
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
