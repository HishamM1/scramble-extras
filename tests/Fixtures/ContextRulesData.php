<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class ContextRulesData extends Data
{
    public function __construct(public string $body) {}

    public static function rules(ValidationContext $context, Request $request): array
    {
        return [
            'body' => ['required', 'string', 'max:300'],
        ];
    }
}
