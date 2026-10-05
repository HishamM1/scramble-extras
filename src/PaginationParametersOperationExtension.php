<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class PaginationParametersOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (strtolower($operation->method) !== 'get') {
            return;
        }

        $returnType = $routeInfo->getReturnType();

        if ($returnType === null) {
            return;
        }

        if ($this->contains($returnType, [PaginatedDataCollection::class, PaginatedListResponse::class])) {
            $this->addParameter($operation, 'page', (new OpenApiIntegerType)->setMin(1));
        } elseif ($this->contains($returnType, [CursorPaginatedDataCollection::class])) {
            $this->addParameter($operation, 'cursor', new OpenApiStringType);
        }
    }

    protected function contains(Type $type, array $classes): bool
    {
        if ($type instanceof Union) {
            foreach ($type->types as $item) {
                if ($this->contains($item, $classes)) {
                    return true;
                }
            }

            return false;
        }

        if (! $type instanceof ObjectType) {
            return false;
        }

        foreach ($classes as $class) {
            if (is_a($type->name, $class, true)) {
                return true;
            }
        }

        return $type instanceof Generic
            && is_a($type->name, JsonResponse::class, true)
            && isset($type->templateTypes[0])
            && $this->contains($type->templateTypes[0], $classes);
    }

    protected function addParameter(Operation $operation, string $name, \Dedoc\Scramble\Support\Generator\Types\Type $schema): void
    {
        foreach ($operation->parameters as $existing) {
            if ($existing->in === 'query' && ($existing->name === $name || ($name === 'page' && str_starts_with($existing->name, 'page[')))) {
                return;
            }
        }

        $operation->addParameters([
            (new Parameter($name, 'query'))->setSchema(Schema::fromType($schema)),
        ]);
    }
}
