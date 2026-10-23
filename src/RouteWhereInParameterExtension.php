<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Reflection\ReflectionRoute;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

class RouteWhereInParameterExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;
        $aliases = ReflectionRoute::createFromRoute($route)->getSignatureParametersMap();
        $domainParameters = Str::of((string) $route->getDomain())->matchAll('/\{(.*?)\}/')->all();

        foreach ($route->wheres as $name => $pattern) {
            if (in_array($name, $domainParameters, true) || ! array_key_exists($name, $aliases)) {
                continue;
            }

            if (! is_string($pattern) || ! preg_match('/^[A-Za-z0-9_\-]+(\|[A-Za-z0-9_\-]+)*$/', $pattern)) {
                continue;
            }

            $scrambleName = $aliases[$name];

            foreach ($operation->parameters as $parameter) {
                if (! $parameter instanceof Parameter || $parameter->in !== 'path' || $parameter->name !== $scrambleName) {
                    continue;
                }

                $type = $this->narrowedType($parameter->schema, explode('|', $pattern));

                if ($type !== null) {
                    $parameter->schema = Schema::fromType($type);
                }
            }
        }
    }

    private function narrowedType(Schema|Reference|null $schema, array $values): ?Type
    {
        $type = $schema instanceof Schema ? $schema->type : $schema;
        $base = $type;

        if ($type instanceof Reference) {
            $resolved = $type->resolve();
            $base = $resolved instanceof Schema ? $resolved->type : $resolved;
        }

        if (! $base instanceof StringType && ! $base instanceof IntegerType) {
            return null;
        }

        $isInteger = $base instanceof IntegerType;

        if ($isInteger && array_filter($values, fn (string $value) => (string) (int) $value !== $value) !== []) {
            return null;
        }

        if ($type instanceof Reference) {
            $narrowed = $isInteger ? new IntegerType : new StringType;
            $narrowed->default = $type->default;
            $narrowed->setDescription($type->description);
        } else {
            $narrowed = $type->clone();
        }

        return $narrowed->enum($isInteger ? array_map('intval', $values) : $values);
    }
}
