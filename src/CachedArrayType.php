<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;

class CachedArrayType extends ArrayType
{
    use CachedLeafType;

    public function __construct(array $cachedArray)
    {
        parent::__construct();

        if (isset($cachedArray['items']) && (is_array($cachedArray['items']) || $cachedArray['items'] instanceof \stdClass)) {
            $this->items = CachedSchemaType::typeFromArray($cachedArray['items']);
        }

        $this->prefixItems = array_map(
            fn ($item) => CachedSchemaType::typeFromArray($item),
            $cachedArray['prefixItems'] ?? [],
        );
        $this->minItems = $cachedArray['minItems'] ?? null;
        $this->maxItems = $cachedArray['maxItems'] ?? null;
        $this->uniqueItems = $cachedArray['uniqueItems'] ?? null;

        $this->hydrate($cachedArray);
    }

    public function toArray()
    {
        $result = $this->overlayMutations();

        if ($this->items->getAttribute('missing') && count($this->prefixItems)) {
            unset($result['items']);
        } elseif (! $this->items->getAttribute('missing')) {
            $result['items'] = $this->items->toArray();
        }

        if ($this->prefixItems) {
            $result['prefixItems'] = array_map(fn ($item) => $item->toArray(), $this->prefixItems);
        } else {
            unset($result['prefixItems']);
        }

        foreach (['minItems' => $this->minItems, 'maxItems' => $this->maxItems, 'uniqueItems' => $this->uniqueItems] as $key => $value) {
            if ($value === null) {
                unset($result[$key]);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
