<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class LaravelActionValidationExceptionExtension extends OperationExtension
{
    private const VALIDATION_METHODS = ['rules', 'withValidator', 'afterValidator', 'getValidator'];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! ($methodType = $routeInfo->getActionType()) || ! ($method = $routeInfo->reflectionMethod())) {
            return;
        }

        $methodType->arguments = array_map(function (Type $argument) {
            $resolved = $argument instanceof TemplateType ? $argument->is : $argument;

            return $resolved instanceof ObjectType && $resolved->name === ActionRequest::class
                ? new ObjectType(Request::class)
                : $argument;
        }, $methodType->arguments);

        $class = $method->getDeclaringClass();
        $uses = class_uses_recursive($class->getName());

        if (
            ! in_array(AsController::class, $uses, true)
            || in_array(WithAttributes::class, $uses, true)
            || ! in_array($method->getName(), ['asController', 'handle', '__invoke'], true)
        ) {
            return;
        }

        $exceptions = collect([
            ValidationException::class => collect(self::VALIDATION_METHODS)->contains(fn (string $name) => $class->hasMethod($name)),
            AuthorizationException::class => $class->hasMethod('authorize'),
        ])->filter()->keys()->reject(
            fn (string $exception) => collect($methodType->exceptions)->contains(fn (Type $e) => $e->isInstanceOf($exception))
        );

        $methodType->exceptions = [
            ...$methodType->exceptions,
            ...$exceptions->map(fn (string $exception) => new ObjectType($exception))->all(),
        ];
    }
}
