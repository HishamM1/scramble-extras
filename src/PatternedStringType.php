<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * StringType variant that knows how to render a `pattern` field — the base
 * Scramble StringType only supports minLength/maxLength.
 */
class PatternedStringType extends StringType
{
    public ?string $pattern = null;

    public function setPattern(?string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function toArray()
    {
        $array = parent::toArray();

        if ($this->pattern !== null) {
            $array['pattern'] = $this->pattern;
        }

        return $array;
    }
}
