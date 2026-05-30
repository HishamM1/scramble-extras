<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use PawelJadanowski\ScrambleExtras\AttributeAnalysis;
use PawelJadanowski\ScrambleExtras\ConstrainedNumberType;
use PawelJadanowski\ScrambleExtras\PatternedStringType;
use PawelJadanowski\ScrambleExtras\SchemaAttributeApplier;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\ValidationFixtures;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SchemaAttributeApplierTest extends TestCase
{
    private function analyze(string $property, OpenApiType $base): AttributeAnalysis
    {
        $reflection = new ReflectionProperty(ValidationFixtures::class, $property);

        return (new SchemaAttributeApplier)->analyze($reflection, $base);
    }

    public function test_email_sets_format(): void
    {
        $analysis = $this->analyze('email', new StringType);

        $this->assertSame('email', $analysis->type->toArray()['format'] ?? null);
    }

    public function test_url_sets_uri_format(): void
    {
        $analysis = $this->analyze('url', new StringType);

        $this->assertSame('uri', $analysis->type->toArray()['format'] ?? null);
    }

    public function test_min_on_string_sets_min_length(): void
    {
        $analysis = $this->analyze('minString', new StringType);

        $this->assertSame(3, $analysis->type->toArray()['minLength'] ?? null);
    }

    public function test_max_on_string_sets_max_length(): void
    {
        $analysis = $this->analyze('maxString', new StringType);

        $this->assertSame(10, $analysis->type->toArray()['maxLength'] ?? null);
    }

    public function test_between_on_string_sets_both_bounds(): void
    {
        $array = $this->analyze('betweenString', new StringType)->type->toArray();

        $this->assertSame(2, $array['minLength'] ?? null);
        $this->assertSame(8, $array['maxLength'] ?? null);
    }

    public function test_min_on_integer_sets_minimum(): void
    {
        $analysis = $this->analyze('minInt', new IntegerType);

        $this->assertSame(5, $analysis->type->toArray()['minimum'] ?? null);
    }

    public function test_greater_than_emits_exclusive_minimum_and_keeps_integer(): void
    {
        $analysis = $this->analyze('positiveInt', new IntegerType);
        $array = $analysis->type->toArray();

        $this->assertInstanceOf(ConstrainedNumberType::class, $analysis->type);
        $this->assertSame('integer', $array['type'] ?? null);
        $this->assertSame(0, $array['exclusiveMinimum'] ?? null);
    }

    public function test_less_than_emits_exclusive_maximum(): void
    {
        $array = $this->analyze('belowHundred', new IntegerType)->type->toArray();

        $this->assertSame(100, $array['exclusiveMaximum'] ?? null);
    }

    public function test_multiple_of(): void
    {
        $array = $this->analyze('multipleOfFive', new IntegerType)->type->toArray();

        $this->assertSame(5, $array['multipleOf'] ?? null);
    }

    public function test_regex_sets_pattern_without_delimiters(): void
    {
        $analysis = $this->analyze('regexed', new StringType);

        $this->assertInstanceOf(PatternedStringType::class, $analysis->type);
        $this->assertSame('^[a-z]+$', $analysis->type->toArray()['pattern'] ?? null);
    }

    public function test_alpha_dash_sets_pattern(): void
    {
        $array = $this->analyze('slug', new StringType)->type->toArray();

        $this->assertSame('^[A-Za-z0-9_-]+$', $array['pattern'] ?? null);
    }

    public function test_starts_with_builds_alternation_pattern(): void
    {
        $array = $this->analyze('prefixed', new StringType)->type->toArray();

        $this->assertSame('^(foo|bar)', $array['pattern'] ?? null);
    }

    public function test_in_emits_enum(): void
    {
        $array = $this->analyze('inList', new StringType)->type->toArray();

        $this->assertSame(['a', 'b', 'c'], $array['enum'] ?? null);
    }

    public function test_enum_class_emits_backed_values(): void
    {
        $array = $this->analyze('enumBacked', new StringType)->type->toArray();

        $this->assertSame(['active', 'inactive', 'pending'], $array['enum'] ?? null);
    }

    public function test_digits_sets_length_and_pattern(): void
    {
        $array = $this->analyze('pin', new StringType)->type->toArray();

        $this->assertSame(4, $array['minLength'] ?? null);
        $this->assertSame(4, $array['maxLength'] ?? null);
        $this->assertSame('^\d{4}$', $array['pattern'] ?? null);
    }

    public function test_image_sets_content_media_type(): void
    {
        $array = $this->analyze('avatar', new StringType)->type->toArray();

        $this->assertSame('image/*', $array['contentMediaType'] ?? null);
        $this->assertSame('binary', $array['contentEncoding'] ?? null);
    }

    public function test_file_sets_octet_stream(): void
    {
        $array = $this->analyze('attachment', new StringType)->type->toArray();

        $this->assertSame('application/octet-stream', $array['contentMediaType'] ?? null);
    }

    public function test_required_forces_required(): void
    {
        $this->assertTrue($this->analyze('forcedRequired', new StringType)->forceRequired);
    }

    public function test_sometimes_forces_not_required(): void
    {
        $this->assertFalse($this->analyze('forcedOptional', new StringType)->forceRequired);
    }

    public function test_confirmed_adds_note(): void
    {
        $notes = $this->analyze('passwordConfirmed', new StringType)->notes;

        $this->assertNotEmpty($notes);
        $this->assertStringContainsString('confirmed', strtolower($notes[0]));
    }

    public function test_same_adds_note_referencing_field(): void
    {
        $notes = $this->analyze('sameAsPassword', new StringType)->notes;

        $this->assertStringContainsString('password', implode(' ', $notes));
    }

    public function test_exists_adds_note(): void
    {
        $notes = $this->analyze('existingUser', new IntegerType)->notes;

        $this->assertStringContainsString('users', implode(' ', $notes));
    }

    public function test_required_if_adds_note(): void
    {
        $notes = $this->analyze('conditionallyRequired', new StringType)->notes;

        $this->assertStringContainsString('Required when', implode(' ', $notes));
    }

    public function test_nullable_marks_type_nullable(): void
    {
        $analysis = $this->analyze('explicitlyNullable', new StringType);
        $array = $analysis->type->toArray();
        $type = $array['type'] ?? null;

        $this->assertTrue(
            $type === 'null'
            || (is_array($type) && in_array('null', $type, true))
            || ($array['nullable'] ?? false) === true,
            'Expected the type to be marked nullable.',
        );
    }

    public function test_plain_property_has_no_notes_or_overrides(): void
    {
        $analysis = $this->analyze('plain', new StringType);

        $this->assertSame([], $analysis->notes);
        $this->assertNull($analysis->forceRequired);
    }

    public function test_example_and_default_are_read_from_phpdoc(): void
    {
        $reflection = new ReflectionProperty(\PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserData::class, 'name');
        $type = new StringType;

        (new SchemaAttributeApplier)->applyExamplesFromPhpDoc($reflection, $type);

        $this->assertSame('Ada Lovelace', $type->toArray()['example'] ?? null);
    }
}
