<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use PawelJadanowski\ScrambleExtras\LaravelDataReturnTypeExtension;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class LaravelDataReturnTypeExtensionTest extends TestCase
{
    private LaravelDataReturnTypeExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new LaravelDataReturnTypeExtension;
    }

    #[Test]
    public function should_handle_only_data_classes(): void
    {
        $this->assertTrue($this->extension->shouldHandle(UserData::class));
        $this->assertFalse($this->extension->shouldHandle(\stdClass::class));
        $this->assertFalse($this->extension->shouldHandle('Not\\A\\Class'));
    }

    #[Test]
    public function from_returns_object_type_of_data_class(): void
    {
        $type = LaravelDataReturnTypeExtension::resolveReturnType(UserData::class, 'from', null);

        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertSame(UserData::class, $type->name);
    }

    #[Test]
    public function collect_without_into_returns_array_of_data(): void
    {
        $type = LaravelDataReturnTypeExtension::resolveReturnType(UserData::class, 'collect', null);

        $this->assertInstanceOf(ArrayType::class, $type);
        $this->assertInstanceOf(ObjectType::class, $type->value);
        $this->assertSame(UserData::class, $type->value->name);
    }

    #[Test]
    public function collect_into_paginated_returns_generic_wrapper(): void
    {
        $into = new LiteralStringType(PaginatedDataCollection::class);
        $type = LaravelDataReturnTypeExtension::resolveReturnType(UserData::class, 'collect', $into);

        $this->assertInstanceOf(Generic::class, $type);
        $this->assertSame(PaginatedDataCollection::class, $type->name);
        $this->assertInstanceOf(ObjectType::class, $type->templateTypes[0]);
        $this->assertSame(UserData::class, $type->templateTypes[0]->name);
    }

    #[Test]
    public function collect_into_cursor_paginated_returns_generic_wrapper(): void
    {
        $into = new LiteralStringType(CursorPaginatedDataCollection::class);
        $type = LaravelDataReturnTypeExtension::resolveReturnType(UserData::class, 'collect', $into);

        $this->assertInstanceOf(Generic::class, $type);
        $this->assertSame(CursorPaginatedDataCollection::class, $type->name);
    }

    #[Test]
    public function collect_into_data_collection_returns_generic_wrapper(): void
    {
        $into = new LiteralStringType(DataCollection::class);
        $type = LaravelDataReturnTypeExtension::resolveReturnType(UserData::class, 'collect', $into);

        $this->assertInstanceOf(Generic::class, $type);
        $this->assertSame(DataCollection::class, $type->name);
    }

    #[Test]
    public function unknown_method_returns_null(): void
    {
        $this->assertNull(
            LaravelDataReturnTypeExtension::resolveReturnType(UserData::class, 'somethingElse', null),
        );
    }
}
