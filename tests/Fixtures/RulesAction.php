<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RulesAction
{
    use AsAction;

    public function asController(ActionRequest $request): UserData
    {
        return UserData::from(['id' => 1]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
            'note' => ['nullable', 'string'],
            'filter.status' => ['nullable', 'array'],
            'filter.status.*' => ['string', Rule::in(['active', 'archived'])],
        ];
    }
}
