<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RequestAction
{
    use AsAction;

    public function asController(ActionRequest $request): UserData
    {
        return UserData::from(['id' => 1]);
    }
}
