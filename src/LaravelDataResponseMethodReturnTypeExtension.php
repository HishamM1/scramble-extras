<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;
use ReflectionClass;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class LaravelDataResponseMethodReturnTypeExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(BaseData::class)
            || $type->isInstanceOf(DataCollection::class)
            || $type->isInstanceOf(PaginatedDataCollection::class)
            || $type->isInstanceOf(CursorPaginatedDataCollection::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->name !== 'toResponse') {
            return null;
        }

        $instance = $event->getInstance();

        return new Generic(JsonResponse::class, [
            (new ReflectionClass($instance->name))->isInstantiable()
                ? $instance
                : new ArrayType(new MixedType, new StringType),
            new LiteralIntegerType(200),
            new KeyedArrayType,
        ]);
    }
}
