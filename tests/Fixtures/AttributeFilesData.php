<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Attributes\Validation as V;
use Spatie\LaravelData\Data;

class AttributeFilesData extends Data
{
    public function __construct(
        public string $title,
        #[V\Image]
        public string $photo,
        #[V\File]
        public string $document,
        public array $lines,
    ) {}

    public static function rules(): array
    {
        return [
            'lines.*.name' => ['required', 'string'],
        ];
    }
}
