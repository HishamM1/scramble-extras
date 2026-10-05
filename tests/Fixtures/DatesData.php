<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatesData extends Data
{
    public function __construct(
        #[DateFormat('Y-m-d')]
        public string $attributeDay,
        #[Date]
        public string $plainDate,
        public ?string $ruleDay,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public CarbonImmutable $namedTransformerDay,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d')]
        public ?CarbonImmutable $positionalTransformerDay,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public CarbonImmutable $castDay,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public CarbonImmutable $moment,
        public CarbonImmutable $createdAt,
    ) {}

    public static function rules(): array
    {
        return [
            'ruleDay' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
