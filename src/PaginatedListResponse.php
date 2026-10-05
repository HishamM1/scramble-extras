<?php

namespace PawelJadanowski\ScrambleExtras;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\PaginatedDataCollection;

class PaginatedListResponse
{
    public static function make(PaginatedDataCollection $collection, Request $request, BaseData $meta): JsonResponse
    {
        $response = $collection->toResponse($request);

        $payload = $response->getData(true);
        $payload['meta'] = [...$payload['meta'], ...$meta->toArray()];

        return $response->setData($payload);
    }
}
