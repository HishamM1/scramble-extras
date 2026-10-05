<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
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
 *  - Prepends the 422-for-Data-DTO and route-methods-expansion fixes directly
 *    onto Scramble's OperationTransformers pipeline (registerExtensions()
 *    would land them too late - see LaravelDataValidationExceptionExtension).
 *  - Binds the SchemaCache singleton (file-backed, per-class mtime
 *    invalidation) and registers the cache:clear artisan command.
 */
class ScrambleExtrasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/scramble-extras.php', 'scramble-extras');

        if (interface_exists(\Dedoc\Scramble\Contracts\RouteProvider::class)) {
            $this->app->extend(\Dedoc\Scramble\Contracts\RouteProvider::class, fn ($provider) => new LaravelActionsRouteProvider($provider));
        }

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
            LaravelDataResponseMethodReturnTypeExtension::class,
        ];

        Scramble::registerExtensions($extensions);

        // Only register the query-builder extension when the (optional) Spatie
        // package is actually installed.
        if (class_exists(\Spatie\QueryBuilder\QueryBuilder::class)) {
            Scramble::configure()->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers->append(QueryBuilderOperationExtension::class);
            });
        }

        if (class_exists(\Lorisleiva\Actions\ActionRequest::class)) {
            FormRequestParametersExtractor::ignoreInstanceOf(\Lorisleiva\Actions\ActionRequest::class);
        }

        Scramble::configure()->withParametersExtractors(function (ParametersExtractors $extractors) {
            $extractors->append(LaravelDataParametersExtractor::class);

            if (class_exists(\Lorisleiva\Actions\ActionRequest::class)) {
                $extractors->append(LaravelActionRulesParametersExtractor::class);
            }
        });

        Scramble::configure()->withOperationTransformers(function (OperationTransformers $transformers) {
            $transformers->prepend(LaravelDataValidationExceptionExtension::class);
            $transformers->append(MethodAwareOperationIdExtension::class);
        });

        // resolveOperationMethodsUsing() doesn't exist before dedoc/scramble
        // ~0.13.2x (it's still on the single-operation-per-route Generator on
        // this package's ^0.13.0 floor, with no configurable resolver at all -
        // see RouteMethodsResolver's docblock). Guard it so older installs
        // degrade gracefully instead of a hard boot-time error.
        if (
            $this->app['config']->get('scramble-extras.expand_route_methods', true)
            && method_exists(Scramble::configure(), 'resolveOperationMethodsUsing')
        ) {
            Scramble::configure()->resolveOperationMethodsUsing(RouteMethodsResolver::resolve(...));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearSchemaCacheCommand::class,
            ]);
        }
    }
}
