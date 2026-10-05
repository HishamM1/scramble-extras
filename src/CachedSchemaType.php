<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class CachedSchemaType extends OpenApiObjectType
{
    use CachedLeafType;

    /** @param array<string, mixed> $cachedArray */
    public function __construct(array $cachedArray)
    {
        parent::__construct();

        if (isset($cachedArray['required']) && is_array($cachedArray['required'])) {
            $this->required = $cachedArray['required'];
        }

        if (isset($cachedArray['properties']) && is_array($cachedArray['properties'])) {
            foreach ($cachedArray['properties'] as $name => $property) {
                $this->properties[$name] = self::typeFromArray($property);
            }
        }

        $this->hydrate($cachedArray);
    }

    public static function typeFromArray(array|\stdClass $array): Type
    {
        if ($array instanceof \stdClass) {
            return new MixedType;
        }

        return match (CachedScalarType::kind($array)) {
            'object' => new self($array),
            'array' => new CachedArrayType($array),
            default => new CachedScalarType($array),
        };
    }

    public function toArray()
    {
        $result = $this->overlayMutations();

        if ($this->properties !== []) {
            $result['properties'] = array_map(
                fn (?Type $property) => $property ? $property->toArray() : ['type' => 'string'],
                $this->properties,
            );
        } elseif (is_array($result['properties'] ?? null)) {
            unset($result['properties']);
        }

        if ($this->required !== []) {
            $result['required'] = array_values($this->required);
        } elseif (is_array($result['required'] ?? null)) {
            unset($result['required']);
        }

        return $result;
    }
}
