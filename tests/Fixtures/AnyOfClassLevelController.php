<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use PawelJadanowski\ScrambleExtras\ResponseAnyOf;
use Spatie\LaravelData\Data;

#[ResponseAnyOf([UserData::class, AddressData::class])]
class AnyOfClassLevelController
{
    public function index(): Data
    {
        return UserData::from([]);
    }

    public function empty()
    {
        return response()->noContent();
    }
}
