<?php

namespace PawelJadanowski\ScrambleExtras;

use Illuminate\Routing\Route;

/**
 * Scramble core's default operation-methods resolver only ever documents the
 * FIRST HTTP method a route responds to (`$route->methods()[0]`). Laravel's
 * `Route::apiResource()` registers its update action for both PUT and PATCH
 * on a single Route object, so with the default resolver only PUT ever gets
 * documented - PATCH silently disappears from the generated spec even though
 * it's a fully working, commonly used endpoint.
 *
 * This documents every method the route actually responds to, except HEAD
 * and OPTIONS (which are implicit/redundant next to GET and CORS preflight
 * respectively, and aren't normally documented as their own operations).
 */
class RouteMethodsResolver
{
    /**
     * @return list<string>
     */
    public static function resolve(Route $route): array
    {
        $methods = array_values(array_diff(
            array_map('strtoupper', $route->methods()),
            ['HEAD', 'OPTIONS'],
        ));

        return $methods === [] ? [$route->methods()[0]] : $methods;
    }
}
