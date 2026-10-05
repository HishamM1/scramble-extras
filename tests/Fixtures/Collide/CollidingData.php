<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures\Collide;

use Spatie\LaravelData\Data;

class CollidingData extends Data
{
    public function __construct(
        public A\AllowedActionData $first,
        public B\AllowedActionData $second,
    ) {}
}
