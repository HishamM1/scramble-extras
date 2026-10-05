<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use PawelJadanowski\ScrambleExtras\Tests\Fixtures\Collide\CollidingData;

class CollidingController
{
    public function show(): CollidingData
    {
        return CollidingData::from([]);
    }

    public function store(CollidingData $data): CollidingData
    {
        return $data;
    }
}
