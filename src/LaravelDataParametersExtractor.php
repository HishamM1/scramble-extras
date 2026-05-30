<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Spatie\LaravelData\Contracts\BaseData;

class LaravelDataParametersExtractor implements ParameterExtractor
{
    public function __construct(
        protected TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $reflectionMethod = $routeInfo->reflectionMethod();
        if (! $reflectionMethod) {
            return $parameterExtractionResults;
        }

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $dataClass = $this->getDataClassName($parameter);
            if ($dataClass === null) {
                continue;
            }

            $parameterExtractionResults[] = $this->buildResult($dataClass);
        }

        return $parameterExtractionResults;
    }

    protected function getDataClassName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        $className = $type->getName();
        if (! class_exists($className)) {
            return null;
        }

        if (! is_a($className, BaseData::class, true)) {
            return null;
        }

        return (new ReflectionClass($className))->isInstantiable()
            ? $className
            : null;
    }

    protected function buildResult(string $dataClass): ParametersExtractionResult
    {
        $reflector = new LaravelDataReflector(
            $this->openApiTransformer,
            $this->openApiTransformer->getComponents(),
        );

        $body = (new Parameter('*', 'body'))
            ->setSchema(Schema::fromType($reflector->buildSchema($dataClass, input: true)));

        // The body schema is inlined (no `schemaName`) on purpose. A Data class
        // is frequently used as both a response and a request body, and its
        // input and output projections differ: `#[Computed]` props are output
        // only, `#[Hidden]` props are input only, `Optional`/`MapInputName`
        // change required-ness and naming. If we registered the input schema
        // under the bare class name, it would collide with the output component
        // of the same name — and whichever was generated last would silently
        // win, leaving the request body describing the wrong shape. Inlining
        // keeps the request body faithful to the input rules regardless.
        return new ParametersExtractionResult(
            parameters: [$body],
            schemaName: null,
        );
    }
}
