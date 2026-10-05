<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
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

    protected function containsBinary(OpenApiObjectType $schema): bool
    {
        return str_contains(
            json_encode($schema->toArray(), JSON_THROW_ON_ERROR),
            '"contentMediaType":"application\/octet-stream"',
        );
    }

    protected function flattenForMultipart(OpenApiObjectType $schema): OpenApiObjectType
    {
        $flat = new OpenApiObjectType;
        $required = [];

        $this->flattenInto($flat, $required, $schema, '', true);

        return $flat->setRequired($required)->setDescription($schema->description);
    }

    protected function flattenInto(OpenApiObjectType $flat, array &$required, OpenApiObjectType $schema, string $prefix, bool $requiredPath): void
    {
        foreach ($schema->properties as $name => $type) {
            $key = $prefix === '' ? $name : "{$prefix}[{$name}]";
            $isRequired = $requiredPath && in_array($name, $schema->required, true);

            if ($type instanceof OpenApiObjectType && $type->properties !== []) {
                $this->flattenInto($flat, $required, $type, $key, $isRequired);

                continue;
            }

            if ($type instanceof OpenApiArrayType && ! $this->isObjectLike($type->items)) {
                $key .= '[]';
            }

            $flat->addProperty($key, $type);

            if ($isRequired) {
                $required[] = $key;
            }
        }
    }

    protected function isObjectLike(OpenApiType $type): bool
    {
        if ($type instanceof OpenApiObjectType || $type instanceof Reference) {
            return true;
        }

        if ($type instanceof CachedScalarType) {
            return $this->cachedReferencesObject($type->toArray());
        }

        return $type instanceof AnyOf && array_filter($type->items, fn (OpenApiType $item) => $this->isObjectLike($item)) !== [];
    }

    protected function cachedReferencesObject(array $schema): bool
    {
        if (isset($schema['$ref']) || ($schema['type'] ?? null) === 'object') {
            return true;
        }

        foreach ($schema['anyOf'] ?? [] as $member) {
            if (is_array($member) && $this->cachedReferencesObject($member)) {
                return true;
            }
        }

        return false;
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

        if ($this->containsBinary($schema)) {
            $schema = $this->flattenForMultipart($schema);
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
