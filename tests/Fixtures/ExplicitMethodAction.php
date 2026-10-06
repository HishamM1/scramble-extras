<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

class ExplicitMethodAction
{
    use AsAction;

    public function custom(): UserData
    {
        return UserData::from(['id' => 1]);
    }

    public function rules(): array
    {
        return ['explicit' => ['required', 'string']];
    }
}
