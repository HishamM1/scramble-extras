<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

/**
 * Mirrors Scramble core's ErrorResponsesExtension::attachCustomRequestExceptions()
 * for FormRequest: core only inspects controller arguments for a FormRequest
 * subclass to decide whether to document a 422 response. A spatie/laravel-data
 * Data-typed argument is resolved and validated the exact same way (Laravel's
 * validator, throwing Illuminate\Validation\ValidationException on failure),
 * but core has no knowledge of spatie/laravel-data, so a Data-typed action
 * never got the conventional 422 documented. This adds it.
 *
 * Registered with `prepend()`, not `append()` (see the service provider), so
 * it runs before core's ResponseExtension - the extension that actually turns
 * `$methodType->exceptions` into a documented response. Scramble's own
 * OperationTransformers::all() always runs prepends before the core
 * ErrorResponsesExtension/ResponseExtension pair, and appends after; landing
 * in `append()` (as this package's other extensions correctly do for
 * TypeToSchemaExtension, a different pipeline) would make this a silent no-op
 * here, since ResponseExtension would already have read the exceptions list.
 */
class LaravelDataValidationExceptionExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $methodType = $routeInfo->getActionType()) {
            return;
        }

        $hasDataArgument = collect($methodType->arguments)->contains(function (Type $argument) {
            $resolved = $argument instanceof ObjectType
                ? $argument
                : ($argument instanceof TemplateType ? $argument->is : null);

            return $resolved instanceof ObjectType
                && class_exists($resolved->name)
                && is_subclass_of($resolved->name, Data::class);
        });

        if (! $hasDataArgument) {
            return;
        }

        if (collect($methodType->exceptions)->contains(fn (Type $e) => $e->isInstanceOf(ValidationException::class))) {
            return;
        }

        $methodType->exceptions = [
            ...$methodType->exceptions,
            new ObjectType(ValidationException::class),
        ];
    }
}
