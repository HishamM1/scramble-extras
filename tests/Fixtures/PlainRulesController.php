<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

class PlainRulesController
{
    public function index(): UserData
    {
        return UserData::from(['id' => 1]);
    }

    protected function rules(): array
    {
        return ['plain' => ['required', 'string']];
    }
}
