<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;
use PawelJadanowski\ScrambleExtras\Console\Commands\ClearSchemaCacheCommand;

/**
 * Boots scramble-extras's integrations into Scramble:
 *  - Registers TypeToSchema/Operation/Infer extensions on Scramble's global
 *    extension list, so they participate in the same pipeline as user-defined
 *    extensions registered via config/scramble.php.
 *  - Prepends a Spatie Data parameter extractor so request bodies typed with
 *    Data subclasses produce inlined input schemas (kept separate from the
 *    reusable output components to stay faithful to input-only rules).
 *  - Binds the SchemaCache singleton (file-backed, per-class mtime
 *    invalidation) and registers the cache:clear artisan command.
 */
class ScrambleExtrasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/scramble-extras.php', 'scramble-extras');

        $this->app->singleton(SchemaCache::class, function ($app) {
            $configured = $app['config']->get('scramble-extras.cache.path');

            return new SchemaCache(
                $configured ?: storage_path('framework/cache/scramble-extras/schemas.php'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/scramble-extras.php' => $this->app->configPath('scramble-extras.php'),
        ], 'scramble-extras-config');

        $extensions = [
            LaravelDataTypeToSchemaExtension::class,
            PaginatedDataCollectionTypeToSchemaExtension::class,
            CursorPaginatedDataCollectionTypeToSchemaExtension::class,
            DataCollectionTypeToSchemaExtension::class,
            LaravelDataReturnTypeExtension::class,
        ];

        // Only register the query-builder extension when the (optional) Spatie
        // package is actually installed.
        if (class_exists(\Spatie\QueryBuilder\QueryBuilder::class)) {
            $extensions[] = QueryBuilderOperationExtension::class;
        }

        Scramble::registerExtensions($extensions);

        Scramble::configure()->withParametersExtractors(function (ParametersExtractors $extractors) {
            $extractors->prepend(LaravelDataParametersExtractor::class);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearSchemaCacheCommand::class,
            ]);
        }
    }
}
