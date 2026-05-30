<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use PawelJadanowski\ScrambleExtras\CachedSchemaType;
use PawelJadanowski\ScrambleExtras\ConstrainedNumberType;
use PawelJadanowski\ScrambleExtras\DataClassNameRegistry;
use PawelJadanowski\ScrambleExtras\PatternedStringType;
use PHPUnit\Framework\TestCase;

class SupportTypesTest extends TestCase
{
    public function test_constrained_number_type_serializes_exclusive_bounds(): void
    {
        $type = (new ConstrainedNumberType('integer'))
            ->setExclusiveMin(0)
            ->setExclusiveMax(100)
            ->setMultipleOf(5);

        $array = $type->toArray();

        $this->assertSame('integer', $array['type']);
        $this->assertSame(0, $array['exclusiveMinimum']);
        $this->assertSame(100, $array['exclusiveMaximum']);
        $this->assertSame(5, $array['multipleOf']);
    }

    public function test_constrained_number_type_omits_null_bounds(): void
    {
        $array = (new ConstrainedNumberType('number'))->toArray();

        $this->assertArrayNotHasKey('exclusiveMinimum', $array);
        $this->assertArrayNotHasKey('multipleOf', $array);
    }

    public function test_patterned_string_type_renders_pattern(): void
    {
        $array = (new PatternedStringType)->setPattern('^[a-z]+$')->toArray();

        $this->assertSame('^[a-z]+$', $array['pattern']);
    }

    public function test_patterned_string_type_without_pattern_has_no_key(): void
    {
        $array = (new PatternedStringType)->toArray();

        $this->assertArrayNotHasKey('pattern', $array);
    }

    public function test_cached_schema_type_returns_cached_array(): void
    {
        $cached = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];

        $type = new CachedSchemaType($cached);

        $this->assertSame($cached, $type->toArray());
        $this->assertSame(['id'], $type->required);
    }

    public function test_cached_schema_type_set_description_propagates_to_array(): void
    {
        $type = new CachedSchemaType(['type' => 'object']);
        $type->setDescription('Hello');

        $this->assertSame('Hello', $type->toArray()['description']);
    }

    public function test_cached_schema_type_nullable_adds_null_to_type(): void
    {
        $type = new CachedSchemaType(['type' => 'object']);
        $type->nullable(true);

        $this->assertSame(['object', 'null'], $type->toArray()['type']);
    }

    public function test_data_class_name_registry_resolves_short_name(): void
    {
        DataClassNameRegistry::reset();
        DataClassNameRegistry::register(StringType::class);

        $this->assertSame(StringType::class, DataClassNameRegistry::resolve('StringType'));
        $this->assertNull(DataClassNameRegistry::resolve('Nonexistent'));

        DataClassNameRegistry::reset();
        $this->assertSame([], DataClassNameRegistry::all());
    }
}
