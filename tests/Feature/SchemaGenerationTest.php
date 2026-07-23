<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Feature;

use Dedoc\Scramble\Generator;
use Illuminate\Routing\Router;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserController;
use PHPUnit\Framework\Attributes\Test;
use PawelJadanowski\ScrambleExtras\Tests\TestCase;

class SchemaGenerationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('api/users/{id}', [UserController::class, 'show']);
        $router->post('api/users', [UserController::class, 'store']);
        $router->get('api/users', [UserController::class, 'index']);
        $router->get('api/users/search', [UserController::class, 'search']);
        $router->get('api/users-generic-docblock', [UserController::class, 'indexWithGenericDocblock']);
        $router->get('api/users-generic-docblock-plain', [UserController::class, 'listWithGenericDocblock']);
        $router->match(['put', 'patch'], 'api/users/{id}', [UserController::class, 'update']);
    }

    /**
     * @return array<string, mixed>
     */
    private function generate(): array
    {
        $result = app(Generator::class)();

        return is_array($result) ? $result : $result->toArray();
    }

    #[Test]
    public function data_class_becomes_a_component_schema(): void
    {
        $openApi = $this->generate();

        $this->assertArrayHasKey('UserData', $openApi['components']['schemas'] ?? []);

        $schema = $openApi['components']['schemas']['UserData'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertSame('email', $schema['properties']['email']['format'] ?? null);
    }

    #[Test]
    public function hidden_property_is_absent_and_computed_present_in_output(): void
    {
        $schema = $this->generate()['components']['schemas']['UserData'];

        $this->assertArrayNotHasKey('internalToken', $schema['properties']);
        $this->assertArrayHasKey('fullName', $schema['properties']);
    }

    #[Test]
    public function status_enum_property_emits_enum_values(): void
    {
        $schema = $this->generate()['components']['schemas']['UserData'];
        $status = $schema['properties']['status'];

        // Either inlined enum or a $ref to the StatusEnum component.
        if (isset($status['$ref'])) {
            $this->assertStringContainsString('StatusEnum', $status['$ref']);
        } else {
            $this->assertSame(['active', 'inactive', 'pending'], $status['enum'] ?? null);
        }
    }

    #[Test]
    public function data_collection_of_renders_array_items(): void
    {
        $schema = $this->generate()['components']['schemas']['UserData'];
        $addresses = $schema['properties']['addresses'];

        $this->assertSame('array', $addresses['type']);
        $this->assertArrayHasKey('items', $addresses);
    }

    #[Test]
    public function request_body_uses_inlined_input_schema(): void
    {
        $openApi = $this->generate();

        $requestBody = $openApi['paths']['/users']['post']['requestBody'] ?? null;
        $this->assertNotNull($requestBody, 'POST /users should declare a request body');

        $schema = $requestBody['content']['application/json']['schema'] ?? [];

        // Inlined input schema (not a $ref to the output component), so it can
        // faithfully reflect input-only rules.
        $this->assertArrayHasKey('properties', $schema);
        $properties = $schema['properties'];

        $this->assertArrayHasKey('email', $properties);
        $this->assertSame(8, $properties['password']['minLength'] ?? null);

        // #[Hidden] is allowed on input, excluded from output.
        $this->assertArrayHasKey('internalToken', $properties);
        // #[Computed] is output-only, must not appear on the request body.
        $this->assertArrayNotHasKey('fullName', $properties);
    }

    #[Test]
    public function paginated_collection_response_envelope(): void
    {
        $openApi = $this->generate();

        $response = $openApi['paths']['/users']['get']['responses'][200] ?? null;
        $this->assertNotNull($response);

        $schema = $response['content']['application/json']['schema'] ?? [];
        $properties = $schema['properties'] ?? [];

        $this->assertArrayHasKey('data', $properties);
        $this->assertArrayHasKey('meta', $properties);
        $this->assertArrayHasKey('links', $properties);
        $this->assertItemsResolveToUserData($properties['data']['items'] ?? null, $openApi);
    }

    /**
     * Regression test: a controller action can declare a spec-correct,
     * two-argument generic docblock — `PaginatedDataCollection<int, UserData>`,
     * matching spatie/laravel-data's own `<TKey of array-key, TValue>`
     * template declaration — instead of relying on flow inference from the
     * method body. The item type must still be read from the *value* template
     * parameter (index 1), not always index 0 (which would be `int`, the key
     * type, and silently degrade the item schema to an empty object).
     */
    #[Test]
    public function paginated_collection_with_explicit_two_argument_generic_docblock(): void
    {
        $openApi = $this->generate();

        $response = $openApi['paths']['/users-generic-docblock']['get']['responses'][200] ?? null;
        $this->assertNotNull($response);

        $schema = $response['content']['application/json']['schema'] ?? [];
        $properties = $schema['properties'] ?? [];

        $this->assertArrayHasKey('data', $properties);
        $this->assertItemsResolveToUserData($properties['data']['items'] ?? null, $openApi);
    }

    /**
     * Same regression coverage as above, for the plain (non-paginated)
     * DataCollection wrapper.
     */
    #[Test]
    public function plain_collection_with_explicit_two_argument_generic_docblock(): void
    {
        $openApi = $this->generate();

        $response = $openApi['paths']['/users-generic-docblock-plain']['get']['responses'][200] ?? null;
        $this->assertNotNull($response);

        $schema = $response['content']['application/json']['schema'] ?? [];

        $this->assertSame('array', $schema['type'] ?? null);
        $this->assertItemsResolveToUserData($schema['items'] ?? null, $openApi);
    }

    /**
     * @param  array<string, mixed>|null  $items
     * @param  array<string, mixed>  $openApi
     */
    private function assertItemsResolveToUserData(?array $items, array $openApi): void
    {
        $this->assertNotNull($items, 'Item schema is missing entirely.');

        if (isset($items['$ref'])) {
            $this->assertSame('#/components/schemas/UserData', $items['$ref']);

            return;
        }

        // Inlined item schema: must have UserData's real properties, not have
        // silently degraded to an empty `{type: object}` placeholder.
        $this->assertArrayHasKey('id', $items['properties'] ?? []);
        $this->assertArrayHasKey('email', $items['properties'] ?? []);
    }

    #[Test]
    public function query_builder_parameters_are_documented(): void
    {
        $openApi = $this->generate();

        $parameters = $openApi['paths']['/users/search']['get']['parameters'] ?? [];
        $names = array_column($parameters, 'name');

        $this->assertContains('filter[name]', $names);
        $this->assertContains('filter[status]', $names);
        $this->assertContains('sort', $names);
        $this->assertContains('include', $names);
        $this->assertContains('page[number]', $names);
        $this->assertContains('page[size]', $names);
    }

    #[Test]
    public function exact_enum_filter_emits_enum_schema(): void
    {
        $openApi = $this->generate();

        $parameters = $openApi['paths']['/users/search']['get']['parameters'] ?? [];

        $statusParam = null;
        foreach ($parameters as $parameter) {
            if (($parameter['name'] ?? null) === 'filter[status]') {
                $statusParam = $parameter;
                break;
            }
        }

        $this->assertNotNull($statusParam);
        $this->assertSame(
            ['active', 'inactive', 'pending'],
            $statusParam['schema']['enum'] ?? null,
        );
    }

    /**
     * Regression test: Scramble core's default operation-methods resolver
     * only documents the first HTTP method a route responds to. A route
     * registered for both PUT and PATCH (exactly what Route::apiResource()
     * does for its update action) must produce two separate operations, not
     * just `put`.
     */
    #[Test]
    public function put_and_patch_are_both_documented_as_separate_operations(): void
    {
        $openApi = $this->generate();

        $operations = $openApi['paths']['/users/{id}'] ?? [];

        $this->assertArrayHasKey('put', $operations, 'PUT operation is missing.');
        $this->assertArrayHasKey('patch', $operations, 'PATCH operation is missing - the route-methods resolver is only documenting the first method again.');
    }

    /**
     * Regression test: a controller action typed with a spatie/laravel-data
     * Data class is resolved and validated by Laravel exactly like a
     * FormRequest would be (throwing ValidationException on failure), but
     * Scramble core only checks for FormRequest when deciding whether to
     * document a 422 response.
     */
    #[Test]
    public function data_typed_action_documents_a_422_validation_response(): void
    {
        $openApi = $this->generate();

        $this->assertArrayHasKey('422', $openApi['paths']['/users']['post']['responses'] ?? []);
        $this->assertArrayHasKey('422', $openApi['paths']['/users/{id}']['put']['responses'] ?? []);
    }
}
