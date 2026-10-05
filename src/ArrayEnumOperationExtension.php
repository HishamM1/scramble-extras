<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;

class ArrayEnumOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $visited = [];

        foreach ($operation->parameters as $parameter) {
            $this->fix($parameter->schema, $visited);
        }

        foreach ($operation->requestBodyObject->content ?? [] as $schema) {
            $this->fix($schema, $visited);
        }
    }

    /** @param array<int, true> $visited */
    protected function fix(Schema|Reference|Type|null $node, array &$visited): void
    {
        if ($node instanceof Reference) {
            $resolved = $node->resolve();
            $node = $resolved instanceof Schema ? $resolved : null;
        }

        if ($node === null || isset($visited[spl_object_id($node)])) {
            return;
        }

        $visited[spl_object_id($node)] = true;

        if ($node instanceof Schema) {
            $node = $node->type;
        }

        if ($node instanceof ArrayType) {
            if ($node->enum !== []) {
                if ($node->items->enum === []) {
                    $node->items->enum($node->enum);
                }

                $node->enum([]);
            }

            $this->fix($node->items, $visited);

            foreach ($node->prefixItems as $item) {
                $this->fix($item, $visited);
            }
        } elseif ($node instanceof ObjectType) {
            foreach ($node->properties as $property) {
                $this->fix($property, $visited);
            }
        } elseif ($node instanceof AnyOf) {
            foreach ($node->items as $item) {
                $this->fix($item, $visited);
            }
        }
    }
}
