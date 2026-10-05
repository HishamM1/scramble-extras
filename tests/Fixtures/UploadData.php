<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UploadData extends Data
{
    /**
     * @param  string  title
     * @param  array<int, UploadedFile>  $attachments
     */
    public function __construct(
        public string $title,
        public UploadedFile $proof,
        public ?UploadedFile $receipt,
        public array $attachments,
    ) {}

    public static function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'min:5', 'max:10240'],
            'attachments.*' => ['file', 'max:2048'],
        ];
    }
}
