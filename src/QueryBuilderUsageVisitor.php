<?php

namespace PawelJadanowski\ScrambleExtras;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * AST visitor that collects spatie/laravel-query-builder configuration from a
 * controller method body: the model passed to `QueryBuilder::for()`, plus the
 * allowed filters/sorts/includes/fields, `defaultSort`, and `jsonPaginate`.
 *
 * Doc-comment attributes (`@example`, `@format`, free-text description) on an
 * individual filter entry are picked up and attached to that filter.
 */
class QueryBuilderUsageVisitor extends NodeVisitorAbstract
{
    public bool $found = false;

    public bool $jsonPaginate = false;

    public ?string $modelClass = null;

    public ?string $defaultSort = null;

    /** @var array<int, array{name: string, kind: string, description?: string, example?: mixed, format?: string}> */
    public array $filters = [];

    /** @var array<int, string> */
    public array $sorts = [];

    /** @var array<int, string> */
    public array $includes = [];

    /** @var array<int, string> */
    public array $fields = [];

    public function __construct(protected ?FileNameResolver $nameResolver) {}

    public function enterNode(Node $node)
    {
        if ($node instanceof StaticCall && $this->isQueryBuilderFor($node)) {
            $this->found = true;
            $this->modelClass = $this->extractClassConst($node->args[0]->value ?? null);
        }

        if (! $node instanceof MethodCall) {
            return null;
        }

        $methodName = $node->name instanceof Identifier ? $node->name->name : null;
        if ($methodName === null) {
            return null;
        }

        switch ($methodName) {
            case 'allowedFilters':
                $this->filters = array_merge($this->filters, $this->extractFilterEntries($node));
                break;
            case 'allowedSorts':
                $this->sorts = array_merge($this->sorts, $this->extractStringList($node));
                break;
            case 'allowedIncludes':
                $this->includes = array_merge($this->includes, $this->extractStringList($node));
                break;
            case 'allowedFields':
                $this->fields = array_merge($this->fields, $this->extractStringList($node));
                break;
            case 'defaultSort':
                $this->defaultSort = $this->extractFirstString($node);
                break;
            case 'jsonPaginate':
                $this->jsonPaginate = true;
                break;
        }

        return null;
    }

    protected function isQueryBuilderFor(StaticCall $node): bool
    {
        $method = $node->name instanceof Identifier ? $node->name->name : null;
        if ($method !== 'for') {
            return false;
        }

        $resolved = $this->resolveClassName($node->class);

        return $resolved === QueryBuilder::class
            || (class_exists($resolved) && is_a($resolved, QueryBuilder::class, true))
            || $resolved === 'QueryBuilder';
    }

    /**
     * @return array<int, array{name: string, kind: string, description?: string, example?: mixed, format?: string}>
     */
    protected function extractFilterEntries(MethodCall $node): array
    {
        $result = [];

        foreach ($node->args as $arg) {
            $expr = $arg->value;

            if ($expr instanceof Array_) {
                foreach ($expr->items as $item) {
                    if (! $item) {
                        continue;
                    }
                    $entry = $this->buildFilterEntry($item->value, $item);
                    if ($entry !== null) {
                        $result[] = $entry;
                    }
                }
            } else {
                $entry = $this->buildFilterEntry($expr, $arg);
                if ($entry !== null) {
                    $result[] = $entry;
                }
            }
        }

        return $result;
    }

    /**
     * @return array{name: string, kind: string, description?: string, example?: mixed, format?: string}|null
     */
    protected function buildFilterEntry(?Node $expr, Node $hostForDoc): ?array
    {
        while ($expr instanceof MethodCall) {
            $expr = $expr->var;
        }

        if ($expr instanceof String_) {
            return $this->withDocAttributes(['name' => $expr->value, 'kind' => 'partial'], $hostForDoc);
        }

        if ($expr instanceof StaticCall && $this->isAllowedFilter($expr)) {
            $kind = $expr->name instanceof Identifier ? $expr->name->name : null;
            $name = $this->extractFirstString($expr);

            if ($kind === null || $name === null) {
                return null;
            }

            return $this->withDocAttributes(['name' => $name, 'kind' => $kind], $hostForDoc);
        }

        return null;
    }

    /**
     * @param  array{name: string, kind: string}  $entry
     * @return array{name: string, kind: string, description?: string, example?: mixed, format?: string}
     */
    protected function withDocAttributes(array $entry, Node $host): array
    {
        $doc = $host->getDocComment();
        if ($doc !== null) {
            $text = $doc->getText();
            if (preg_match('/@example\s+([^\n*]+)/', $text, $m)) {
                $entry['example'] = trim($m[1]);
            }
            if (preg_match('/@format\s+([^\n*]+)/', $text, $m)) {
                $entry['format'] = trim($m[1]);
            }
            $description = $this->cleanDocText($text);
            if ($description !== '') {
                $entry['description'] = $description;
            }
        }

        return $entry;
    }

    protected function isAllowedFilter(StaticCall $node): bool
    {
        $resolved = $this->resolveClassName($node->class);

        return $resolved === AllowedFilter::class
            || (class_exists($resolved) && is_a($resolved, AllowedFilter::class, true))
            || $resolved === 'AllowedFilter';
    }

    /**
     * @param  StaticCall|MethodCall  $call
     */
    protected function extractFirstString(Node $call): ?string
    {
        $arg = $call->args[0] ?? null;
        if ($arg && $arg->value instanceof String_) {
            return $arg->value->value;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function extractStringList(MethodCall $node): array
    {
        $result = [];

        foreach ($node->args as $arg) {
            $expr = $arg->value;

            if ($expr instanceof Array_) {
                foreach ($expr->items as $item) {
                    $name = $item ? $this->stringOrFactoryName($item->value) : null;
                    if ($name !== null) {
                        $result[] = $name;
                    }
                }
            } elseif (($name = $this->stringOrFactoryName($expr)) !== null) {
                $result[] = $name;
            }
        }

        return $result;
    }

    protected function stringOrFactoryName(Node $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof StaticCall) {
            return $this->extractFirstString($expr);
        }

        return null;
    }

    protected function extractClassConst(?Node $node): ?string
    {
        if (! $node instanceof ClassConstFetch) {
            return null;
        }
        if (! ($node->name instanceof Identifier && $node->name->name === 'class')) {
            return null;
        }

        return $this->resolveClassName($node->class) ?: null;
    }

    protected function resolveClassName(Node|string|null $class): string
    {
        if ($class === null) {
            return '';
        }
        if (is_string($class)) {
            return ltrim($class, '\\');
        }
        if (! $class instanceof Node\Name) {
            return '';
        }

        $shortName = $class->toString();

        if ($this->nameResolver) {
            return ($this->nameResolver)($shortName);
        }

        return $shortName;
    }

    protected function cleanDocText(string $doc): string
    {
        $lines = preg_split('/\r?\n/', $doc) ?: [];
        $text = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t/*");
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }
            $text[] = $line;
        }

        return trim(implode(' ', $text));
    }
}
