<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Model;
use PhpParser\NodeTraverser;
use ReflectionClass;
use ReflectionEnum;

/**
 * Walks a controller method's AST for spatie/laravel-query-builder usage and
 * adds matching query parameters to the OpenAPI operation.
 *
 * Extracts:
 *  - allowedFilters (string literals + AllowedFilter::exact/partial/scope/...)
 *  - allowedSorts, allowedIncludes, allowedFields
 *  - defaultSort (-> sort param default)
 *  - jsonPaginate (-> page[number]/page[size])
 *  - the model class from QueryBuilder::for(Model::class) (used to detect
 *    enum-cast columns and emit `enum` on exact filters).
 */
class QueryBuilderOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $methodNode = $routeInfo->methodNode();
        if ($methodNode === null || $methodNode->stmts === null) {
            return;
        }

        $resolver = $this->makeNameResolver($routeInfo);

        $finder = new QueryBuilderUsageVisitor($resolver);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($finder);
        $traverser->traverse($methodNode->stmts);

        if (! $finder->found) {
            return;
        }

        $params = $this->buildParameters($finder);

        if ($params !== []) {
            $operation->addParameters($params);
        }
    }

    protected function makeNameResolver(RouteInfo $routeInfo): ?FileNameResolver
    {
        $reflection = $routeInfo->reflectionMethod();
        if (! $reflection) {
            return null;
        }

        $file = $reflection->getDeclaringClass()->getFileName();

        return $file ? FileNameResolver::createForFile($file) : null;
    }

    /**
     * @return Parameter[]
     */
    protected function buildParameters(QueryBuilderUsageVisitor $finder): array
    {
        $parameters = [];
        $modelEnumCasts = $finder->modelClass ? $this->resolveEnumCasts($finder->modelClass) : [];

        foreach ($finder->filters as $filter) {
            $parameters[] = $this->buildFilterParameter($filter, $modelEnumCasts);
        }

        if ($finder->sorts !== []) {
            $description = 'Sort the result. Prefix with `-` for descending order. Multiple values can be passed, separated via `,`. Allowed: `'
                .implode('`, `', $finder->sorts).'`';

            $sortType = new OpenApiStringType;
            $param = (new Parameter('sort', 'query'))
                ->description($description)
                ->setSchema(Schema::fromType($sortType));

            if ($finder->defaultSort !== null) {
                $sortType->default($finder->defaultSort);
            }

            $parameters[] = $param;
        }

        if ($finder->includes !== []) {
            $description = 'Include related resources. Multiple values can be passed, separated via `,`. Allowed: `'
                .implode('`, `', $finder->includes).'`';

            $parameters[] = (new Parameter('include', 'query'))
                ->description($description)
                ->setSchema(Schema::fromType(new OpenApiStringType));
        }

        $groupedFields = $this->groupFieldsByResource($finder->fields);
        foreach ($groupedFields as $resource => $fields) {
            $key = $resource === '_root' ? 'fields' : "fields[$resource]";
            $description = 'Sparse fieldset. Comma separated. Allowed: `'.implode('`, `', $fields).'`';

            $parameters[] = (new Parameter($key, 'query'))
                ->description($description)
                ->setSchema(Schema::fromType(new OpenApiStringType));
        }

        if ($finder->jsonPaginate) {
            $parameters[] = (new Parameter('page[number]', 'query'))
                ->description('Page number (starts at 1).')
                ->setSchema(Schema::fromType((new OpenApiIntegerType)->default(1)));

            $parameters[] = (new Parameter('page[size]', 'query'))
                ->description('Number of items per page.')
                ->setSchema(Schema::fromType(new OpenApiIntegerType));
        }

        return $parameters;
    }

    /**
     * @param  array{name: string, kind: string, description?: string, example?: mixed, format?: string}  $filter
     * @param  array<string, array<int, scalar>>  $modelEnumCasts
     */
    protected function buildFilterParameter(array $filter, array $modelEnumCasts): Parameter
    {
        $type = new OpenApiStringType;

        if (isset($filter['format'])) {
            $type->format($filter['format']);
        }

        // exact filter on an enum-cast column → emit enum schema.
        if ($filter['kind'] === 'exact' && isset($modelEnumCasts[$filter['name']])) {
            $type->enum($modelEnumCasts[$filter['name']]);
        }

        $description = $this->describeFilter($filter);

        $parameter = (new Parameter("filter[{$filter['name']}]", 'query'))
            ->description($description)
            ->setSchema(Schema::fromType($type));

        if (isset($filter['example'])) {
            $parameter->example($filter['example']);
        }

        return $parameter;
    }

    /**
     * @param  array{name: string, kind: string, description?: string}  $filter
     */
    protected function describeFilter(array $filter): string
    {
        $base = trim($filter['description'] ?? '');

        $kindNote = match ($filter['kind']) {
            'exact' => 'Exact match.',
            'partial' => 'Partial (LIKE %value%) match.',
            'beginsWithStrict' => 'Begins-with match.',
            'endsWithStrict' => 'Ends-with match.',
            'belongsTo' => 'Filters related belongsTo records.',
            'scope' => 'Applies a model scope.',
            'callback' => 'Applies a custom callback filter.',
            'trashed' => 'Filters soft-deleted records.',
            'operator' => 'Operator filter.',
            'custom' => 'Custom filter class.',
            default => '',
        };

        return trim($base.($base !== '' && $kindNote !== '' ? ' ' : '').$kindNote);
    }

    /**
     * @return array<string, array<int, scalar>>
     */
    protected function resolveEnumCasts(string $modelClass): array
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        try {
            $instance = (new ReflectionClass($modelClass))->newInstanceWithoutConstructor();
            if (! $instance instanceof Model) {
                return [];
            }
            $casts = $instance->getCasts();
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($casts as $column => $castTarget) {
            if (! is_string($castTarget) || ! enum_exists($castTarget)) {
                continue;
            }

            try {
                $reflection = new ReflectionEnum($castTarget);
            } catch (\ReflectionException) {
                continue;
            }

            if (! $reflection->isBacked()) {
                continue;
            }

            $result[$column] = array_map(
                fn (\BackedEnum $case) => $case->value,
                $castTarget::cases(),
            );
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, array<int, string>>
     */
    protected function groupFieldsByResource(array $fields): array
    {
        $groups = [];
        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                [$resource, $rest] = explode('.', $field, 2);
                $groups[$resource][] = $rest;
            } else {
                $groups['_root'][] = $field;
            }
        }

        return $groups;
    }
}
