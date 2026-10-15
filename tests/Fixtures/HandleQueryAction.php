<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class HandleQueryAction
{
    use AsAction;

    public function handle(int $ownerId): array
    {
        return QueryBuilder::for(User::class)
            ->where('owner_id', $ownerId)
            ->allowedFilters(AllowedFilter::partial('name'))
            ->allowedSorts('created_at', 'name')
            ->get()
            ->all();
    }

    public function asController(ActionRequest $request): UserData
    {
        $this->handle(1);

        return UserData::from(['id' => 1]);
    }
}
