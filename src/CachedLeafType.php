<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\MissingValue;

trait CachedLeafType
{
    private array $cachedArray = [];

    private array $initial = [];

    private function hydrate(array $cachedArray): void
    {
        $this->cachedArray = $cachedArray;

        $this->format = $cachedArray['format'] ?? '';
        $this->contentMediaType = $cachedArray['contentMediaType'] ?? '';
        $this->contentEncoding = $cachedArray['contentEncoding'] ?? '';
        $this->description = $cachedArray['description'] ?? '';
        $this->deprecated = $cachedArray['deprecated'] ?? false;
        $this->pattern = $cachedArray['pattern'] ?? null;
        $this->enum = $cachedArray['enum'] ?? [];
        $this->const = $cachedArray['const'] ?? null;
        $this->default = array_key_exists('default', $cachedArray) ? $cachedArray['default'] : new MissingValue;
        $this->examples = $cachedArray['examples'] ?? [];
        $this->nullable = is_array($cachedArray['type'] ?? null) && in_array('null', $cachedArray['type'], true)
            || self::nullMemberIndex($cachedArray) !== null;

        $this->initial = $this->snapshot();
    }

    private function snapshot(): array
    {
        return [
            'format' => $this->format,
            'contentMediaType' => $this->contentMediaType,
            'contentEncoding' => $this->contentEncoding,
            'description' => $this->description,
            'deprecated' => $this->deprecated,
            'pattern' => $this->pattern,
            'enum' => $this->enum,
            'const' => $this->const,
            'default' => $this->default,
            'examples' => $this->mergedExamples(),
            'nullable' => $this->nullable,
        ];
    }

    private function mergedExamples(): array
    {
        return collect($this->examples)
            ->prepend($this->example)
            ->reject(fn ($example) => $example instanceof MissingValue)
            ->values()
            ->all();
    }

    private function overlayMutations(): array
    {
        $result = $this->cachedArray;
        $current = $this->snapshot();

        foreach (['format', 'contentMediaType', 'contentEncoding', 'description', 'deprecated', 'pattern', 'enum', 'const'] as $field) {
            if ($current[$field] === $this->initial[$field]) {
                continue;
            }

            if ($current[$field] === '' || $current[$field] === [] || $current[$field] === null || $current[$field] === false) {
                unset($result[$field]);
            } else {
                $result[$field] = $current[$field];
            }
        }

        if ($current['default'] !== $this->initial['default']) {
            if ($current['default'] instanceof MissingValue) {
                unset($result['default']);
            } else {
                $result['default'] = $current['default'];
            }
        }

        if ($current['examples'] !== $this->initial['examples']) {
            $result['examples'] = $current['examples'];
        }

        if ($current['nullable'] !== $this->initial['nullable']) {
            $result = $this->applyNullable($result, $current['nullable']);
        }

        return $result;
    }

    private static function nullMemberIndex(array $array): ?int
    {
        foreach ($array['anyOf'] ?? [] as $index => $member) {
            if (is_array($member) && ($member['type'] ?? null) === 'null') {
                return $index;
            }
        }

        return null;
    }

    private function applyNullable(array $result, bool $nullable): array
    {
        if (isset($result['anyOf'])) {
            return $this->applyNullableToAnyOf($result, $nullable);
        }

        if (isset($result['$ref'])) {
            return $nullable ? ['anyOf' => [$result, ['type' => 'null']]] : $result;
        }

        if (isset($result['type'])) {
            $types = array_values(array_filter((array) $result['type'], fn ($type) => $type !== 'null'));

            if ($types !== []) {
                if ($nullable) {
                    $types[] = 'null';
                }

                $result['type'] = count($types) === 1 ? $types[0] : $types;
            }
        }

        if (isset($result['enum'])) {
            $enum = array_values(array_filter($result['enum'], fn ($value) => $value !== null));

            if ($nullable) {
                $enum[] = null;
            }

            $result['enum'] = $enum;
        }

        if ($nullable && array_key_exists('const', $result) && $result['const'] !== null) {
            $result['enum'] = [$result['const'], null];
            unset($result['const']);
        }

        return $result;
    }

    private function applyNullableToAnyOf(array $result, bool $nullable): array
    {
        $index = self::nullMemberIndex($result);

        if ($nullable) {
            if ($index === null) {
                $result['anyOf'][] = ['type' => 'null'];
            }

            return $result;
        }

        if ($index === null) {
            return $result;
        }

        unset($result['anyOf'][$index]);
        $members = array_values($result['anyOf']);
        unset($result['anyOf']);

        if (count($members) === 1 && is_array($members[0])) {
            return [...$result, ...$members[0]];
        }

        $result['anyOf'] = $members;

        return $result;
    }
}
