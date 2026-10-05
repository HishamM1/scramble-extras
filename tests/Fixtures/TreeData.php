<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class TreeData extends Data
{
    public function __construct(
        public string $name,
        #[DataCollectionOf(TreeData::class)]
        public array $children,
        public ?TreeData $parent = null,
    ) {}
}
