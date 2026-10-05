<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Data;

class SortedData extends Data
{
    public function __construct(public ?string $sort = null) {}
}
