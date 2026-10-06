<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class DateRuleQueryData extends Data
{
    public function __construct(
        public ?string $since = null,
        public ?CarbonImmutable $startsAt = null,
    ) {}

    public static function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'startsAt' => ['nullable', 'date'],
        ];
    }
}
