<?php

namespace PawelJadanowski\ScrambleExtras;

use BackedEnum;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\NullType as OpenApiNullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType as OpenApiNumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType as OpenApiUnknownType;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use ReflectionClass;
use ReflectionMethod;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;
use Throwable;

class DataRulesSchemaApplier
{
    public function apply(OpenApiObjectType $schema, string $dataClass): void
    {
        $rules = $this->loadRules($dataClass);

        if ($rules === []) {
            return;
        }

        $root = $this->buildTree($rules);

        foreach ($root['children'] as $name => $node) {
            if (! $schema->hasProperty($name)) {
                continue;
            }

            $schema->addProperty($name, $this->refine($schema->getProperty($name), $node));

            if ($node['rules']['required']) {
                $schema->addRequired([$name]);
            }
        }
    }

    protected function loadRules(string $dataClass): array
    {
        if (! method_exists($dataClass, 'rules')) {
            return [];
        }

        $method = new ReflectionMethod($dataClass, 'rules');

        if (! $method->isStatic()) {
            return [];
        }

        try {
            $rules = app()->call([$dataClass, 'rules'], [
                'context' => new ValidationContext([], [], ValidationPath::create()),
            ]);
        } catch (Throwable $e) {
            if (function_exists('app') && app()->bound('log')) {
                app('log')->warning('scramble-extras could not evaluate rules()', [
                    'class' => $dataClass,
                    'exception' => $e->getMessage(),
                ]);
            }

            return [];
        }

        return is_array($rules) ? $rules : [];
    }

    protected function buildTree(array $rules): array
    {
        $root = $this->emptyNode();

        foreach ($rules as $key => $definition) {
            if (! is_string($key)) {
                continue;
            }

            $node = &$root;
            foreach (explode('.', $key) as $segment) {
                $node['children'][$segment] ??= $this->emptyNode();
                $node = &$node['children'][$segment];
            }
            $node['rules'] = $this->parseRules($definition);
            unset($node);
        }

        return $root;
    }

    protected function emptyNode(): array
    {
        return [
            'rules' => [
                'required' => false,
                'nullable' => false,
                'type' => null,
                'format' => null,
                'min' => null,
                'max' => null,
                'in' => null,
                'file' => false,
            ],
            'children' => [],
        ];
    }

    protected function parseRules(mixed $definition): array
    {
        $parsed = $this->emptyNode()['rules'];
        $items = is_string($definition) ? explode('|', $definition) : (is_array($definition) ? $definition : [$definition]);

        foreach ($items as $item) {
            if ($item instanceof In) {
                $previous = $parsed['in'];
                $rendered = (string) $item;
                $parsed['in'] = str_starts_with($rendered, 'in:') ? $this->inValues(substr($rendered, 3)) : null;
                $parsed['in'] ??= $previous;
            } elseif ($item instanceof Enum) {
                $parsed['in'] = $this->enumValues($item);
            } elseif ($item instanceof File) {
                $parsed['file'] = true;
                $parsed['min'] = $this->fileSize($item, 'minimumFileSize') ?? $parsed['min'];
                $parsed['max'] = $this->fileSize($item, 'maximumFileSize') ?? $parsed['max'];
            } elseif (is_string($item)) {
                $this->applyStringRule($parsed, $item);
            }
        }

        return $parsed;
    }

    protected function applyStringRule(array &$parsed, string $rule): void
    {
        [$name, $arguments] = array_pad(explode(':', $rule, 2), 2, null);

        switch ($name) {
            case 'required':
                $parsed['required'] = true;
                break;
            case 'nullable':
                $parsed['nullable'] = true;
                break;
            case 'string':
            case 'integer':
            case 'boolean':
            case 'array':
                $parsed['type'] = $name;
                break;
            case 'numeric':
                $parsed['type'] = 'number';
                break;
            case 'email':
                $parsed['type'] ??= 'string';
                $parsed['format'] = 'email';
                break;
            case 'url':
                $parsed['type'] ??= 'string';
                $parsed['format'] = 'uri';
                break;
            case 'uuid':
                $parsed['type'] ??= 'string';
                $parsed['format'] = 'uuid';
                break;
            case 'min':
                $parsed['min'] = is_numeric($arguments) ? $arguments + 0 : null;
                break;
            case 'max':
                $parsed['max'] = is_numeric($arguments) ? $arguments + 0 : null;
                break;
            case 'in':
                $parsed['in'] = ($arguments === null ? null : $this->inValues($arguments)) ?? $parsed['in'];
                break;
            case 'file':
            case 'image':
            case 'mimes':
            case 'mimetypes':
            case 'extensions':
                $parsed['file'] = true;
                break;
        }
    }

    protected function fileSize(File $rule, string $property): int|float|null
    {
        $reflection = new ReflectionClass($rule);

        if (! $reflection->hasProperty($property)) {
            return null;
        }

        $value = $reflection->getProperty($property)->getValue($rule);

        return is_numeric($value) ? $value + 0 : null;
    }

    protected function inValues(string $arguments): ?array
    {
        $values = array_values(array_filter(
            str_getcsv($arguments, ',', '"', ''),
            fn ($value) => $value !== null && $value !== '',
        ));

        return $values === [] ? null : $values;
    }

    protected function enumValues(Enum $rule): ?array
    {
        $reflection = new ReflectionClass($rule);
        $read = function (string $property) use ($reflection, $rule) {
            if (! $reflection->hasProperty($property)) {
                return null;
            }

            $reflectionProperty = $reflection->getProperty($property);

            return $reflectionProperty->getValue($rule);
        };

        $enum = $read('type');

        if (! is_string($enum) || ! is_subclass_of($enum, BackedEnum::class)) {
            return null;
        }

        $only = $read('only') ?: null;
        $except = $read('except') ?: [];

        $cases = array_filter(
            $enum::cases(),
            fn (BackedEnum $case) => ($only === null || in_array($case, $only, true)) && ! in_array($case, $except, true),
        );

        return array_values(array_map(fn (BackedEnum $case) => $case->value, $cases));
    }

    protected function refine(OpenApiType $type, array $node): OpenApiType
    {
        if ($this->isReferenceLike($type)) {
            return $type;
        }

        $rules = $node['rules'];
        $children = $node['children'];
        $itemNode = $children['*'] ?? null;
        unset($children['*']);

        if ($itemNode !== null) {
            $map = $this->findMap($type);

            if ($map !== null) {
                $map->additionalProperties($this->refine($map->additionalProperties, $itemNode));
            } else {
                $array = $type instanceof OpenApiArrayType ? $type : new OpenApiArrayType;
                $items = $array->items instanceof OpenApiType && ! $array->items instanceof OpenApiUnknownType
                    ? $array->items
                    : new OpenApiUnknownType;
                $array->setItems($this->refine($items, $itemNode));
                $type = $array;
            }
        }

        if ($children !== []) {
            $object = $type instanceof OpenApiObjectType ? $type : new OpenApiObjectType;

            foreach ($children as $name => $child) {
                $existing = $object->hasProperty($name) ? $object->getProperty($name) : new OpenApiUnknownType;
                $object->addProperty($name, $this->refine($existing, $child));

                if ($child['rules']['required']) {
                    $object->addRequired([$name]);
                }
            }

            $type = $object;
        } elseif ($itemNode === null) {
            $type = $this->coerce($type, $rules['type']);
        }

        return $this->constrain($type, $rules);
    }

    protected function findMap(OpenApiType $type): ?OpenApiObjectType
    {
        if ($type instanceof OpenApiObjectType) {
            return $type->additionalProperties instanceof OpenApiType ? $type : null;
        }

        if ($type instanceof AnyOf) {
            foreach ($type->items as $item) {
                $map = $this->findMap($item);

                if ($map !== null) {
                    return $map;
                }
            }
        }

        return null;
    }

    protected function coerce(OpenApiType $type, ?string $ruleType): OpenApiType
    {
        if (! $type instanceof OpenApiUnknownType) {
            return $type;
        }

        return match ($ruleType) {
            'string' => new OpenApiStringType,
            'integer' => new OpenApiIntegerType,
            'number' => new OpenApiNumberType,
            'boolean' => new OpenApiBooleanType,
            'array' => new OpenApiArrayType,
            default => $type,
        };
    }

    protected function constrain(OpenApiType $type, array $rules): OpenApiType
    {
        if ($this->isReferenceLike($type)) {
            return $type;
        }

        if ($rules['file'] && ! ($type instanceof OpenApiStringType && $type->format === 'binary')) {
            $type = (new OpenApiStringType)
                ->contentMediaType('application/octet-stream')
                ->format('binary')
                ->nullable($type->nullable)
                ->setDescription($type->description);
        }

        if ($rules['required'] && ! $rules['nullable']) {
            $this->setNullable($type, false);
        } elseif ($rules['nullable']) {
            $this->setNullable($type, true);
        }

        if ($rules['format'] !== null && $type instanceof OpenApiStringType && $type->format === '') {
            $type->format($rules['format']);
        }

        if ($rules['in'] !== null && $type->enum === [] && ! $type instanceof OpenApiObjectType && ! $type instanceof OpenApiArrayType) {
            $type->enum($this->castValues($type, $rules['in']));
        }

        if ($type instanceof OpenApiStringType && ($type->format === 'binary' || $type->contentMediaType === 'application/octet-stream')) {
            $sizes = array_filter([
                $rules['min'] !== null ? "Minimum file size: {$rules['min']} kilobytes." : null,
                $rules['max'] !== null ? "Maximum file size: {$rules['max']} kilobytes." : null,
            ]);

            if ($sizes !== []) {
                $type->setDescription(trim($type->description.' '.implode(' ', $sizes)));
            }
        } elseif ($type instanceof OpenApiStringType || $type instanceof OpenApiNumberType) {
            if ($rules['min'] !== null && $type->min === null) {
                $type->setMin($rules['min']);
            }
            if ($rules['max'] !== null && $type->max === null) {
                $type->setMax($rules['max']);
            }
        } elseif ($type instanceof OpenApiArrayType) {
            if ($rules['min'] !== null && $type->minItems === null) {
                $type->setMin($rules['min']);
            }
            if ($rules['max'] !== null && $type->maxItems === null) {
                $type->setMax($rules['max']);
            }
        }

        return $type;
    }

    protected function isReferenceLike(OpenApiType $type): bool
    {
        return $type instanceof Reference
            || ($type instanceof AnyOf && collect($type->items)->contains(fn (OpenApiType $item) => $item instanceof Reference));
    }

    protected function setNullable(OpenApiType $type, bool $nullable): void
    {
        if (! $type instanceof AnyOf) {
            $type->nullable($nullable);

            return;
        }

        $items = array_values(array_filter($type->items, fn (OpenApiType $item) => ! $item instanceof OpenApiNullType));

        if ($nullable) {
            $items[] = new OpenApiNullType;
        }

        $type->setItems($items);
    }

    protected function castValues(OpenApiType $type, array $values): array
    {
        if ($type instanceof OpenApiIntegerType) {
            return array_map(fn ($value) => is_numeric($value) ? (int) $value : $value, $values);
        }

        if ($type instanceof OpenApiNumberType) {
            return array_map(fn ($value) => is_numeric($value) ? $value + 0 : $value, $values);
        }

        return $values;
    }
}
