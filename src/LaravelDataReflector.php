<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\NullType as OpenApiNullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType as OpenApiNumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType as OpenApiUnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ObjectType as ScrambleObjectType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Lazy;

/**
 * Inspects a spatie/laravel-data class and produces an OpenAPI ObjectType.
 *
 * Why: replicates the Pro extension's behavior — without depending on the paid
 * package — for both output (response) and input (request body) schemas.
 */
class LaravelDataReflector
{
    public function __construct(
        protected TypeTransformer $openApiTransformer,
        protected Components $components,
    ) {}

    public function buildSchema(string $dataClass, bool $input = false): OpenApiObjectType
    {
        $cache = $this->cache();

        if ($cache !== null) {
            $key = $dataClass.($input ? '.in' : '.out');
            $entry = $cache->get($key);
            $mtime = $this->mtimeOf($dataClass);

            if ($entry !== null && ($entry['mtime'] ?? 0) >= $mtime) {
                $this->materializeDeps($entry['deps'] ?? []);

                return new CachedSchemaType($this->decodeRefs($entry['array']));
            }

            $schema = $this->doBuildSchema($dataClass, $input);
            $array = $this->encodeRefs($schema->toArray(), $this->refNames());

            $cache->put($key, [
                'mtime' => $mtime,
                'deps' => $this->extractDeps($array),
                'array' => $array,
            ]);

            return $schema;
        }

        return $this->doBuildSchema($dataClass, $input);
    }

    protected function doBuildSchema(string $dataClass, bool $input): OpenApiObjectType
    {
        $reflection = new ReflectionClass($dataClass);
        $object = new OpenApiObjectType;
        $required = [];

        $classMapper = $this->detectClassNameMapper($reflection);

        $nameResolver = $reflection->getFileName()
            ? FileNameResolver::createForFile($reflection->getFileName())
            : null;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $info = $this->describeProperty($property, $input, $classMapper, $nameResolver);
            if ($info === null) {
                continue;
            }

            $object->addProperty($info['name'], $info['type']);

            if ($info['required']) {
                $required[] = $info['name'];
            }
        }

        $object->setRequired($required);

        if ($input) {
            (new DataRulesSchemaApplier)->apply($object, $dataClass);
        }

        return $object;
    }

    protected function cache(): ?SchemaCache
    {
        if (! function_exists('app')) {
            return null;
        }

        if (! app()->bound(SchemaCache::class)) {
            return null;
        }

        $config = app()->bound('config') ? app('config') : null;
        if ($config !== null && ! $config->get('scramble-extras.cache.enabled', true)) {
            return null;
        }

        return app(SchemaCache::class);
    }

    protected function mtimeOf(string $class): int
    {
        try {
            $file = (new ReflectionClass($class))->getFileName();
        } catch (\ReflectionException) {
            return 0;
        }

        if ($file === false || ! is_file($file)) {
            return 0;
        }

        return (int) filemtime($file);
    }

    protected function materializeDeps(array $deps): void
    {
        foreach ($deps as $dep) {
            if (class_exists($dep) || enum_exists($dep)) {
                $this->openApiTransformer->transform(new ScrambleObjectType($dep));
            }
        }
    }

    protected function extractDeps(array $array): array
    {
        $deps = [];
        $prefix = '#/components/fqcn/';

        array_walk_recursive($array, function ($value, $key) use (&$deps, $prefix) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, $prefix)) {
                $deps[] = substr($value, strlen($prefix));
            }
        });

        return array_values(array_unique($deps));
    }

    protected function encodeRefs(array $array, array $names): array
    {
        return $this->mapRefs($array, fn (string $name): ?string => isset($names[$name]) ? '#/components/fqcn/'.$names[$name] : null);
    }

    protected function decodeRefs(array $array): array
    {
        $prefix = '#/components/fqcn/';

        return $this->mapRefs($array, fn (string $key): ?string => '#/components/schemas/'.$this->openApiTransformer->context->references->schemas->uniqueName($key), $prefix);
    }

    protected function mapRefs(array $node, \Closure $map, string $prefix = '#/components/schemas/'): array
    {
        foreach ($node as $k => $v) {
            if ($k === '$ref' && is_string($v) && str_starts_with($v, $prefix)) {
                $node[$k] = $map(substr($v, strlen($prefix))) ?? $v;
            } elseif (is_array($v)) {
                $node[$k] = $this->mapRefs($v, $map, $prefix);
            }
        }

        return $node;
    }

    protected function refNames(): array
    {
        $names = [];

        foreach ($this->openApiTransformer->context->references->schemas->items as $fqcn => $references) {
            $names[$references[0]->shortName ?: $references[0]->fullName] = $fqcn;
        }

        return $names;
    }

    /**
     * @return array{name: string, type: OpenApiType, required: bool}|null
     */
    protected function describeProperty(ReflectionProperty $property, bool $input, ?\Closure $classMapper, ?FileNameResolver $nameResolver): ?array
    {
        $isHidden = $this->hasAttribute($property, Hidden::class);
        $isComputed = $this->hasAttribute($property, Computed::class);

        // Hidden never appears in output. Computed never appears in input.
        if (! $input && $isHidden) {
            return null;
        }
        if ($input && $isComputed) {
            return null;
        }

        $reflectionType = $property->getType();
        $unionTypes = $reflectionType instanceof ReflectionUnionType
            ? $reflectionType->getTypes()
            : ($reflectionType ? [$reflectionType] : []);

        $isOptional = false;
        $isLazy = false;
        $effectiveTypes = [];

        foreach ($unionTypes as $t) {
            if (! $t instanceof ReflectionNamedType) {
                continue;
            }

            $name = $t->getName();
            if (is_subclass_of($name, Optional::class) || $name === Optional::class) {
                $isOptional = true;
                continue;
            }
            if (is_subclass_of($name, Lazy::class) || $name === Lazy::class) {
                $isLazy = true;
                continue;
            }
            $effectiveTypes[] = $t;
        }

        $isNullable = $reflectionType && $reflectionType->allowsNull();

        $collectionOf = $this->extractDataCollectionOf($property);
        if ($collectionOf !== null) {
            $openApiType = (new OpenApiArrayType)->setItems(
                $this->resolveClassType($collectionOf),
            );
        } else {
            $docVarType = $this->extractDocVarType($property);
            $openApiType = $this->resolveType($effectiveTypes, $docVarType, $nameResolver);
        }

        if ($isNullable) {
            $openApiType->nullable(true);
        }

        $applier = new SchemaAttributeApplier;
        $analysis = $applier->analyze($property, $openApiType);
        $openApiType = $analysis->type;
        $applier->applyExamplesFromPhpDoc($property, $openApiType);

        $description = $this->buildDescription(
            base: $this->extractDescription($property),
            notes: $analysis->notes,
        );
        if ($description !== '') {
            $openApiType->setDescription($description);
        }

        $name = $this->resolveMappedName($property, $input, $classMapper);

        if (! $input && $isComputed) {
            // Computed properties are produced server-side on every output, so always required.
            $required = true;
        } elseif ($analysis->forceRequired !== null) {
            $required = $analysis->forceRequired;
        } else {
            $required = ! $isOptional
                && ! $isLazy
                && ! $isNullable
                && ! $this->promotedHasDefault($property);
        }

        return [
            'name' => $name,
            'type' => $openApiType,
            'required' => $required,
        ];
    }

    /**
     * @param  string[]  $notes
     */
    protected function buildDescription(string $base, array $notes): string
    {
        $parts = [];
        if ($base !== '') {
            $parts[] = $base;
        }
        foreach ($notes as $note) {
            $parts[] = $note;
        }

        return trim(implode(' ', $parts));
    }

    protected function hasAttribute(ReflectionProperty $property, string $attributeClass): bool
    {
        return $property->getAttributes($attributeClass) !== [];
    }

    protected function extractDataCollectionOf(ReflectionProperty $property): ?string
    {
        $attrs = $property->getAttributes(DataCollectionOf::class);
        if ($attrs === []) {
            return null;
        }

        $args = $attrs[0]->getArguments();
        $class = $args[0] ?? ($args['class'] ?? null);

        return is_string($class) && class_exists($class) ? $class : null;
    }

    protected function promotedHasDefault(ReflectionProperty $property): bool
    {
        if (! $property->isPromoted()) {
            return $property->hasDefaultValue();
        }

        $constructor = $property->getDeclaringClass()->getConstructor();
        if (! $constructor) {
            return false;
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === $property->getName()) {
                return $param->isDefaultValueAvailable() || $param->isOptional();
            }
        }

        return false;
    }

    /**
     * @param  ReflectionNamedType[]  $types
     */
    protected function resolveType(array $types, ?string $docVarType, ?FileNameResolver $nameResolver): OpenApiType
    {
        if ($docVarType !== null) {
            $resolved = $this->resolveDocType($docVarType, $nameResolver);
            if (! $resolved instanceof OpenApiUnknownType) {
                return $resolved;
            }
        }

        $hasNull = count(array_filter($types, fn (ReflectionNamedType $type) => $type->getName() === 'null')) > 0;
        $types = array_values(array_filter($types, fn (ReflectionNamedType $type) => $type->getName() !== 'null'));

        if (count($types) === 0) {
            return new OpenApiUnknownType;
        }

        if (count($types) > 1) {
            $items = array_map(fn (ReflectionNamedType $type) => $this->resolveNamedType($type->getName()), $types);

            if ($hasNull) {
                $items[] = new OpenApiNullType;
            }

            return (new AnyOf)->setItems($items);
        }

        return $this->resolveNamedType($types[0]->getName());
    }

    protected function resolveNamedType(string $name): OpenApiType
    {
        return match ($name) {
            'int' => new OpenApiIntegerType,
            'string' => new OpenApiStringType,
            'bool' => new OpenApiBooleanType,
            'float' => new OpenApiNumberType,
            'array' => new OpenApiArrayType,
            'mixed' => new OpenApiUnknownType,
            default => $this->resolveClassType($name),
        };
    }

    protected function resolveDocType(string $docType, ?FileNameResolver $nameResolver): OpenApiType
    {
        $phpDocNode = PhpDoc::parse("/** @var $docType */", $nameResolver);

        $varTags = $phpDocNode->getVarTagValues();
        if (empty($varTags)) {
            return new OpenApiUnknownType;
        }

        $scrambleType = PhpDocTypeHelper::toType($varTags[0]->type);

        return $this->openApiTransformer->transform($scrambleType);
    }

    protected function resolveClassType(string $className): OpenApiType
    {
        if (! class_exists($className) && ! enum_exists($className)) {
            return new OpenApiUnknownType;
        }

        return $this->openApiTransformer->transform(new ScrambleObjectType($className));
    }

    protected function extractDocVarType(ReflectionProperty $property): ?string
    {
        $doc = $property->getDocComment() ?: '';

        if ($doc !== '' && preg_match('/@var\s+([^\n*]+)/', $doc, $m)) {
            return trim($m[1]);
        }

        if ($property->isPromoted()) {
            $paramType = $this->findPromotedParamType($property);
            if ($paramType !== null) {
                return $paramType;
            }
        }

        return null;
    }

    protected function findPromotedParamType(ReflectionProperty $property): ?string
    {
        $constructor = $property->getDeclaringClass()->getConstructor();
        if (! $constructor) {
            return null;
        }

        $constructorDoc = $constructor->getDocComment() ?: '';
        if ($constructorDoc === '') {
            return null;
        }

        $name = $property->getName();
        if (preg_match('/@param\s+(\S+)\s+\$'.preg_quote($name, '/').'\b/', $constructorDoc, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function extractDescription(ReflectionProperty $property): string
    {
        $doc = $property->getDocComment();
        if (! $doc) {
            return '';
        }

        $lines = preg_split('/\r?\n/', $doc) ?: [];
        $description = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t/*");
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }
            $description[] = $line;
        }

        return trim(implode(' ', $description));
    }

    protected function detectClassNameMapper(ReflectionClass $reflection): ?\Closure
    {
        foreach ($reflection->getAttributes() as $attribute) {
            if ($attribute->getName() !== \Spatie\LaravelData\Attributes\MapName::class) {
                continue;
            }

            $args = $attribute->getArguments();
            $mapper = $args[0] ?? null;
            if (is_string($mapper) && class_exists($mapper)) {
                $instance = new $mapper;

                return static fn (string $name) => $instance->map($name);
            }
        }

        return null;
    }

    protected function resolveMappedName(ReflectionProperty $property, bool $input, ?\Closure $classMapper): string
    {
        $name = $property->getName();

        $perPropertyMapper = $this->getPerPropertyMapper($property, $input);
        if ($perPropertyMapper !== null) {
            return $perPropertyMapper($name);
        }

        return $classMapper ? $classMapper($name) : $name;
    }

    protected function getPerPropertyMapper(ReflectionProperty $property, bool $input): ?\Closure
    {
        $targetAttr = $input
            ? \Spatie\LaravelData\Attributes\MapInputName::class
            : \Spatie\LaravelData\Attributes\MapName::class;

        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() !== $targetAttr) {
                continue;
            }
            $args = $attribute->getArguments();
            $mapper = $args[0] ?? null;
            if (is_string($mapper) && class_exists($mapper)) {
                $instance = new $mapper;

                return static fn (string $n) => $instance->map($n);
            }
            if (is_string($mapper)) {
                return static fn () => $mapper;
            }
        }

        return null;
    }
}
