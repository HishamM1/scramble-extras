<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Fixtures;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserData extends Data
{
    /**
     * The user's display name.
     *
     * @example Ada Lovelace
     */
    public string $name;

    /** @var AddressData[] */
    #[DataCollectionOf(AddressData::class)]
    public array $addresses;

    public function __construct(
        public int $id,
        #[Email]
        public string $email,
        public StatusEnum $status,
        #[Min(8)]
        public string $password,
        public ?string $bio = null,
        public string|Optional $nickname = new Optional,
        #[Computed]
        public string $fullName = '',
        #[Hidden]
        public string $internalToken = '',
    ) {}
}
