<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType as ScrambleObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\LaravelData\PaginatedDataCollection;

class PaginatedListResponseTypeToSchemaExtension extends PaginatedDataCollectionTypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->name === PaginatedListResponse::class
            && count($type->templateTypes) === 2
            && $type->templateTypes[0] instanceof ScrambleObjectType
            && $type->templateTypes[1] instanceof ScrambleObjectType;
    }

    public function toSchema(Type $type): OpenApiType
    {
        /** @var Generic $type */
        [$item, $meta] = $type->templateTypes;

        $schema = parent::toSchema(new Generic(PaginatedDataCollection::class, [$item]));

        $metaSchema = (new LaravelDataReflector($this->openApiTransformer, $this->components))
            ->buildSchema($meta->name)
            ->toArray();
        $paginatorMeta = $schema->getProperty('meta')->toArray();

        $merged = $paginatorMeta;
        $merged['properties'] = [...$paginatorMeta['properties'], ...($metaSchema['properties'] ?? [])];
        $merged['required'] = array_values(array_unique([
            ...($paginatorMeta['required'] ?? []),
            ...($metaSchema['required'] ?? []),
        ]));

        $schema->addProperty('meta', new CachedSchemaType($merged));

        return $schema;
    }
}
