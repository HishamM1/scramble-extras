<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PawelJadanowski\ScrambleExtras\PaginatedListResponse;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\AddressData;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserListMetaData;
use PawelJadanowski\ScrambleExtras\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\PaginatedDataCollection;

class PaginatedListResponseTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), LaravelDataServiceProvider::class];
    }

    #[Test]
    public function json_is_identical_to_manual_meta_merge(): void
    {
        $request = Request::create('/api/addresses');
        $meta = new UserListMetaData(3, 'hello');

        $makePaginator = fn () => new LengthAwarePaginator(
            [['street' => 'A', 'city' => 'B'], ['street' => 'C', 'city' => 'D']],
            25,
            2,
            2,
            ['path' => 'http://localhost/api/addresses'],
        );

        $manual = AddressData::collect($makePaginator(), PaginatedDataCollection::class)->toResponse($request);
        $payload = $manual->getData(true);
        $payload['meta'] = [...$payload['meta'], ...$meta->toArray()];
        $expected = $manual->setData($payload);

        $actual = PaginatedListResponse::make(
            AddressData::collect($makePaginator(), PaginatedDataCollection::class),
            $request,
            $meta,
        );

        $this->assertSame($expected->getContent(), $actual->getContent());

        $decoded = $actual->getData(true);
        $this->assertSame(['data', 'links', 'meta'], array_keys($decoded));
        $this->assertSame(3, $decoded['meta']['active_count']);
        $this->assertSame(25, $decoded['meta']['total']);
        $this->assertArrayHasKey('first_page_url', $decoded['meta']);
    }
}
