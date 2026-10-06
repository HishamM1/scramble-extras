<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => StatusEnum::class,
        'priority' => PriorityEnum::class,
    ];
}
