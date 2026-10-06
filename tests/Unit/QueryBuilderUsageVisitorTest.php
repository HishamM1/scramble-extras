<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Unit;

use PawelJadanowski\ScrambleExtras\QueryBuilderUsageVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryBuilderUsageVisitorTest extends TestCase
{
    private function visit(string $body): QueryBuilderUsageVisitor
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse("<?php\n".$body);

        $visitor = new QueryBuilderUsageVisitor(null);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    #[Test]
    public function detects_query_builder_for_and_model(): void
    {
        $visitor = $this->visit('$q = QueryBuilder::for(User::class);');

        $this->assertTrue($visitor->found);
        $this->assertSame('User', $visitor->modelClass);
    }

    #[Test]
    public function resolves_model_from_parent_constructor_forms(): void
    {
        $forms = [
            'Invoice::query()',
            'Invoice::with("tenant", "plan")',
            'Invoice::withTrashed()->where("a", 1)',
            'Invoice::query()->where("a", 1)->with("b")',
            'new Invoice',
        ];

        foreach ($forms as $form) {
            $visitor = $this->visit("parent::__construct($form, \$request);");

            $this->assertSame('Invoice', $visitor->modelClass, $form);
        }
    }

    #[Test]
    public function leaves_model_null_when_parent_constructor_subject_is_opaque(): void
    {
        $visitor = $this->visit('parent::__construct($family->students()->with("x"), $request);');

        $this->assertNull($visitor->modelClass);
    }

    #[Test]
    public function ignores_unrelated_code(): void
    {
        $visitor = $this->visit('$x = SomeOther::for(User::class)->allowedFilters(["name"]);');

        $this->assertFalse($visitor->found);
    }

    #[Test]
    public function extracts_string_and_allowed_filters(): void
    {
        $visitor = $this->visit(<<<'PHP'
        $q = QueryBuilder::for(User::class)
            ->allowedFilters([
                'name',
                AllowedFilter::exact('status'),
                AllowedFilter::scope('recent'),
            ]);
        PHP);

        $names = array_column($visitor->filters, 'name');
        $kinds = array_column($visitor->filters, 'kind');

        $this->assertSame(['name', 'status', 'recent'], $names);
        $this->assertSame(['partial', 'exact', 'scope'], $kinds);
    }

    #[Test]
    public function extracts_sorts_includes_fields_and_default_sort(): void
    {
        $visitor = $this->visit(<<<'PHP'
        $q = QueryBuilder::for(User::class)
            ->allowedSorts(['name', 'created_at'])
            ->allowedIncludes('posts', 'comments')
            ->allowedFields(['id', 'name', 'posts.title'])
            ->defaultSort('-created_at')
            ->jsonPaginate();
        PHP);

        $this->assertSame(['name', 'created_at'], $visitor->sorts);
        $this->assertSame(['posts', 'comments'], $visitor->includes);
        $this->assertSame(['id', 'name', 'posts.title'], $visitor->fields);
        $this->assertSame('-created_at', $visitor->defaultSort);
        $this->assertTrue($visitor->jsonPaginate);
    }

    #[Test]
    public function reads_doc_attributes_on_filter_entry(): void
    {
        $visitor = $this->visit(<<<'PHP'
        $q = QueryBuilder::for(User::class)
            ->allowedFilters([
                /**
                 * Filter by city name.
                 * @example Berlin
                 * @format city
                 */
                AllowedFilter::partial('city'),
            ]);
        PHP);

        $this->assertCount(1, $visitor->filters);
        $filter = $visitor->filters[0];

        $this->assertSame('city', $filter['name']);
        $this->assertSame('Berlin', $filter['example'] ?? null);
        $this->assertSame('city', $filter['format'] ?? null);
        $this->assertSame('Filter by city name.', $filter['description'] ?? null);
    }
}
