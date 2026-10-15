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

    public function serializeAs31(): mixed
    {
        return $this->withPattern(parent::serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->withPattern(parent::serializeAs32());
    }

    private function withPattern(array $array): array
    {

        if ($this->pattern !== null) {
            $array['pattern'] = $this->pattern;
        }

        return $array;
    }
}
