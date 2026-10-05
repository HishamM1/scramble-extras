<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Attributes\Api;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Contracts\RouteProvider;
use Dedoc\Scramble\GeneratorConfig;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use ReflectionMethod;

class LaravelActionsRouteProvider implements RouteProvider
{
    private const CONTROLLER_TRAITS = [
        'Lorisleiva\Actions\Concerns\AsController',
        'Lorisleiva\Actions\Concerns\AsAction',
    ];

    public function __construct(private RouteProvider $routeProvider) {}

    /**
     * @return Collection<int, Route>
     */
    public function get(GeneratorConfig $config): Collection
    {
        return $this->routeProvider
            ->get($config)
            ->map(fn (Route $route) => $this->pointAtAsController($route))
            ->filter(fn (Route $route) => $this->isDocumented($route, $config))
            ->values();
    }

    private function pointAtAsController(Route $route): Route
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses) || ! str_ends_with($uses, '@__invoke')) {
            return $route;
        }

        $class = substr($uses, 0, -strlen('@__invoke'));

        if (! $this->isControllerAction($class)) {
            return $route;
        }

        $clone = clone $route;
        $clone->uses($class.'@asController');

        return $clone;
    }

    private function isDocumented(Route $route, GeneratorConfig $config): bool
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses) || ! str_ends_with($uses, '@asController') || ! method_exists(...explode('@', $uses))) {
            return true;
        }

        $method = new ReflectionMethod(...explode('@', $uses));

        if ($method->getAttributes(ExcludeRouteFromDocs::class) !== []) {
            return false;
        }

        $api = $method->getAttributes(Api::class);

        return $api === [] || in_array($config->name, $api[0]->newInstance()->only, true);
    }

    private function isControllerAction(string $class): bool
    {
        if (! class_exists($class) || ! method_exists($class, 'asController')) {
            return false;
        }

        return array_intersect(self::CONTROLLER_TRAITS, array_keys(class_uses_recursive($class))) !== [];
    }
}
