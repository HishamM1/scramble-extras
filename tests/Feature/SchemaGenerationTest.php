<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Feature;

use Dedoc\Scramble\Generator;
use Illuminate\Routing\Router;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\RequestAction;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UpdateUserAction;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserController;
use PHPUnit\Framework\Attributes\Test;
use PawelJadanowski\ScrambleExtras\Tests\TestCase;

class SchemaGenerationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->post('api/request-action', RequestAction::class);
        $router->get('api/sorted', [UserController::class, 'sorted']);
        $router->get('api/filtered', [UserController::class, 'filtered']);
        $router->post('api/ruled', [UserController::class, 'ruled']);
        $router->get('api/users/{id}', [UserController::class, 'show']);
        $router->post('api/users', [UserController::class, 'store']);
        $router->get('api/users', [UserController::class, 'index']);
        $router->get('api/users-custom-query', [UserController::class, 'customQuery']);
        $router->get('api/users/search', [UserController::class, 'search']);
        $router->get('api/users-generic-docblock', [UserController::class, 'indexWithGenericDocblock']);
        $router->get('api/users-generic-docblock-plain', [UserController::class, 'listWithGenericDocblock']);
        $router->match(['put', 'patch'], 'api/users/{id}', [UserController::class, 'update']);
        $router->put('api/actions/{id}', UpdateUserAction::class)->name('api.actions.update');
        $router->get('api/user-responses/{user}', [UserController::class, 'showResponse']);
        $router->post('api/user-responses', [UserController::class, 'created']);
        $router->get('api/user-responses-paged', [UserController::class, 'pagedResponse']);
        $router->get('api/user-responses-list', [UserController::class, 'listResponse']);
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
    public function rules_are_mapped_to_request_schema_constraints(): void
    {
        $schema = $this->generate()['paths']['/ruled']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertSame('#/components/schemas/RuledDataInput', $schema['$ref'] ?? null);
        $schema = $this->resolveRef($schema);
        $p = $schema['properties'];

        $this->assertSame(2, $p['name']['minLength']);
        $this->assertSame(50, $p['name']['maxLength']);
        $this->assertSame('email', $p['contact']['format']);
        $this->assertSame(1, $p['age']['minimum']);
        $this->assertSame(120, $p['age']['maximum']);
        $this->assertSame(['a', 'b'], $p['kind']['enum']);
        $this->assertNotEmpty($p['status']['enum'] ?? $p['status']['anyOf'] ?? []);
        $this->assertSame(3, $p['tags']['maxItems']);
        $this->assertSame(10, $p['tags']['items']['maxLength']);
        $this->assertSame(['phone'], array_values(array_intersect(['phone'], $p['family']['required'])));
        $this->assertSame('array', $p['family']['properties']['parents']['type']);
        $this->assertContains('name', $p['family']['properties']['parents']['items']['required']);
        $this->assertEqualsCanonicalizing(['number', 'string', 'null'], (array) $p['rate']['type']);
        foreach (['name', 'age', 'kind', 'status', 'family'] as $field) {
            $this->assertContains($field, $schema['required']);
        }
    }

    #[Test]
    public function multi_method_routes_get_method_suffixed_operation_ids(): void
    {
        $path = $this->generate()['paths']['/users/{id}'];
        $put = $path['put']['operationId'];
        $patch = $path['patch']['operationId'];

        $this->assertNotSame($put, $patch);
        $this->assertStringEndsWith('.put', $put);
        $this->assertStringEndsWith('.patch', $patch);
        $this->assertDoesNotMatchRegularExpression('/_\d+$/', $put.$patch);
    }

    #[Test]
    public function nested_rules_keep_reference_properties_intact(): void
    {
        $schema = $this->resolveRef($this->generate()['paths']['/ruled']['post']['requestBody']['content']['application/json']['schema']);
        $address = json_encode($schema['properties']['address'], JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('#/components/schemas/AddressData', $address);
        $this->assertArrayNotHasKey('properties', $schema['properties']['address']);
    }

    #[Test]
    public function in_rule_casts_values_to_the_property_type(): void
    {
        $schema = $this->resolveRef($this->generate()['paths']['/ruled']['post']['requestBody']['content']['application/json']['schema']);

        $this->assertSame([1, 2, 3], array_values(array_filter($schema['properties']['level']['enum'], fn ($v) => $v !== null)));
    }

    #[Test]
    public function required_rule_removes_null_from_the_type(): void
    {
        $schema = $this->resolveRef($this->generate()['paths']['/ruled']['post']['requestBody']['content']['application/json']['schema']);

        $this->assertSame('integer', $schema['properties']['depth']['type']);
        $this->assertContains('depth', $schema['required']);
    }

    #[Test]
    public function action_request_parameter_produces_no_body(): void
    {
        $operation = $this->generate()['paths']['/request-action']['post'];

        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    #[Test]
    public function data_parameter_on_get_route_becomes_query_parameters(): void
    {
        $operation = $this->generate()['paths']['/filtered']['get'];

        $this->assertArrayNotHasKey('requestBody', $operation);
        $parameters = collect($operation['parameters'])->keyBy('name');
        $this->assertSame(['query'], $parameters->pluck('in')->unique()->values()->all());
        $this->assertTrue($parameters['name']['required']);
        $this->assertSame(2, $parameters['name']['schema']['minLength']);
        $this->assertFalse($parameters['contact']['required'] ?? false);
    }

    private function resolveRef(array $schema): array
    {
        if (isset($schema['$ref'])) {
            $name = basename($schema['$ref']);

            return $this->generate()['components']['schemas'][$name];
        }

        return $schema;
    }

    #[Test]
    public function request_body_references_input_schema(): void
    {
        $openApi = $this->generate();

        $requestBody = $openApi['paths']['/users']['post']['requestBody'] ?? null;
        $this->assertNotNull($requestBody, 'POST /users should declare a request body');

        $schema = $requestBody['content']['application/json']['schema'] ?? [];
        $this->assertSame('#/components/schemas/UserDataInput', $schema['$ref'] ?? null);
        $schema = $this->resolveRef($schema);

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
        if (! method_exists(\Dedoc\Scramble\Scramble::configure(), 'resolveOperationMethodsUsing')) {
            $this->markTestSkipped('resolveOperationMethodsUsing() was added later than this package\'s dedoc/scramble floor (^0.13.0); RouteMethodsResolver is a no-op on older installs.');
        }

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

    #[Test]
    public function to_response_on_custom_static_constructor_resolves_to_data_schema(): void
    {
        $schema = $this->generate()['paths']['/user-responses/{user}']['get']['responses'][200]['content']['application/json']['schema'] ?? [];

        $this->assertSame('#/components/schemas/UserData', $schema['$ref'] ?? null);
    }

    #[Test]
    public function to_response_chain_with_set_status_code_documents_that_status(): void
    {
        $responses = $this->generate()['paths']['/user-responses']['post']['responses'] ?? [];

        $this->assertArrayHasKey('201', $responses);
        $this->assertSame('#/components/schemas/UserData', $responses[201]['content']['application/json']['schema']['$ref'] ?? null);
    }

    #[Test]
    public function to_response_on_paginated_collection_gives_envelope_with_typed_items(): void
    {
        $openApi = $this->generate();
        $schema = $openApi['paths']['/user-responses-paged']['get']['responses'][200]['content']['application/json']['schema'] ?? [];

        $this->assertArrayHasKey('data', $schema['properties'] ?? []);
        $this->assertArrayHasKey('meta', $schema['properties'] ?? []);
        $this->assertItemsResolveToUserData($schema['properties']['data']['items'] ?? null, $openApi);
    }

    #[Test]
    public function to_response_on_data_collection_gives_typed_items(): void
    {
        $openApi = $this->generate();
        $schema = $openApi['paths']['/user-responses-list']['get']['responses'][200]['content']['application/json']['schema'] ?? [];

        $this->assertItemsResolveToUserData($schema['properties']['data']['items'] ?? $schema['items'] ?? null, $openApi);
    }

    #[Test]
    public function laravel_actions_route_is_analysed_through_as_controller(): void
    {
        $openApi = $this->generate();

        $this->assertArrayNotHasKey('/actions/{arguments}', $openApi['paths']);
        $operation = $openApi['paths']['/actions/{id}']['put'] ?? null;
        $this->assertNotNull($operation);
        $this->assertSame('actions.update', $operation['operationId']);
        $this->assertSame('id', $operation['parameters'][0]['name'] ?? null);
        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertSame('#/components/schemas/UserData', $operation['responses'][200]['content']['application/json']['schema']['$ref'] ?? null);
    }

    #[Test]
    public function custom_query_builder_subclass_filters_and_sorts_are_documented(): void
    {
        $parameters = $this->generate()['paths']['/users-custom-query']['get']['parameters'] ?? [];
        $names = array_column($parameters, 'name');

        $this->assertContains('filter[status]', $names);
        $this->assertContains('filter[search]', $names);
        $this->assertContains('sort', $names);

        $sort = $parameters[array_search('sort', $names, true)];
        $this->assertStringContainsString('`age`', $sort['description']);
        $this->assertSame('name', $sort['schema']['default'] ?? null);
    }

    #[Test]
    public function query_builder_parameters_replace_duplicates(): void
    {
        $parameters = $this->generate()['paths']['/sorted']['get']['parameters'];
        $sorts = array_values(array_filter($parameters, fn ($p) => $p['name'] === 'sort' && $p['in'] === 'query'));

        $this->assertCount(1, $sorts);
        $this->assertSame('name', $sorts[0]['schema']['default'] ?? null);
    }

    #[Test]
    public function custom_query_filters_do_not_leak_into_other_methods(): void
    {
        $paths = $this->generate()['paths'];

        foreach ([$paths['/users']['post'], $paths['/users/{id}']['get']] as $operation) {
            $names = array_column($operation['parameters'] ?? [], 'name');
            $this->assertSame([], array_values(array_filter($names, fn ($n) => str_starts_with($n, 'filter[') || $n === 'sort')));
        }
    }
}
