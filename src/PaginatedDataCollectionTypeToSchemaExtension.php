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
            ->addProperty('links', $this->linksObject())
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

    protected function linksObject(): OpenApiObjectType
    {
        $object = new OpenApiObjectType;

        $nullableUrl = (new OpenApiStringType)->format('uri')->nullable(true);
        $object
            ->addProperty('first', $nullableUrl)
            ->addProperty('last', $nullableUrl)
            ->addProperty('prev', $nullableUrl)
            ->addProperty('next', $nullableUrl);

        return $object;
    }

    protected function metaObject(): OpenApiObjectType
    {
        $object = new OpenApiObjectType;

        $object
            ->addProperty('current_page', new OpenApiIntegerType)
            ->addProperty('from', (new OpenApiIntegerType)->nullable(true))
            ->addProperty('last_page', new OpenApiIntegerType)
            ->addProperty('path', (new OpenApiStringType)->format('uri'))
            ->addProperty('per_page', new OpenApiIntegerType)
            ->addProperty('to', (new OpenApiIntegerType)->nullable(true))
            ->addProperty('total', new OpenApiIntegerType);

        $linkObject = new OpenApiObjectType;
        $linkObject
            ->addProperty('url', (new OpenApiStringType)->format('uri')->nullable(true))
            ->addProperty('label', new OpenApiStringType)
            ->addProperty('active', new OpenApiBooleanType);

        $object->addProperty('links', (new OpenApiArrayType)->setItems($linkObject));

        return $object;
    }
}
