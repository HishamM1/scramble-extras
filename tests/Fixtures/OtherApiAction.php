<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Dedoc\Scramble\Attributes\Api;
use Lorisleiva\Actions\Concerns\AsAction;

class OtherApiAction
{
    use AsAction;

    #[Api('other')]
    public function asController(): UserData
    {
        return UserData::from(['id' => 1]);
    }
}
