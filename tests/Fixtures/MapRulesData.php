<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Data;

class MapRulesData extends Data
{
    /**
     * @param  array<string, string|null>|null  $mapping
     * @param  array<int, array{values: array<string, string|null>}>  $rows
     */
    public function __construct(
        public ?array $mapping,
        public array $rows,
    ) {}

    public static function rules(): array
    {
        return [
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:5'],
            'rows' => ['required', 'array'],
            'rows.*.values' => ['array'],
            'rows.*.values.*' => ['nullable', 'string', 'max:7'],
        ];
    }
}
