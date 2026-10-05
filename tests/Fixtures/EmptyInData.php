<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class EmptyInData extends Data
{
    public function __construct(
        public string $tenant,
        public string $kind,
        public string $status,
        public string $mixed,
    ) {}

    public static function rules(): array
    {
        return [
            'tenant' => ['required', Rule::in([request()->route('tenant')?->name])],
            'kind' => ['required', 'in:a,b'],
            'status' => ['required', Rule::enum(StatusEnum::class), Rule::in([])],
            'mixed' => ['required', 'in:"","a"'],
        ];
    }
}
