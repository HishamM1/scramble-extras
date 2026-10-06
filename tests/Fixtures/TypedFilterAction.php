<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TypedFilterAction
{
    use AsAction;

    public function asController(ActionRequest $request): UserData
    {
        QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::exact('student_id'),
                AllowedFilter::exact('kind'),
                AllowedFilter::exact('day'),
                AllowedFilter::exact('title'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('priority'),
            )
            ->allowedSorts('name', '-name')
            ->defaultSort('-name')
            ->jsonPaginate();

        return UserData::from(['id' => 1]);
    }

    public function rules(): array
    {
        return [
            'filter.student_id' => ['nullable', 'integer'],
            'filter.kind' => ['nullable', Rule::in(['a', 'b'])],
            'filter.day' => ['nullable', 'date_format:Y-m-d'],
            'filter.title' => ['nullable', 'string'],
            'filter.status' => ['nullable', 'string'],
            'filter.priority' => ['nullable', 'integer'],
            'page.number' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(['name', '-name'])],
        ];
    }
}
