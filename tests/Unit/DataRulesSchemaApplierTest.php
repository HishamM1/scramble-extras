<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use Illuminate\Validation\Rules\File;
use PawelJadanowski\ScrambleExtras\DataRulesSchemaApplier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DataRulesSchemaApplierTest extends TestCase
{
    #[Test]
    public function file_size_is_null_when_the_rule_lacks_the_property(): void
    {
        $method = new ReflectionMethod(DataRulesSchemaApplier::class, 'fileSize');
        $applier = (new \ReflectionClass(DataRulesSchemaApplier::class))->newInstanceWithoutConstructor();

        $this->assertNull($method->invoke($applier, File::default(), 'renamedInFutureLaravel'));
    }
}
