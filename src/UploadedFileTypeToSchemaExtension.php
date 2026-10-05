<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\UploadedFile;

class UploadedFileTypeToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && class_exists($type->name)
            && is_a($type->name, UploadedFile::class, true);
    }

    public function toSchema(Type $type, ?OpenApiType $previousExtensionResult = null): OpenApiType
    {
        return (new OpenApiStringType)
            ->contentMediaType('application/octet-stream')
            ->format('binary');
    }
}
