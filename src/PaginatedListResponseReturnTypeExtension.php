<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\PaginatedDataCollection;

class PaginatedListResponseReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === PaginatedListResponse::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if ($event->name !== 'make') {
            return null;
        }

        $collection = $event->getArg('collection', 0);
        $meta = $event->getArg('meta', 2);

        if (! $collection instanceof Generic
            || ! is_a($collection->name, PaginatedDataCollection::class, true)
            || $collection->templateTypes === []) {
            return null;
        }

        $item = end($collection->templateTypes);

        if (! $meta instanceof ObjectType || ! $item instanceof ObjectType) {
            return new Generic(JsonResponse::class, [
                $collection,
                new LiteralIntegerType(200),
                new KeyedArrayType,
            ]);
        }

        return new Generic(JsonResponse::class, [
            new Generic(PaginatedListResponse::class, [$item, new ObjectType($meta->name)]),
            new LiteralIntegerType(200),
            new KeyedArrayType,
        ]);
    }
}
