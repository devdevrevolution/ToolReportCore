<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Services\FieldDiscoveryService;
use Toolreport\Core\Tests\TestCase;

class FieldDiscoveryServiceTest extends TestCase
{
    private FieldDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FieldDiscoveryService();
    }

    #[Test]
    public function it_discovers_flat_json_structure(): void
    {
        $data = ['name' => 'Alice', 'age' => 30];

        $fields = $this->service->discover($data, 'ds-1');

        $this->assertCount(2, $fields);

        $names = array_column($fields, 'name');
        $this->assertContains('Name', $names);
        $this->assertContains('Age', $names);

        $nameField = $this->findField($fields, 'name');
        $this->assertSame('string', $nameField['type']);
        $this->assertSame(0, $nameField['level']);
        $this->assertSame('ds-1', $nameField['datasourceId']);

        $ageField = $this->findField($fields, 'age');
        $this->assertSame('number', $ageField['type']);
        $this->assertSame(0, $ageField['level']);
    }

    #[Test]
    public function it_discovers_nested_objects(): void
    {
        $data = [
            'user' => [
                'profile' => ['age' => 30, 'active' => true],
                'name' => 'Bob',
            ],
        ];

        $fields = $this->service->discover($data, 'ds-1');

        $paths = array_column($fields, 'path');
        $this->assertContains('user.name', $paths);
        $this->assertContains('user.profile.age', $paths);
        $this->assertContains('user.profile.active', $paths);

        $ageField = $this->findField($fields, 'user.profile.age');
        $this->assertSame('number', $ageField['type']);
        $this->assertSame(2, $ageField['level']);
    }

    #[Test]
    public function it_handles_arrays_of_objects(): void
    {
        $data = [
            'orders' => [
                ['id' => 1, 'total' => 29.99, 'product' => 'Widget'],
                ['id' => 2, 'total' => 49.99],
            ],
        ];

        $fields = $this->service->discover($data, 'ds-1');

        $ordersField = $this->findField($fields, 'orders');
        $this->assertSame('array', $ordersField['type']);
        $this->assertSame(0, $ordersField['level']);

        $paths = array_column($fields, 'path');
        $this->assertContains('orders[].id', $paths);
        $this->assertContains('orders[].total', $paths);
        $this->assertContains('orders[].product', $paths);

        $idField = $this->findField($fields, 'orders[].id');
        $this->assertSame('number', $idField['type']);
        $this->assertSame(1, $idField['level']);
    }

    #[Test]
    public function it_handles_empty_arrays(): void
    {
        $data = ['tags' => []];

        $fields = $this->service->discover($data, 'ds-1');

        $this->assertCount(1, $fields);
        $this->assertSame('Tags', $fields[0]['name']);
        $this->assertSame('tags', $fields[0]['path']);
        $this->assertSame('array', $fields[0]['type']);
    }

    #[Test]
    public function it_handles_primitive_arrays(): void
    {
        $data = ['scores' => [95, 87, 92]];

        $fields = $this->service->discover($data, 'ds-1');

        $this->assertCount(1, $fields);
        $this->assertSame('scores', $fields[0]['path']);
        $this->assertSame('array', $fields[0]['type']);
    }

    #[Test]
    public function it_respects_max_depth(): void
    {
        $data = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => 'too deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // maxDepth: 2 means levels 0, 1, 2 are included, level 3+ excluded
        $fields = $this->service->discover($data, 'ds-1', maxDepth: 2);

        $paths = array_column($fields, 'path');

        // Levels 0, 1, 2 should be included
        $this->assertContains('a', $paths);
        $this->assertContains('a.b', $paths);
        $this->assertContains('a.b.c', $paths);

        // Level 3+ should be excluded
        $this->assertNotContains('a.b.c.d', $paths);
        $this->assertNotContains('a.b.c.d.e', $paths);
        $this->assertNotContains('a.b.c.d.e.f', $paths);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_data(): void
    {
        $fields = $this->service->discover([], 'ds-1');

        $this->assertSame([], $fields);
    }

    #[Test]
    public function it_generates_human_readable_names(): void
    {
        $data = [
            'user_id' => 1,
            'api_endpoint_url' => 'https://example.com',
        ];

        $fields = $this->service->discover($data, 'ds-1');

        $names = [];
        foreach ($fields as $field) {
            $names[$field['path']] = $field['name'];
        }

        $this->assertSame('User Id', $names['user_id']);
        $this->assertSame('Api Endpoint Url', $names['api_endpoint_url']);
    }

    #[Test]
    public function it_detects_mixed_types(): void
    {
        $data = [
            'name' => 'Alice',
            'age' => 30,
            'active' => true,
            'notes' => null,
        ];

        $fields = $this->service->discover($data, 'ds-1');

        $this->assertSame('string', $this->getFieldType($fields, 'name'));
        $this->assertSame('number', $this->getFieldType($fields, 'age'));
        $this->assertSame('boolean', $this->getFieldType($fields, 'active'));
        $this->assertSame('null', $this->getFieldType($fields, 'notes'));
    }

    #[Test]
    public function it_handles_root_level_array_of_objects(): void
    {
        $data = [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ];

        $fields = $this->service->discover($data, 'ds-1');

        // At root level, an array of objects — we should still discover from first element
        $this->assertNotEmpty($fields);

        $paths = array_column($fields, 'path');
        $this->assertContains('[].id', $paths);
        $this->assertContains('[].title', $paths);
    }

    private function findField(array $fields, string $path): ?array
    {
        foreach ($fields as $field) {
            if ($field['path'] === $path) {
                return $field;
            }
        }

        return null;
    }

    private function getFieldType(array $fields, string $path): ?string
    {
        $field = $this->findField($fields, $path);

        return $field['type'] ?? null;
    }
}
