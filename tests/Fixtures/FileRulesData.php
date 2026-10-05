<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Spatie\LaravelData\Data;

class FileRulesData extends Data
{
    public function __construct(
        public string $title,
        public array $branding,
        public ?array $attachments,
        /** Avatar. */
        public ?UploadedFile $avatar = null,
    ) {}

    public static function rules(): array
    {
        return [
            'branding.logo' => ['nullable', 'image', 'max:10240'],
            'branding.primary_color' => ['nullable', 'string'],
            'avatar' => ['nullable', File::image()->max(2048)],
            'attachments.*' => ['file', 'mimes:pdf,png', 'max:2048'],
        ];
    }
}
