<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Foundation\Http\FormRequest;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;
use PhpParser\PrettyPrinter;
use ReflectionClass;
use ReflectionMethod;
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

        if (! $method) {
            return $parameterExtractionResults;
        }

        $class = $method->getDeclaringClass()->getName();
        $uses = class_uses_recursive($class);

        if (
            ! in_array(AsController::class, $uses, true)
            || in_array(WithAttributes::class, $uses, true)
            || ! in_array($method->getName(), ['asController', 'handle', '__invoke'], true)
            || ! $this->hasPublicRules($method->getDeclaringClass())
            || $this->hasCustomFormRequestParameter($method)
        ) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = (new FormRequestParametersExtractor(
            $this->printer,
            $this->openApiTransformer,
            $this->diagnostics,
        ))->extractFormRequestParameters($class, $routeInfo);

        return $parameterExtractionResults;
    }

    protected function hasPublicRules(ReflectionClass $class): bool
    {
        return $class->hasMethod('rules') && $class->getMethod('rules')->isPublic();
    }

    protected function hasCustomFormRequestParameter(ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && is_a($type->getName(), FormRequest::class, true)
                && ! is_a($type->getName(), ActionRequest::class, true)
            ) {
                return true;
            }
        }

        return false;
    }
}
