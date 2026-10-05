<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class MultipartLinesData extends Data
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, UploadedFile>|null  $extras
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public UploadedFile $proof,
        public array $files,
        #[DataCollectionOf(AddressData::class)]
        public array $lines,
        public ?array $extras = null,
        public array $tags = [],
    ) {}
}
