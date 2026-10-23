<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use ReflectionClass;
use ReflectionMethod;

class ResponseAnyOfOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $attribute = $this->attribute($routeInfo->reflectionMethod())
            ?? $this->attribute($routeInfo->className() ? new ReflectionClass($routeInfo->className()) : null);

        if ($attribute === null) {
            return;
        }

        $items = array_map(
            fn (string $class) => $this->openApiTransformer->transform(new ObjectType($class)),
            $attribute->resolve(),
        );

        $schema = Schema::fromType((new AnyOf)->setItems($items));

        foreach ($operation->responses as $index => $response) {
            $resolved = $response instanceof Reference ? $response->resolve() : $response;

            $code = (int) $resolved->code;

            if ($code < 200 || $code > 299) {
                continue;
            }

            if (in_array($code, [204, 205], true)) {
                continue;
            }

            if ($response instanceof Reference) {
                $resolved = Response::make($resolved->code);
                $operation->responses[$index] = $resolved;
            }

            $resolved->setDescription('')->setContent('application/json', $schema);

            return;
        }

        if ($this->hasResponseWithCode($operation, [204, 205])) {
            return;
        }

        $operation->addResponse(Response::make(200)->setContent('application/json', $schema));
    }

    private function hasResponseWithCode(Operation $operation, array $codes): bool
    {
        foreach ($operation->responses as $response) {
            $resolved = $response instanceof Reference ? $response->resolve() : $response;

            if (in_array((int) $resolved->code, $codes, true)) {
                return true;
            }
        }

        return false;
    }

    private function attribute(ReflectionMethod|ReflectionClass|null $reflector): ?ResponseAnyOf
    {
        return ($reflector?->getAttributes(ResponseAnyOf::class)[0] ?? null)?->newInstance();
    }
}
