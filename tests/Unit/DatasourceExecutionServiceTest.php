<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Services\DatasourceExecutionService;
use Toolreport\Core\Tests\TestCase;

class DatasourceExecutionServiceTest extends TestCase
{
    private DatasourceExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DatasourceExecutionService();
    }

    #[Test]
    public function it_returns_empty_array_for_no_datasources()
    {
        $result = $this->service->execute([]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_executes_a_single_datasource_and_returns_data()
    {
        Http::fake([
            'https://api.example.com/user' => Http::response([
                'name' => 'John',
                'email' => 'john@test.com',
            ]),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/user',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('John', $result['name']);
        $this->assertSame('john@test.com', $result['email']);
    }

    #[Test]
    public function it_navigates_to_collection_path_and_uses_first_item()
    {
        Http::fake([
            'https://api.example.com/report' => Http::response([
                'users' => [
                    ['name' => 'Alice', 'role' => 'Admin'],
                    ['name' => 'Bob', 'role' => 'User'],
                ],
            ]),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/report',
                'method' => 'GET',
                'collectionPath' => 'users',
            ],
        ]);

        $this->assertSame('Alice', $result['name']);
        $this->assertSame('Admin', $result['role']);
    }

    #[Test]
    public function it_merges_data_from_multiple_datasources()
    {
        Http::fake([
            'https://api.example.com/user' => Http::response([
                'name' => 'John',
                'email' => 'john@test.com',
            ]),
            'https://api.example.com/company' => Http::response([
                'company' => 'Acme Inc',
                'role' => 'Developer',
            ]),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/user',
                'method' => 'GET',
            ],
            [
                'id' => 'ds-2',
                'url' => 'https://api.example.com/company',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('John', $result['name']);
        $this->assertSame('john@test.com', $result['email']);
        $this->assertSame('Acme Inc', $result['company']);
        $this->assertSame('Developer', $result['role']);
    }

    #[Test]
    public function it_handles_root_level_indexed_array()
    {
        Http::fake([
            'https://api.example.com/items' => Http::response([
                ['title' => 'Item 1', 'price' => 100],
                ['title' => 'Item 2', 'price' => 200],
            ]),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/items',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('Item 1', $result['title']);
        $this->assertSame(100, $result['price']);
    }

    #[Test]
    public function it_skips_failed_requests_gracefully()
    {
        Http::fake([
            'https://api.example.com/ok' => Http::response(['name' => 'John']),
            'https://api.example.com/fail' => Http::response('Server Error', 500),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-ok',
                'url' => 'https://api.example.com/ok',
                'method' => 'GET',
            ],
            [
                'id' => 'ds-fail',
                'url' => 'https://api.example.com/fail',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('John', $result['name']);
    }

    #[Test]
    public function it_handles_bearer_auth()
    {
        Http::fake(function ($request) {
            $this->assertStringContainsString('Bearer secret-token', $request->header('Authorization')[0]);

            return Http::response(['name' => 'John']);
        });

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/user',
                'method' => 'GET',
                'auth' => ['type' => 'bearer', 'token' => 'secret-token'],
            ],
        ]);

        $this->assertSame('John', $result['name']);
    }

    #[Test]
    public function it_applies_custom_headers()
    {
        Http::fake(function ($request) {
            $this->assertSame('CustomValue', $request->header('X-Custom')[0]);

            return Http::response(['ok' => true]);
        });

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/data',
                'method' => 'GET',
                'headers' => ['X-Custom' => 'CustomValue'],
            ],
        ]);

        $this->assertTrue($result['ok']);
    }

    #[Test]
    public function it_navigates_nested_collection_path()
    {
        Http::fake([
            'https://api.example.com/nested' => Http::response([
                'data' => [
                    'users' => [
                        ['name' => 'Deep', 'role' => 'Explorer'],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/nested',
                'method' => 'GET',
                'collectionPath' => 'data.users',
            ],
        ]);

        $this->assertSame('Deep', $result['name']);
        $this->assertSame('Explorer', $result['role']);
    }

    // ========================================================================
    // executeForRendering() tests
    // ========================================================================

    #[Test]
    public function executeForRendering_preserves_collection_as_array()
    {
        Http::fake([
            'https://api.example.com/orders' => Http::response([
                'orders' => [
                    ['id' => 1, 'total' => 29.99],
                    ['id' => 2, 'total' => 49.99],
                ],
                'client' => ['name' => 'Acme Corp'],
            ]),
        ]);

        // Without collectionPath, the entire response is merged as-is
        // preserving collections and nested objects for the LayoutEngine to resolve
        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/orders',
                'method' => 'GET',
            ],
        ]);

        // Collection array must remain intact, not flattened to first item
        $this->assertIsArray($result['orders']);
        $this->assertCount(2, $result['orders']);
        $this->assertSame(1, $result['orders'][0]['id']);
        $this->assertSame(29.99, $result['orders'][0]['total']);
        $this->assertSame(2, $result['orders'][1]['id']);
        // Nested object must be preserved
        $this->assertSame('Acme Corp', $result['client']['name']);
    }

    #[Test]
    public function executeForRendering_preserves_collection_without_collectionPath()
    {
        Http::fake([
            'https://api.example.com/orders' => Http::response([
                'orders' => [
                    ['id' => 1, 'total' => 29.99],
                    ['id' => 2, 'total' => 49.99],
                ],
                'client' => ['name' => 'Acme Corp'],
            ]),
        ]);

        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/orders',
                'method' => 'GET',
            ],
        ]);

        // Without collectionPath, entire response is merged as-is
        // Arrays and nested objects must stay intact
        $this->assertIsArray($result['orders']);
        $this->assertCount(2, $result['orders']);
        $this->assertSame('Acme Corp', $result['client']['name']);
    }

    #[Test]
    public function executeForRendering_preserves_nested_objects_intact()
    {
        Http::fake([
            'https://api.example.com/data' => Http::response([
                'categories' => [
                    [
                        'name' => 'Electronics',
                        'products' => [['sku' => 'E1'], ['sku' => 'E2']],
                    ],
                    [
                        'name' => 'Books',
                        'products' => [['sku' => 'B1']],
                    ],
                ],
                'meta' => ['total' => 3],
            ]),
        ]);

        // Without collectionPath, the entire response is preserved
        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/data',
                'method' => 'GET',
            ],
        ]);

        // Nested arrays inside collection items must be preserved
        $this->assertIsArray($result['categories']);
        $this->assertCount(2, $result['categories']);
        $this->assertSame('Electronics', $result['categories'][0]['name']);
        $this->assertIsArray($result['categories'][0]['products']);
        $this->assertCount(2, $result['categories'][0]['products']);
        // meta object preserved
        $this->assertSame(3, $result['meta']['total']);
    }

    #[Test]
    public function executeForRendering_multiple_datasources_merge_correctly()
    {
        Http::fake([
            'https://api.example.com/orders' => Http::response([
                'orders' => [['id' => 1], ['id' => 2]],
            ]),
            'https://api.example.com/settings' => Http::response([
                'settings' => ['currency' => 'USD'],
            ]),
        ]);

        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/orders',
                'method' => 'GET',
            ],
            [
                'id' => 'ds-2',
                'url' => 'https://api.example.com/settings',
                'method' => 'GET',
            ],
        ]);

        $this->assertIsArray($result['orders']);
        $this->assertCount(2, $result['orders']);
        $this->assertSame('USD', $result['settings']['currency']);
    }

    #[Test]
    public function executeForRendering_null_response_skips_datasource()
    {
        Http::fake([
            'https://api.example.com/fail' => Http::response('Error', 500),
            'https://api.example.com/ok' => Http::response(['name' => 'Alice']),
        ]);

        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-fail',
                'url' => 'https://api.example.com/fail',
                'method' => 'GET',
            ],
            [
                'id' => 'ds-ok',
                'url' => 'https://api.example.com/ok',
                'method' => 'GET',
            ],
        ]);

        // Failed datasource is skipped; successful one merges as-is
        $this->assertSame('Alice', $result['name']);
    }

    #[Test]
    public function executeForRendering_empty_url_returns_empty()
    {
        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-empty',
                'url' => '',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function executeForRendering_single_item_flat_response_unchanged()
    {
        Http::fake([
            'https://api.example.com/user' => Http::response([
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ]),
        ]);

        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/user',
                'method' => 'GET',
            ],
        ]);

        // Flat single-object response should be unchanged
        $this->assertSame(1, $result['id']);
        $this->assertSame('Alice', $result['name']);
        $this->assertSame('alice@example.com', $result['email']);
    }

    #[Test]
    public function executeForRendering_key_conflict_last_wins()
    {
        Http::fake([
            'https://api.example.com/a' => Http::response([
                'items' => [['id' => 1]],
                'source' => 'A',
            ]),
            'https://api.example.com/b' => Http::response([
                'items' => [['id' => 2]],
                'source' => 'B',
            ]),
        ]);

        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-a',
                'url' => 'https://api.example.com/a',
                'method' => 'GET',
            ],
            [
                'id' => 'ds-b',
                'url' => 'https://api.example.com/b',
                'method' => 'GET',
            ],
        ]);

        // Last datasource wins for conflicting keys
        $this->assertSame('B', $result['source']);
        $this->assertIsArray($result['items']);
        $this->assertSame(2, $result['items'][0]['id']);
    }

    #[Test]
    public function executeForRendering_collectionPath_preserves_full_structure()
    {
        Http::fake([
            'https://api.example.com/nested' => Http::response([
                'data' => [
                    'orders' => [
                        ['id' => 10, 'total' => 99.99],
                        ['id' => 20, 'total' => 199.99],
                    ],
                ],
                'meta' => ['count' => 2],
            ]),
        ]);

        // With collectionPath, the full response is still merged to preserve all keys
        // for variable interpolation. The collectionPath is used by resolveCollection()
        // at render time to navigate to the iteration data.
        $result = $this->service->executeForRendering([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/nested',
                'method' => 'GET',
                'collectionPath' => 'data',
            ],
        ]);

        // Full response is preserved — navigate via data.orders
        $this->assertIsArray($result['data']['orders']);
        $this->assertCount(2, $result['data']['orders']);
        $this->assertSame(10, $result['data']['orders'][0]['id']);
        // 'meta' is also preserved for interpolation
        $this->assertSame(2, $result['meta']['count']);
    }

    #[Test]
    public function execute_method_still_flattens_collections()
    {
        // Verify that the original execute() method still works as before
        // (backward compatibility) — it flattens collections to first item
        Http::fake([
            'https://api.example.com/orders' => Http::response([
                'orders' => [
                    ['id' => 1, 'total' => 29.99],
                    ['id' => 2, 'total' => 49.99],
                ],
                'client' => ['name' => 'Acme Corp'],
            ]),
        ]);

        $result = $this->service->execute([
            [
                'id' => 'ds-1',
                'url' => 'https://api.example.com/orders',
                'method' => 'GET',
                'collectionPath' => 'orders',
            ],
        ]);

        // execute() still flattens to first item
        $this->assertSame(1, $result['id']);
        $this->assertSame(29.99, $result['total']);
        $this->assertArrayNotHasKey('orders', $result);
    }
}
