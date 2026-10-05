<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Types\Type;

class CachedScalarType extends Type
{
    use CachedLeafType;

    public function __construct(array $cachedArray)
    {
        parent::__construct(self::kind($cachedArray));

        $this->hydrate($cachedArray);
    }

    public static function kind(array $array): string
    {
        if (isset($array['$ref'])) {
            return '$ref';
        }

        if (isset($array['anyOf'])) {
            return 'anyOf';
        }

        $type = $array['type'] ?? '';

        if (is_array($type)) {
            $type = array_values(array_filter($type, fn ($item) => $item !== 'null'))[0] ?? 'null';
        }

        return $type;
    }

    public function toArray()
    {
        return $this->overlayMutations();
    }
}
