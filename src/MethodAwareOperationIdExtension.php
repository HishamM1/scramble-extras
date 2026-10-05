<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\UniqueNameOptions;
use Dedoc\Scramble\Support\RouteInfo;

class MethodAwareOperationIdExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $methods = array_diff(array_map('strtolower', $routeInfo->route->methods()), ['head']);
        $options = $operation->getAttribute('operationId');

        if (count($methods) < 2 || ! $options instanceof UniqueNameOptions) {
            return;
        }

        $operation->setAttribute('operationId', new UniqueNameOptions(
            eloquent: $options->eloquent === null ? null : $options->eloquent.$options->separator.$operation->method,
            unique: [...$options->unique, $operation->method],
            separator: $options->separator,
            fallbackEloquentPartsCount: $options->fallbackEloquentPartsCount,
        ));
    }
}
