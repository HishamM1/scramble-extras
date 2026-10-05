<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures\Collide\A;

use Spatie\LaravelData\Data;

class AllowedActionData extends Data
{
    public function __construct(public string $a) {}
}
