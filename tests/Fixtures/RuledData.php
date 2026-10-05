<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class RuledData extends Data
{
    public function __construct(
        public ?string $name,
        public ?string $contact,
        public ?int $age,
        public string $kind,
        public ?string $status,
        public ?array $tags,
        public ?array $family,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'contact' => 'nullable|email',
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'kind' => ['required', 'in:a,b'],
            'status' => ['required', Rule::enum(StatusEnum::class)],
            'tags' => ['array', 'max:3'],
            'tags.*' => ['string', 'max:10'],
            'family' => ['required', 'array'],
            'family.phone' => ['required', 'string'],
            'family.parents' => ['array'],
            'family.parents.*.name' => ['required', 'string'],
        ];
    }
}
