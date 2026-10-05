<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FilterArrayAction
{
    use AsAction;

    public function asController(ActionRequest $request): UserData
    {
        $balanceSort = AllowedSort::callback('balance', fn ($query, bool $descending) => $query);

        $scope = User::where('x', 1);
        $search = AllowedFilter::partial('search');

        QueryBuilder::for(User::class)
            ->allowedFilters(AllowedFilter::callback('status', fn ($query, $value) => $query), AllowedFilter::callback('active_only', fn ($query, $value) => $query), $search)
            ->allowedSorts('name', $balanceSort, $scope);

        return UserData::from(['id' => 1]);
    }

    public function rules(): array
    {
        return [
            'filter.status' => ['nullable', 'array'],
            'filter.active_only' => ['nullable', 'boolean'],
            'filter.status.*' => ['string', Rule::in(['active', 'inactive'])],
        ];
    }
}
