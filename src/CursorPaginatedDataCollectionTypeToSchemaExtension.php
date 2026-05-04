<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType as ScrambleObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\LaravelData\CursorPaginatedDataCollection;

/**
 * Renders `Spatie\LaravelData\CursorPaginatedDataCollection<T>` as the
 * standard Laravel cursor-paginator envelope (data + path + per_page +
 * next_cursor / prev_cursor + page urls).
 */
class CursorPaginatedDataCollectionTypeToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && is_a($type->name, CursorPaginatedDataCollection::class, true)
            && $this->getItemClass($type) !== null;
    }

    public function toSchema(Type $type): OpenApiType
    {
        $itemSchema = $this->getItemSchema($type);

        $object = new OpenApiObjectType;
        $object
            ->addProperty('data', (new OpenApiArrayType)->setItems($itemSchema))
            ->addProperty('path', (new OpenApiStringType)->format('uri'))
            ->addProperty('per_page', new OpenApiIntegerType)
            ->addProperty('next_cursor', (new OpenApiStringType)->nullable(true))
            ->addProperty('next_page_url', (new OpenApiStringType)->format('uri')->nullable(true))
            ->addProperty('prev_cursor', (new OpenApiStringType)->nullable(true))
            ->addProperty('prev_page_url', (new OpenApiStringType)->format('uri')->nullable(true))
            ->setRequired(['data', 'path', 'per_page']);

        return $object;
    }

    public function toResponse(Type $type): ?Response
    {
        return Response::make(200)
            ->setDescription('Cursor-paginated set of data')
            ->setContent('application/json', Schema::fromType($this->toSchema($type)));
    }

    protected function getItemClass(Type $type): ?string
    {
        if ($type instanceof Generic && isset($type->templateTypes[0])) {
            $inner = $type->templateTypes[0];
            if ($inner instanceof ScrambleObjectType && class_exists($inner->name)) {
                return $inner->name;
            }
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
}
