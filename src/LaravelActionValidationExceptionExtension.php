<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Request;
use Lorisleiva\Actions\ActionRequest;

class LaravelActionValidationExceptionExtension extends OperationExtension
{
    private const VALIDATION_METHODS = ['rules', 'withValidator', 'afterValidator', 'getValidator'];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! ($methodType = $routeInfo->getActionType()) || ! ($method = $routeInfo->reflectionMethod())) {
            return;
        }

        $class = $method->getDeclaringClass();

        if (collect(self::VALIDATION_METHODS)->contains(fn (string $name) => $class->hasMethod($name))) {
            return;
        }

        $methodType->arguments = array_map(function (Type $argument) {
            $resolved = $argument instanceof TemplateType ? $argument->is : $argument;

            return $resolved instanceof ObjectType && $resolved->name === ActionRequest::class
                ? new ObjectType(Request::class)
                : $argument;
        }, $methodType->arguments);
    }
}
