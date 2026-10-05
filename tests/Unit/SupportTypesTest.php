<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use PawelJadanowski\ScrambleExtras\ArrayEnumOperationExtension;
use PawelJadanowski\ScrambleExtras\CachedArrayType;
use PawelJadanowski\ScrambleExtras\CachedScalarType;
use PawelJadanowski\ScrambleExtras\CachedSchemaType;
use PawelJadanowski\ScrambleExtras\ConstrainedNumberType;
use PawelJadanowski\ScrambleExtras\PatternedStringType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SupportTypesTest extends TestCase
{
    #[Test]
    public function constrained_number_type_serializes_exclusive_bounds(): void
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

    #[Test]
    public function constrained_number_type_omits_null_bounds(): void
    {
        $array = (new ConstrainedNumberType('number'))->toArray();

        $this->assertArrayNotHasKey('exclusiveMinimum', $array);
        $this->assertArrayNotHasKey('multipleOf', $array);
    }

    #[Test]
    public function patterned_string_type_renders_pattern(): void
    {
        $array = (new PatternedStringType)->setPattern('^[a-z]+$')->toArray();

        $this->assertSame('^[a-z]+$', $array['pattern']);
    }

    #[Test]
    public function patterned_string_type_without_pattern_has_no_key(): void
    {
        $array = (new PatternedStringType)->toArray();

        $this->assertArrayNotHasKey('pattern', $array);
    }

    #[Test]
    public function cached_schema_type_returns_cached_array(): void
    {
        $cached = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];

        $type = new CachedSchemaType($cached);

        $this->assertSame($cached, $type->toArray());
        $this->assertSame(['id'], $type->required);
    }

    #[Test]
    public function cached_schema_type_set_description_propagates_to_array(): void
    {
        $type = new CachedSchemaType(['type' => 'object']);
        $type->setDescription('Hello');

        $this->assertSame('Hello', $type->toArray()['description']);
    }

    #[Test]
    public function cached_schema_type_nullable_adds_null_to_type(): void
    {
        $type = new CachedSchemaType(['type' => 'object']);
        $type->nullable(true);

        $this->assertSame(['object', 'null'], $type->toArray()['type']);
    }

    #[Test]
    public function cached_schema_type_round_trips_empty_schemas_as_objects(): void
    {
        $type = new CachedSchemaType([
            'type' => 'object',
            'properties' => ['any' => new \stdClass, 'list' => ['type' => 'array', 'items' => new \stdClass]],
        ]);

        $array = $type->toArray();

        $this->assertEquals((object) [], $array['properties']['any']);
        $this->assertInstanceOf(\stdClass::class, $array['properties']['any']);
        $this->assertInstanceOf(\stdClass::class, $array['properties']['list']['items']);
    }

    #[Test]
    public function cached_schema_type_reflects_property_and_required_mutations(): void
    {
        $type = new CachedSchemaType(['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a']]);

        $type->properties['a']->setDescription('Alpha');
        $type->addProperty('b', new StringType);
        $type->addRequired(['b']);

        $array = $type->toArray();

        $this->assertSame('Alpha', $array['properties']['a']['description']);
        $this->assertSame(['type' => 'string'], $array['properties']['b']);
        $this->assertSame(['a', 'b'], $array['required']);

        $type->setRequired([]);

        $this->assertArrayNotHasKey('required', $type->toArray());
    }

    #[Test]
    public function cached_leaf_nullable_adds_and_removes_null_in_enum_and_type(): void
    {
        $plain = new CachedScalarType(['type' => 'string', 'enum' => ['a', 'b']]);
        $plain->nullable(true);
        $this->assertSame(['type' => ['string', 'null'], 'enum' => ['a', 'b', null]], $plain->toArray());

        $nullable = new CachedScalarType(['type' => ['string', 'null'], 'enum' => ['a', null]]);
        $nullable->nullable(false);
        $this->assertSame(['type' => 'string', 'enum' => ['a']], $nullable->toArray());
    }

    #[Test]
    public function cached_leaf_nullable_on_any_of_adds_and_removes_the_null_member(): void
    {
        $ref = ['$ref' => '#/components/schemas/Foo'];

        $nullable = new CachedScalarType(['anyOf' => [$ref, ['type' => 'null']]]);
        $nullable->nullable(false);
        $this->assertSame($ref, $nullable->toArray());

        $plain = new CachedScalarType($ref);
        $plain->nullable(true);
        $this->assertSame(['anyOf' => [$ref, ['type' => 'null']]], $plain->toArray());

        $multi = new CachedScalarType(['anyOf' => [['type' => 'string'], ['type' => 'integer', 'minimum' => 1], ['type' => 'null']]]);
        $multi->nullable(false);
        $this->assertSame(['anyOf' => [['type' => 'string'], ['type' => 'integer', 'minimum' => 1]]], $multi->toArray());
    }

    #[Test]
    public function cached_array_type_carries_constraints_and_items_mutations(): void
    {
        $type = new CachedArrayType(['type' => 'array']);
        $type->setMin(1)->setMax(5)->setUniqueItems(true)->setItems(new StringType);

        $this->assertSame(
            ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 5, 'uniqueItems' => true],
            $type->toArray(),
        );

        $type->setPrefixItems([new StringType]);
        $this->assertSame([['type' => 'string']], $type->toArray()['prefixItems']);

        $cached = new CachedArrayType(['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 2]);
        $cached->setMin(null);
        $this->assertArrayNotHasKey('minItems', $cached->toArray());
    }

    #[Test]
    public function array_enum_moves_to_items_in_parameters_and_body_properties(): void
    {
        $makeArray = fn () => (new ArrayType)->setItems(new StringType)->enum(['a', 'b']);

        $operation = (new Operation('get'))->addParameters([
            (new Parameter('status[]', 'query'))->setSchema(Schema::fromType($makeArray())),
        ]);
        $body = (new ObjectType)->addProperty('tags', $makeArray());
        $operation->addRequestBodyObject(RequestBodyObject::make()->setContent('application/json', Schema::fromType($body)));

        $extension = (new \ReflectionClass(ArrayEnumOperationExtension::class))->newInstanceWithoutConstructor();
        $extension->handle($operation, (new \ReflectionClass(\Dedoc\Scramble\Support\RouteInfo::class))->newInstanceWithoutConstructor());

        $param = $operation->parameters[0]->schema->type->toArray();
        $this->assertArrayNotHasKey('enum', $param);
        $this->assertSame(['a', 'b'], $param['items']['enum']);

        $tags = $body->toArray()['properties']['tags'];
        $this->assertArrayNotHasKey('enum', $tags);
        $this->assertSame(['a', 'b'], $tags['items']['enum']);
    }
}
