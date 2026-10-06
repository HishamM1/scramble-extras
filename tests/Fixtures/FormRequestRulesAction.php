<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

class FormRequestRulesAction
{
    use AsAction;

    public function asController(CustomFormRequest $request): UserData
    {
        return UserData::from(['id' => 1]);
    }

    public function rules(): array
    {
        return ['action_rule' => ['required', 'string']];
    }
}
