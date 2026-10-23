<?php

namespace PawelJadanowski\ScrambleExtras;

use Attribute;
use InvalidArgumentException;
use Spatie\LaravelData\Contracts\BaseData;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ResponseAnyOf
{
    public function __construct(public array $classes) {}

    public function resolve(): array
    {
        $classes = $this->isCallable() ? ($this->classes)() : $this->classes;

        if (! is_array($classes)) {
            throw new InvalidArgumentException('ResponseAnyOf callable must return an array of class names, got '.get_debug_type($classes));
        }

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                throw new InvalidArgumentException('ResponseAnyOf class does not exist: '.(is_string($class) ? $class : get_debug_type($class)));
            }

            if (! is_subclass_of($class, BaseData::class)) {
                throw new InvalidArgumentException("ResponseAnyOf class is not a Data class: {$class}");
            }
        }

        $classes = array_values(array_unique($classes));

        if ($classes === []) {
            throw new InvalidArgumentException('ResponseAnyOf requires at least one class.');
        }

        return $classes;
    }

    private function isCallable(): bool
    {
        return count($this->classes) === 2
            && is_callable($this->classes)
            && ! (is_string($this->classes[1]) && class_exists($this->classes[1]));
    }
}
