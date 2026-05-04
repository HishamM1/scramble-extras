<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Types\NumberType;

/**
 * NumberType variant supporting exclusiveMinimum/exclusiveMaximum/multipleOf.
 * Scramble's base NumberType only handles inclusive minimum/maximum.
 */
class ConstrainedNumberType extends NumberType
{
    public int|float|null $exclusiveMin = null;

    public int|float|null $exclusiveMax = null;

    public int|float|null $multipleOf = null;

    public function setExclusiveMin(int|float|null $value): self
    {
        $this->exclusiveMin = $value;

        return $this;
    }

    public function setExclusiveMax(int|float|null $value): self
    {
        $this->exclusiveMax = $value;

        return $this;
    }

    public function setMultipleOf(int|float|null $value): self
    {
        $this->multipleOf = $value;

        return $this;
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), array_filter([
            'exclusiveMinimum' => $this->exclusiveMin,
            'exclusiveMaximum' => $this->exclusiveMax,
            'multipleOf' => $this->multipleOf,
        ], fn ($v) => $v !== null));
    }
}
