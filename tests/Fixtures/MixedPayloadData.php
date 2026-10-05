<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Data;

class MixedPayloadData extends Data
{
    /**
     * @param  mixed[]  $items
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        /** @var mixed */
        public mixed $payload,
        public array $items,
        public array $meta,
        public string $name,
    ) {}
}
