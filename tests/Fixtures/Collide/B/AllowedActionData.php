<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures\Collide\B;

use Spatie\LaravelData\Data;

class AllowedActionData extends Data
{
    public function __construct(public int $b) {}
}
