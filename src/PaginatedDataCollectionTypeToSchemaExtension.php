<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\NullType as OpenApiNullType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType as ScrambleObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\LaravelData\PaginatedDataCollection;

class PaginatedDataCollectionTypeToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        if ($type instanceof Generic && is_a($type->name, PaginatedDataCollection::class, true)) {
            return $this->getItemClass($type) !== null;
        }

        return $type instanceof ScrambleObjectType
            && is_a($type->name, PaginatedDataCollection::class, true);
    }

    public function toSchema(Type $type): OpenApiType
    {
        $itemSchema = $this->getItemSchema($type);

        $object = new OpenApiObjectType;
        $object
            ->addProperty('data', (new OpenApiArrayType)->setItems($itemSchema))
            ->addProperty('links', self::linksArray())
            ->addProperty('meta', $this->metaObject())
            ->setRequired(['data', 'links', 'meta']);

        return $object;
    }

    public function toResponse(Type $type): ?Response
    {
        $schema = $this->toSchema($type);

        return Response::make(200)
            ->description('Paginated set of data')
            ->setContent('application/json', Schema::fromType($schema));
    }

    /**
     * The item type is always the last template parameter: spatie/laravel-data
     * declares these collections as `<TKey of array-key, TValue>` (matching
     * Illuminate\Support\Collection), so a spec-correct two-argument generic
     * like `PaginatedDataCollection<int, ProductData>` carries the Data class
     * at index 1, not 0. Flow-inferred generics (no docblock, resolved from a
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
            return new OpenApiObjectType;
        }

        return $this->openApiTransformer->transform(new ScrambleObjectType($itemClass));
    }

    public static function linksArray(): OpenApiArrayType
    {
        $linkObject = new OpenApiObjectType;
        $linkObject
            ->addProperty('url', (new OpenApiStringType)->format('uri')->nullable(true))
            ->addProperty('label', new OpenApiStringType)
            ->addProperty('page', (new OpenApiIntegerType)->nullable(true))
            ->addProperty('active', new OpenApiBooleanType)
            ->setRequired(['url', 'label', 'active']);

        return (new OpenApiArrayType)->setItems($linkObject);
    }

    protected function metaObject(): OpenApiObjectType
    {
        $object = new OpenApiObjectType;

        $url = (new OpenApiStringType)->format('uri');
        $nullableUrl = (new OpenApiStringType)->format('uri')->nullable(true);

        $object
            ->addProperty('current_page', new OpenApiIntegerType)
            ->addProperty('first_page_url', $url)
            ->addProperty('from', (new OpenApiIntegerType)->nullable(true))
            ->addProperty('last_page', new OpenApiIntegerType)
            ->addProperty('last_page_url', $url)
            ->addProperty('next_page_url', $nullableUrl)
            ->addProperty('path', $url)
            ->addProperty('per_page', new OpenApiIntegerType)
            ->addProperty('prev_page_url', $nullableUrl)
            ->addProperty('to', (new OpenApiIntegerType)->nullable(true))
            ->addProperty('total', new OpenApiIntegerType)
            ->setRequired([
                'current_page', 'first_page_url', 'from', 'last_page', 'last_page_url',
                'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total',
            ]);

        return $object;
    }
}
