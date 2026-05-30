<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schema cache
    |--------------------------------------------------------------------------
    |
    | scramble-extras caches the schemas it builds from your spatie/laravel-data
    | classes so a full `scramble:export` / docs request doesn't re-reflect and
    | re-parse every Data class on every run. The cache is invalidated per class
    | whenever the source file's mtime changes.
    |
    | You'll typically want it enabled in production and CI, and may prefer it
    | disabled while actively iterating on Data classes locally.
    |
    */

    'cache' => [

        'enabled' => env('SCRAMBLE_EXTRAS_CACHE', true),

        // Absolute path to the cache file. When null, defaults to
        // storage/framework/cache/scramble-extras/schemas.php.
        'path' => env('SCRAMBLE_EXTRAS_CACHE_PATH'),

    ],

];
