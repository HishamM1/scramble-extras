<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

class AnyOfClasses
{
    public static function dataClasses(): array
    {
        return [AddressData::class, UserData::class, AddressData::class];
    }

    public static function collection()
    {
        return collect([AddressData::class]);
    }
}
