<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController
{
    public function show(int $id): UserData
    {
        return UserData::from(['id' => $id]);
    }

    public function store(UserData $data): UserData
    {
        return $data;
    }

    public function index()
    {
        return UserData::collect([], PaginatedDataCollection::class);
    }

    public function search(): array
    {
        QueryBuilder::for(User::class)
            ->allowedFilters([
                'name',
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['name', 'created_at'])
            ->allowedIncludes('posts')
            ->defaultSort('-created_at')
            ->jsonPaginate();

        return [];
    }
}
