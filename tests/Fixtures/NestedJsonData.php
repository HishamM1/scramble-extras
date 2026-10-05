<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Data;

class NestedJsonData extends Data
{
    public function __construct(
        public string $title,
        public array $meta,
    ) {}

    public static function rules(): array
    {
        return [
            'meta.key' => ['required', 'string'],
        ];
    }
}
