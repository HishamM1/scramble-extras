<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public string $street,
        public string $city,
        public ?string $postalCode = null,
    ) {}
}
