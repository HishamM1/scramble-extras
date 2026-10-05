<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Lorisleiva\Actions\ActionRequest;
use PhpParser\PrettyPrinter;
use ReflectionNamedType;

class LaravelActionRulesParametersExtractor implements ParameterExtractor
{
    public function __construct(
        protected PrettyPrinter $printer,
        protected TypeTransformer $openApiTransformer,
        protected DiagnosticsCollector $diagnostics,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $method = $routeInfo->reflectionMethod();

        if (! $method || ! $this->hasActionRequestParameter($method) || ! method_exists($method->getDeclaringClass()->getName(), 'rules')) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = (new FormRequestParametersExtractor(
            $this->printer,
            $this->openApiTransformer,
            $this->diagnostics,
        ))->extractFormRequestParameters($method->getDeclaringClass()->getName(), $routeInfo);

        return $parameterExtractionResults;
    }

    protected function hasActionRequestParameter(\ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), ActionRequest::class, true)) {
                return true;
            }
        }

        return false;
    }
}
