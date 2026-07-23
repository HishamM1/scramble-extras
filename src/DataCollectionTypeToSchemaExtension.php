<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType as ScrambleObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\LaravelData\DataCollection;

/**
 * Renders `Spatie\LaravelData\DataCollection<T>` (the non-paginated wrapper)
 * as a plain `array<T>` JSON response.
 */
class DataCollectionTypeToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && is_a($type->name, DataCollection::class, true)
            && $this->getItemClass($type) !== null;
    }

    public function toSchema(Type $type): OpenApiType
    {
        return (new OpenApiArrayType)->setItems($this->getItemSchema($type));
    }

    public function toResponse(Type $type): ?Response
    {
        return Response::make(200)
            ->setContent('application/json', Schema::fromType($this->toSchema($type)));
    }

    /**
     * The item type is always the last template parameter: spatie/laravel-data
     * declares this collection as `<TKey of array-key, TValue>` (matching
     * Illuminate\Support\Collection), so a spec-correct two-argument generic
     * like `DataCollection<int, CategoryData>` carries the Data class at
     * index 1, not 0. Flow-inferred generics (no docblock, resolved from a
     * `Data::collect()` call) only ever carry the single value type, at index 0
     * — which is also "last" — so this covers both shapes.
     */
    protected function getItemClass(Type $type): ?string
    {
        if (! $type instanceof Generic || $type->templateTypes === []) {
            return null;
        }

        $inner = end($type->templateTypes);

        if ($inner instanceof ScrambleObjectType && class_exists($inner->name)) {
            return $inner->name;
        }

        return null;
    }

    protected function getItemSchema(Type $type): OpenApiType
    {
        $itemClass = $this->getItemClass($type);
        if ($itemClass === null) {
            return new \Dedoc\Scramble\Support\Generator\Types\ObjectType;
        }

        return $this->openApiTransformer->transform(new ScrambleObjectType($itemClass));
    }
}
