<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserListQuery extends QueryBuilder
{
    public function __construct()
    {
        parent::__construct(User::query());

        $this
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', fn ($query, $value) => $query)->delimiter(''),
            )
            ->allowedSorts(
                'name',
                AllowedSort::callback('age', fn ($query, bool $descending) => $query),
            )
            ->defaultSort('name');
    }
}
