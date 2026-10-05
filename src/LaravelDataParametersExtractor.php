<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
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

            $parameterExtractionResults = array_values(array_filter(
                $parameterExtractionResults,
                fn (ParametersExtractionResult $result) => $result->sourceClass !== $dataClass,
            ));
            $parameterExtractionResults[] = $this->buildResult($dataClass, $this->hasRequestBody($routeInfo));
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

    protected function hasRequestBody(RouteInfo $routeInfo): bool
    {
        $method = strtolower($routeInfo->route->methods()[0] ?? 'get');

        return ! in_array($method, RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY, true);
    }

    protected function buildResult(string $dataClass, bool $asBody): ParametersExtractionResult
    {
        $reflector = new LaravelDataReflector(
            $this->openApiTransformer,
            $this->openApiTransformer->getComponents(),
        );

        $schema = $reflector->buildSchema($dataClass, input: true);

        if (! $asBody) {
            return new ParametersExtractionResult(
                parameters: array_map(
                    fn (string $name, $type) => (new Parameter($name, 'query'))
                        ->setSchema(Schema::fromType($type))
                        ->required(in_array($name, $schema->required, true)),
                    array_keys($schema->properties),
                    array_values($schema->properties),
                ),
                sourceClass: $dataClass,
            );
        }

        $body = (new Parameter('*', 'body'))
            ->setSchema(Schema::fromType($schema));

        return new ParametersExtractionResult(
            parameters: [$body],
            schemaName: (new ReflectionClass($dataClass))->getShortName().'Input',
            sourceClass: $dataClass,
        );
    }
}
