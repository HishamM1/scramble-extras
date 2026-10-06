<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class CustomFormRequest extends FormRequest
{
    public function rules(): array
    {
        return ['custom' => ['required', 'string']];
    }
}
