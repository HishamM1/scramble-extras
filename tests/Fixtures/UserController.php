<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\DataCollection;
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

    /**
     * Registered against both PUT and PATCH on the same route (mimicking
     * Route::apiResource()'s update action), for the route-methods-expansion
     * regression test.
     */
    public function update(int $id, UserData $data): UserData
    {
        return $data;
    }

    public function index()
    {
        return UserData::collect([], PaginatedDataCollection::class);
    }

    /**
     * Same as index(), but with an explicit, spec-correct two-argument
     * generic docblock (`<TKey, TValue>`, matching spatie/laravel-data's own
     * template declaration) instead of relying purely on flow inference from
     * the method body. Regression coverage for the item type being read off
     * the wrong template index when a docblock like this is present.
     *
     * @return PaginatedDataCollection<int, UserData>
     */
    public function indexWithGenericDocblock(): PaginatedDataCollection
    {
        return UserData::collect([], PaginatedDataCollection::class);
    }

    /**
     * Same regression coverage as indexWithGenericDocblock(), for the plain
     * (non-paginated) DataCollection wrapper.
     *
     * @return DataCollection<int, UserData>
     */
    public function listWithGenericDocblock(): DataCollection
    {
        return UserData::collect([], DataCollection::class);
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

    public function showResponse(Request $request, User $user)
    {
        return UserData::fromModel($user)->toResponse($request);
    }

    public function created(Request $request)
    {
        return UserData::from(['id' => 1])->toResponse($request)->setStatusCode(201);
    }

    public function pagedResponse(Request $request, LengthAwarePaginator $paginator)
    {
        return UserData::collect($paginator, PaginatedDataCollection::class)->toResponse($request);
    }

    public function listResponse(Request $request)
    {
        return UserData::collect([], DataCollection::class)->toResponse($request);
    }

    public function customQuery(): array
    {
        return (new UserListQuery)->get()->all();
    }
}
