<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

class NoRequestRulesAction
{
    use AsAction;

    public function asController(): UserData
    {
        return UserData::from(['id' => 1]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
            'note' => ['nullable', 'string'],
        ];
    }
}
