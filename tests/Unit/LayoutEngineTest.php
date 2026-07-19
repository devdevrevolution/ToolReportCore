<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;

use Toolreport\Core\Exceptions\InvalidLayoutException;
use Toolreport\Core\Layout\LayoutEngine;
use Toolreport\Core\Layout\Renderers\BarcodeElementRenderer;
use Toolreport\Core\Layout\Renderers\ImageElementRenderer;
use Toolreport\Core\Layout\Renderers\LineElementRenderer;
use Toolreport\Core\Layout\Renderers\PageNumberElementRenderer;
use Toolreport\Core\Layout\Renderers\RectangleElementRenderer;
use Toolreport\Core\Layout\Renderers\TableElementRenderer;
use Toolreport\Core\Layout\Renderers\TextElementRenderer;
use Toolreport\Core\Tests\TestCase;

class LayoutEngineTest extends TestCase
{
    private LayoutEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new LayoutEngine();
        $this->engine->registerRenderers([
            new TextElementRenderer(),
            new ImageElementRenderer(),
            new TableElementRenderer(),
            new LineElementRenderer(),
            new RectangleElementRenderer(),
            new BarcodeElementRenderer(),
            new PageNumberElementRenderer(),
        ]);
    }

    #[Test]
    public function it_renders_a_simple_text_element()
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'elements' => [
                [
                    'type' => 'text',
                    'x' => 20, 'y' => 20, 'width' => 170, 'height' => 15,
                    'content' => ['text' => 'Hello World'],
                    'styles' => ['fontSize' => 14, 'bold' => true],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        $this->assertEquals(1, $result->elementCount);
        $this->assertEquals('a4', $result->paperSize);
        $this->assertEquals('portrait', $result->orientation);
        $this->assertStringContainsString('Hello World', $result->html);
        $this->assertStringContainsString('font-weight: bold', $result->html);
        $this->assertStringContainsString('font-size: 14pt', $result->html);
    }

    #[Test]
    public function it_interpolates_variables_in_text()
    {
        $config = [
            'page' => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            'elements' => [
                [
                    'type' => 'text',
                    'x' => 20, 'y' => 20, 'width' => 170, 'height' => 15,
                    'content' => ['text' => 'Hello {{ name }}, your invoice is {{ invoice.number }}'],
                    'styles' => [],
                ],
            ],
        ];

        $data = ['name' => 'John', 'invoice' => ['number' => 'INV-001']];
        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('John', $result->html);
        $this->assertStringContainsString('INV-001', $result->html);
        $this->assertStringNotContainsString('{{ name }}', $result->html);
    }

    #[Test]
    public function it_uses_variable_when_text_has_no_placeholders()
    {
        $config = [
            'page' => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            'elements' => [
                [
                    'type' => 'text',
                    'x' => 20, 'y' => 20, 'width' => 170, 'height' => 15,
                    'content' => [
                        'text' => 'User Name',
                        'variable' => 'user.name',
                    ],
                    'styles' => [],
                ],
            ],
        ];

        $data = ['user' => ['name' => 'Jane Doe']];
        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Jane Doe', $result->html);
        $this->assertStringNotContainsString('User Name', $result->html);
        $this->assertStringNotContainsString('{{', $result->html);
    }

    #[Test]
    public function it_throws_exception_for_missing_page()
    {
        $this->expectException(InvalidLayoutException::class);

        $this->engine->render([]);
    }

    #[Test]
    public function it_throws_exception_for_invalid_element_type()
    {
        $this->expectException(InvalidLayoutException::class);

        $config = [
            'page' => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'margins' => []],
            'elements' => [
                ['type' => 'invalid_type', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'content' => []],
            ],
        ];

        $this->engine->render($config);
    }

    #[Test]
    public function it_renders_multiple_elements()
    {
        $config = [
            'page' => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            'elements' => [
                ['type' => 'text', 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20, 'content' => ['text' => 'Title'], 'styles' => ['fontSize' => 18]],
                ['type' => 'line', 'x' => 10, 'y' => 35, 'width' => 180, 'height' => 1, 'styles' => ['color' => '#000']],
                ['type' => 'text', 'x' => 10, 'y' => 45, 'width' => 100, 'height' => 15, 'content' => ['text' => 'Body'], 'styles' => []],
            ],
        ];

        $result = $this->engine->render($config);

        $this->assertEquals(3, $result->elementCount);
        $this->assertStringContainsString('Title', $result->html);
        $this->assertStringContainsString('Body', $result->html);
    }

    #[Test]
    public function it_renders_table_with_header_and_rows()
    {
        $config = [
            'page' => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            'elements' => [
                [
                    'type' => 'table',
                    'x' => 20, 'y' => 50, 'width' => 170, 'height' => 80,
                    'content' => [
                        'columns' => [
                            ['header' => 'Item', 'key' => 'item'],
                            ['header' => 'Price', 'key' => 'price'],
                        ],
                        'rows' => [
                            ['item' => 'Product A', 'price' => '$10'],
                            ['item' => 'Product B', 'price' => '$20'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Product A', $result->html);
        $this->assertStringContainsString('Product B', $result->html);
    }

    #[Test]
    public function it_renders_table_with_variable_binding_through_layout_engine(): void
    {
        $config = [
            'page' => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            'elements' => [
                [
                    'type' => 'table',
                    'x' => 20, 'y' => 50, 'width' => 170, 'height' => 80,
                    'content' => [
                        'columns' => [
                            ['header' => 'Product', 'key' => 'product'],
                            ['header' => 'Price', 'key' => 'price'],
                        ],
                        'variable' => 'data.items',
                    ],
                ],
            ],
        ];

        $data = [
            'data' => [
                'items' => [
                    ['product' => 'Widget', 'price' => 100],
                    ['product' => 'Gadget', 'price' => 200],
                ],
            ],
        ];

        $result = $this->engine->render($config, $data);

        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Widget', $result->html);
        $this->assertStringContainsString('Gadget', $result->html);
        $this->assertStringContainsString('100', $result->html);
        $this->assertStringContainsString('200', $result->html);
        $this->assertStringContainsString('Product', $result->html);
        $this->assertStringContainsString('Price', $result->html);
        $this->assertStringContainsString('<table', $result->html);
    }

    #[Test]
    public function detail_band_table_with_variable_binding_resolves_from_detail_data(): void
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'height' => 50,
                        'collectionPath' => 'orders',
                        'elements' => [
                            [
                                'type' => 'table',
                                'x' => 10, 'y' => 5,
                                'width' => 180, 'height' => 40,
                                'content' => [
                                    'columns' => [
                                        ['header' => 'SKU', 'key' => 'sku'],
                                        ['header' => 'Qty', 'key' => 'qty'],
                                    ],
                                    'variable' => '[].items',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = [
            'orders' => [
                [
                    'orderId' => 1,
                    'items' => [
                        ['sku' => 'A-001', 'qty' => 2],
                        ['sku' => 'A-002', 'qty' => 1],
                    ],
                ],
                [
                    'orderId' => 2,
                    'items' => [
                        ['sku' => 'B-001', 'qty' => 5],
                    ],
                ],
            ],
        ];

        $result = $this->engine->render($config, $data);

        $this->assertEquals(2, $result->elementCount);
        $this->assertStringContainsString('A-001', $result->html);
        $this->assertStringContainsString('A-002', $result->html);
        $this->assertStringContainsString('B-001', $result->html);
        $this->assertStringContainsString('2', $result->html);
        $this->assertStringContainsString('5', $result->html);
    }

    #[Test]
    public function it_returns_registered_types()
    {
        $types = $this->engine->getRegisteredTypes();

        $this->assertContains('text', $types);
        $this->assertContains('image', $types);
        $this->assertContains('table', $types);
        $this->assertContains('line', $types);
        $this->assertContains('barcode', $types);
        $this->assertContains('page_number', $types);
    }

    #[Test]
    public function detail_band_with_null_collection_path_iterates_root_data()
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'label' => 'Detail',
                        'height' => 15,
                        'collectionPath' => null,
                        'datasourceId' => 'ds-1',
                        'elements' => [
                            [
                                'type' => 'text',
                                'x' => 10, 'y' => 5,
                                'width' => 80, 'height' => 10,
                                'content' => ['text' => '{{ cognome }}', 'variable' => 'cognome'],
                                'styles' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = [
            ['cognome' => 'Rossi', 'nome' => 'Mario'],
            ['cognome' => 'Bianchi', 'nome' => 'Luca'],
        ];

        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Rossi', $result->html);
        $this->assertStringContainsString('Bianchi', $result->html);
        $this->assertEquals(2, $result->elementCount);
    }

    #[Test]
    public function detail_band_with_empty_string_collection_path_iterates_root_data()
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'label' => 'Detail',
                        'height' => 15,
                        'collectionPath' => '',
                        'elements' => [
                            [
                                'type' => 'text',
                                'x' => 10, 'y' => 5,
                                'width' => 80, 'height' => 10,
                                'content' => ['text' => '{{ [].cognome }}'],
                                'styles' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = [
            ['cognome' => 'Rossi', 'nome' => 'Mario'],
            ['cognome' => 'Bianchi', 'nome' => 'Luca'],
        ];

        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Rossi', $result->html);
        $this->assertStringContainsString('Bianchi', $result->html);
    }

    #[Test]
    public function detail_band_with_named_collection_path_iterates_nested_data()
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'label' => 'Detail',
                        'height' => 15,
                        'collectionPath' => 'results',
                        'elements' => [
                            [
                                'type' => 'text',
                                'x' => 10, 'y' => 5,
                                'width' => 80, 'height' => 10,
                                'content' => ['text' => '{{ name }}', 'variable' => 'name'],
                                'styles' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = [
            'results' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
            'company' => 'Acme',
        ];

        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Alice', $result->html);
        $this->assertStringContainsString('Bob', $result->html);
    }

    #[Test]
    public function detail_band_with_bracket_notation_interpolation()
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'pageHeader',
                        'type' => 'pageHeader',
                        'anchor' => 'top',
                        'label' => 'Header',
                        'height' => 20,
                        'elements' => [
                            [
                                'type' => 'text',
                                'x' => 10, 'y' => 5,
                                'width' => 100, 'height' => 10,
                                'content' => ['text' => 'Company: {{ company }}', 'variable' => 'company'],
                                'styles' => [],
                            ],
                        ],
                    ],
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'label' => 'Detail',
                        'height' => 15,
                        'collectionPath' => '',
                        'elements' => [
                            [
                                'type' => 'text',
                                'x' => 10, 'y' => 5,
                                'width' => 80, 'height' => 10,
                                'content' => ['text' => '{{ [].cognome }}', 'variable' => 'cognome'],
                                'styles' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = [
            ['cognome' => 'Rossi'],
            ['cognome' => 'Bianchi'],
        ];

        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Rossi', $result->html);
        $this->assertStringContainsString('Bianchi', $result->html);
    }
}
