<?php

namespace PawelJadanowski\ScrambleExtras\Tests\Feature;

use Dedoc\Scramble\Generator;
use Illuminate\Routing\Router;
use PawelJadanowski\ScrambleExtras\Tests\Fixtures\UserController;
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
    }

    /**
     * @return array<string, mixed>
     */
    private function generate(): array
    {
        $result = app(Generator::class)();

        return is_array($result) ? $result : $result->toArray();
    }

    public function test_data_class_becomes_a_component_schema(): void
    {
        $openApi = $this->generate();

        $this->assertArrayHasKey('UserData', $openApi['components']['schemas'] ?? []);

        $schema = $openApi['components']['schemas']['UserData'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertSame('email', $schema['properties']['email']['format'] ?? null);
    }

    public function test_hidden_property_is_absent_and_computed_present_in_output(): void
    {
        $schema = $this->generate()['components']['schemas']['UserData'];

        $this->assertArrayNotHasKey('internalToken', $schema['properties']);
        $this->assertArrayHasKey('fullName', $schema['properties']);
    }

    public function test_status_enum_property_emits_enum_values(): void
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

    public function test_data_collection_of_renders_array_items(): void
    {
        $schema = $this->generate()['components']['schemas']['UserData'];
        $addresses = $schema['properties']['addresses'];

        $this->assertSame('array', $addresses['type']);
        $this->assertArrayHasKey('items', $addresses);
    }

    public function test_request_body_uses_inlined_input_schema(): void
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

    public function test_paginated_collection_response_envelope(): void
    {
        $openApi = $this->generate();

        $response = $openApi['paths']['/users']['get']['responses'][200] ?? null;
        $this->assertNotNull($response);

        $schema = $response['content']['application/json']['schema'] ?? [];
        $properties = $schema['properties'] ?? [];

        $this->assertArrayHasKey('data', $properties);
        $this->assertArrayHasKey('meta', $properties);
        $this->assertArrayHasKey('links', $properties);
    }

    public function test_query_builder_parameters_are_documented(): void
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

    public function test_exact_enum_filter_emits_enum_schema(): void
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
}
