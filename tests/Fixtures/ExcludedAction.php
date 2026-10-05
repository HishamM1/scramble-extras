<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Lorisleiva\Actions\Concerns\AsAction;

class ExcludedAction
{
    use AsAction;

    #[ExcludeRouteFromDocs]
    public function asController(): UserData
    {
        return UserData::from(['id' => 1]);
    }
}
