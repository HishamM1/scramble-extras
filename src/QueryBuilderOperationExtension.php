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
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Spatie\QueryBuilder\QueryBuilder;
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

        $usages = $finder->found ? [$finder] : $this->customQueryUsages($routeInfo);

        foreach ($usages as $usage) {
            $params = $this->buildParameters($usage);

            if ($params !== []) {
                $operation->addParameters($params);
            }
        }
    }

    /**
     * @return QueryBuilderUsageVisitor[]
     */
    protected function customQueryUsages(RouteInfo $routeInfo): array
    {
        $file = $routeInfo->reflectionMethod()?->getDeclaringClass()->getFileName();
        if (! $file) {
            return [];
        }

        $usages = [];
        foreach ($this->customQueryClasses($file, $routeInfo->reflectionMethod()->getName()) as $queryClass) {
            $queryFile = (new ReflectionClass($queryClass))->getFileName();
            if (! $queryFile) {
                continue;
            }

            $constructor = $this->findConstructor($this->parse($queryFile));
            if ($constructor === null || $constructor->stmts === null) {
                continue;
            }

            $usage = new QueryBuilderUsageVisitor(FileNameResolver::createForFile($queryFile));
            $traverser = new NodeTraverser;
            $traverser->addVisitor($usage);
            $traverser->traverse($constructor->stmts);

            if ($usage->filters !== [] || $usage->sorts !== [] || $usage->includes !== [] || $usage->fields !== []) {
                $usage->found = true;
                $usages[] = $usage;
            }
        }

        return $usages;
    }

    /**
     * @return array<int, class-string<QueryBuilder>>
     */
    protected function customQueryClasses(string $file, string $methodName): array
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $nodes = $traverser->traverse($this->parse($file));

        $methods = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, ClassMethod::class) as $method) {
            $methods[$method->name->toString()] ??= $method;
        }

        $classes = [];
        $visited = [];
        $queue = [$methodName];

        while ($queue !== []) {
            $name = array_shift($queue);
            if (isset($visited[$name]) || ! isset($methods[$name])) {
                continue;
            }
            $visited[$name] = true;

            foreach ((new NodeFinder)->find($methods[$name]->stmts ?? [], fn (Node $node) => true) as $node) {
                if ($node instanceof New_ && $node->class instanceof Name) {
                    $class = $node->class->toString();
                    if (class_exists($class) && is_subclass_of($class, QueryBuilder::class)) {
                        $classes[$class] = true;
                    }
                } elseif (($node instanceof MethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier) {
                    $called = $node->name->toString();
                    $queue[] = $called === 'run' ? 'handle' : $called;
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * @return Node\Stmt[]
     */
    protected function parse(string $file): array
    {
        return (new ParserFactory)->createForHostVersion()->parse((string) file_get_contents($file)) ?? [];
    }

    /**
     * @param  Node[]  $nodes
     */
    protected function findConstructor(array $nodes): ?ClassMethod
    {
        return (new NodeFinder)->findFirst(
            $nodes,
            fn (Node $node) => $node instanceof ClassMethod && $node->name->toString() === '__construct',
        );
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
