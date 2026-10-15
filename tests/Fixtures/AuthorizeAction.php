<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthorizeAction
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function asController(int $id): UserData
    {
        return UserData::from(['id' => $id]);
    }
}
