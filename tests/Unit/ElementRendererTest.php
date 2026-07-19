<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;

use Toolreport\Core\Layout\LayoutEngine;
use Toolreport\Core\Layout\Renderers\BarcodeElementRenderer;
use Toolreport\Core\Layout\Renderers\ContainerElementRenderer;
use Toolreport\Core\Layout\Renderers\ImageElementRenderer;
use Toolreport\Core\Layout\Renderers\LineElementRenderer;
use Toolreport\Core\Layout\Renderers\PageNumberElementRenderer;
use Toolreport\Core\Layout\Renderers\RectangleElementRenderer;
use Toolreport\Core\Layout\Renderers\TableElementRenderer;
use Toolreport\Core\Layout\Renderers\TextElementRenderer;
use Toolreport\Core\Tests\TestCase;

class ElementRendererTest extends TestCase
{
    private array $defaultPage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultPage = [
            'width' => 210, 'height' => 297,
            'orientation' => 'portrait',
            'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
        ];
    }

    #[Test]
    public function text_renderer_returns_correct_type()
    {
        $renderer = new TextElementRenderer();
        $this->assertEquals('text', $renderer->type());
    }

    #[Test]
    public function text_renderer_applies_styles()
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 20, 'width' => 100, 'height' => 30,
            'content' => ['text' => 'Styled Text'],
            'styles' => [
                'fontSize' => 16,
                'fontWeight' => 'bold',
                'fontStyle' => 'italic',
                'color' => '#ff0000',
                'textAlign' => 'center',
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('Styled Text', $html);
        $this->assertStringContainsString('font-size: 16pt', $html);
        $this->assertStringContainsString('font-weight: bold', $html);
        $this->assertStringContainsString('font-style: italic', $html);
        $this->assertStringContainsString('color: #ff0000', $html);
        $this->assertStringContainsString('text-align: center', $html);
    }

    #[Test]
    public function text_renderer_applies_horizontal_alignment()
    {
        $renderer = new TextElementRenderer();

        foreach (['left', 'center', 'right', 'justify'] as $align) {
            $element = [
                'x' => 0, 'y' => 0, 'width' => 100, 'height' => 20,
                'content' => ['text' => 'Alignment'],
                'styles' => ['textAlign' => $align],
            ];

            $html = $renderer->render($element, [], $this->defaultPage);
            $this->assertStringContainsString("text-align: {$align}", $html);
        }
    }

    #[Test]
    public function text_renderer_applies_vertical_alignment_with_padding()
    {
        $renderer = new TextElementRenderer();

        // No padding: top-aligned (default behavior)
        $topEl = [
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Top'],
            'styles' => ['verticalAlign' => 'top'],
        ];
        $topHtml = $renderer->render($topEl, [], $this->defaultPage);
        $this->assertStringNotContainsString('padding', $topHtml);

        // Bottom-aligned uses auto-calculated padding-top
        $botEl = [
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Bottom'],
            'styles' => ['verticalAlign' => 'bottom'],
        ];
        $botHtml = $renderer->render($botEl, [], $this->defaultPage);
        $this->assertStringNotContainsString('padding', $botHtml);

        // User-specified padding is applied
        $paddedEl = [
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 30,
            'content' => ['text' => 'With Padding'],
            'styles' => ['padding' => ['top' => 5, 'right' => 2, 'bottom' => 3, 'left' => 2]],
        ];
        $paddedHtml = $renderer->render($paddedEl, [], $this->defaultPage);
        $this->assertStringContainsString('padding: 5mm 2mm 3mm 2mm', $paddedHtml);
    }

    #[Test]
    public function text_renderer_defaults_to_left_and_top()
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Default'],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('text-align: left', $html);
        $this->assertStringNotContainsString('display: table', $html);
        $this->assertStringNotContainsString('table-cell', $html);
    }

    #[Test]
    public function text_renderer_applies_background_color_when_set(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Hello'],
            'styles' => ['backgroundColor' => '#ff0000'],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('background-color: #ff0000', $html);
    }

    #[Test]
    public function text_renderer_does_not_add_background_when_not_set(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Hello'],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringNotContainsString('background-color', $html);
    }

    #[Test]
    public function text_renderer_applies_background_with_null_value(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Hello'],
            'styles' => ['backgroundColor' => null],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringNotContainsString('background-color', $html);
    }

    #[Test]
    public function text_renderer_applies_border_radius_when_set(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Rounded'],
            'styles' => ['borderRadius' => 4],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('border-radius: 4mm', $html);
        $this->assertStringContainsString('overflow: hidden', $html);
    }

    #[Test]
    public function text_renderer_does_not_add_border_radius_when_zero(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Square'],
            'styles' => ['borderRadius' => 0],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringNotContainsString('border-radius', $html);
        $this->assertStringNotContainsString('overflow: hidden', $html);
    }

    #[Test]
    public function image_renderer_returns_empty_for_no_src()
    {
        $renderer = new ImageElementRenderer();

        $element = [
            'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
            'content' => [],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertEmpty($html);
    }

    #[Test]
    public function image_renderer_applies_border_radius_overflow_when_set(): void
    {
        $renderer = new ImageElementRenderer();

        $element = [
            'x' => 10, 'y' => 20, 'width' => 50, 'height' => 50,
            'content' => ['imageUrl' => 'https://example.com/img.jpg'],
            'styles' => ['borderRadius' => 5],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('border-radius: 5mm', $html);
        $this->assertStringContainsString('overflow: hidden', $html);
    }

    #[Test]
    public function image_renderer_does_not_add_border_radius_when_zero(): void
    {
        $renderer = new ImageElementRenderer();

        $element = [
            'x' => 10, 'y' => 20, 'width' => 50, 'height' => 50,
            'content' => ['imageUrl' => 'https://example.com/img.jpg'],
            'styles' => ['borderRadius' => 0],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringNotContainsString('border-radius', $html);
        $this->assertStringNotContainsString('overflow: hidden', $html);
    }

    #[Test]
    public function line_renderer_uses_solid_style_by_default()
    {
        $renderer = new LineElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 1,
            'styles' => ['thickness' => 1, 'color' => '#000'],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('border-bottom: 1pt solid', $html);
    }

    #[Test]
    public function line_renderer_supports_dashed_style()
    {
        $renderer = new LineElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 1,
            'styles' => ['style' => 'dashed', 'thickness' => 0.5],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('dashed', $html);
    }

    #[Test]
    public function table_renderer_renders_header_and_rows()
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 20, 'y' => 50, 'width' => 170, 'height' => 80,
            'content' => [
                'columns' => [
                    ['header' => 'Name', 'key' => 'name'],
                    ['header' => 'Value', 'key' => 'value'],
                ],
                'rows' => [
                    ['name' => 'A', 'value' => '1'],
                    ['name' => 'B', 'value' => '2'],
                ],
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Value', $html);
        $this->assertStringContainsString('A', $html);
        $this->assertStringContainsString('B', $html);
        $this->assertStringContainsString('<table', $html);
    }

    #[Test]
    public function table_renderer_resolves_rows_from_variable(): void
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 160, 'height' => 80,
            'content' => [
                'columns' => [
                    ['key' => 'product', 'header' => 'Product'],
                    ['key' => 'price', 'header' => 'Price'],
                ],
                'variable' => 'data.items',
            ],
        ];

        $data = [
            'data' => [
                'items' => [
                    ['product' => 'Widget', 'price' => 100],
                    ['product' => 'Gadget', 'price' => 200],
                    ['product' => 'Doohickey', 'price' => 50],
                ],
            ],
        ];

        $html = $renderer->render($element, $data, $this->defaultPage);

        $this->assertStringContainsString('Product', $html);
        $this->assertStringContainsString('Price', $html);
        $this->assertStringContainsString('Widget', $html);
        $this->assertStringContainsString('Gadget', $html);
        $this->assertStringContainsString('Doohickey', $html);
        $this->assertStringContainsString('100', $html);
        $this->assertStringContainsString('200', $html);
        $this->assertStringContainsString('50', $html);
    }

    #[Test]
    public function table_renderer_falls_back_to_static_rows_when_variable_missing(): void
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 50,
            'content' => [
                'columns' => [
                    ['key' => 'name', 'header' => 'Name'],
                ],
                'variable' => 'nonexistent.path',
                'rows' => [
                    ['name' => 'Fallback Row'],
                ],
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('Fallback Row', $html);
    }

    #[Test]
    public function table_renderer_uses_local_data_first(): void
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 50,
            'content' => [
                'columns' => [
                    ['key' => 'label', 'header' => 'Label'],
                ],
                'variable' => 'items',
            ],
        ];

        $data = ['items' => [['label' => 'Global']]];
        $localData = ['items' => [['label' => 'Local']]];

        $html = $renderer->render($element, $data, $this->defaultPage, $localData);

        $this->assertStringContainsString('Local', $html);
        $this->assertStringNotContainsString('Global', $html);
    }

    #[Test]
    public function table_renderer_renders_placeholder_when_no_columns(): void
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 50,
            'content' => [
                'columns' => [],
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('Configure columns', $html);
    }

    #[Test]
    public function table_renderer_handles_legacy_header_format(): void
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 50,
            'content' => [
                'header' => ['Name', 'Value'],
                'columnWidths' => [50, 50],
                'rows' => [
                    ['name' => 'A', 'value' => '1'],
                ],
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Value', $html);
        $this->assertStringContainsString('A', $html);
    }

    #[Test]
    public function table_renderer_applies_per_column_alignment(): void
    {
        $renderer = new TableElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 50,
            'content' => [
                'columns' => [
                    ['key' => 'name', 'header' => 'Name', 'align' => 'left'],
                    ['key' => 'price', 'header' => 'Price', 'align' => 'right'],
                ],
                'rows' => [
                    ['name' => 'Foo', 'price' => 100],
                ],
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('text-align: left', $html);
        $this->assertStringContainsString('text-align: right', $html);
    }

    #[Test]
    public function text_renderer_uses_variable_when_no_placeholder_in_text()
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => [
                'text' => 'User Name',
                'variable' => 'name',
            ],
            'styles' => [],
        ];

        $html = $renderer->render($element, ['name' => 'John Doe'], $this->defaultPage);

        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('User Name', $html);
    }

    #[Test]
    public function text_renderer_prefers_inline_placeholder_over_variable()
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => [
                'text' => 'Hello {{ username }}',
                'variable' => 'ignored',
            ],
            'styles' => [],
        ];

        $html = $renderer->render($element, ['username' => 'Jane'], $this->defaultPage);

        $this->assertStringContainsString('Jane', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('ignored', $html);
    }

    #[Test]
    public function text_renderer_renders_static_text_when_no_variable()
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Static Text'],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('Static Text', $html);
    }

    #[Test]
    public function text_renderer_keeps_placeholder_when_data_missing_for_variable()
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => [
                'text' => 'Field Label',
                'variable' => 'missing.key',
            ],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('{{ missing.key }}', $html);
        $this->assertStringNotContainsString('Field Label', $html);
    }

    #[Test]
    public function barcode_renderer_handles_interpolation()
    {
        $renderer = new BarcodeElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 80, 'height' => 30,
            'content' => ['value' => 'SKU-{{ product.code }}', 'showLabel' => true],
            'styles' => [],
        ];

        $html = $renderer->render($element, ['product' => ['code' => 'ABC123']], $this->defaultPage);

        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    #[Test]
    public function text_renderer_resolves_local_data_first(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Hello {{name}}'],
            'styles' => [],
        ];

        $data = ['name' => 'Global Name'];
        $localData = ['name' => 'Local Name'];

        $html = $renderer->render($element, $data, $this->defaultPage, $localData);

        $this->assertStringContainsString('Local Name', $html);
        $this->assertStringNotContainsString('Global Name', $html);
    }

    #[Test]
    public function text_renderer_falls_back_to_global_data(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Company: {{company}}'],
            'styles' => [],
        ];

        $data = ['company' => 'Acme Corp'];
        $localData = ['name' => 'Local Name'];

        $html = $renderer->render($element, $data, $this->defaultPage, $localData);

        $this->assertStringContainsString('Acme Corp', $html);
    }

    #[Test]
    public function text_renderer_works_without_local_data(): void
    {
        $renderer = new TextElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20,
            'content' => ['text' => 'Hello {{name}}'],
            'styles' => [],
        ];

        $data = ['name' => 'Global Name'];

        // Calling without $localData (backward compatibility)
        $html = $renderer->render($element, $data, $this->defaultPage);

        $this->assertStringContainsString('Global Name', $html);
    }

    // ── Rectangle Renderer Tests ────────────────────

    #[Test]
    public function rectangle_renderer_returns_correct_type(): void
    {
        $renderer = new RectangleElementRenderer();
        $this->assertEquals('rectangle', $renderer->type());
    }

    #[Test]
    public function rectangle_renderer_renders_basic_rectangle(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 10, 'y' => 20, 'width' => 80, 'height' => 40,
            'content' => ['type' => 'rectangle'],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('left: 10mm', $html);
        $this->assertStringContainsString('top: 20mm', $html);
        $this->assertStringContainsString('width: 80mm', $html);
        $this->assertStringContainsString('height: 40mm', $html);
        $this->assertStringContainsString('pdf-element', $html);
    }

    #[Test]
    public function rectangle_renderer_applies_background_color(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 50,
            'content' => ['type' => 'rectangle'],
            'styles' => ['backgroundColor' => '#3b82f6'],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('background-color: #3b82f6', $html);
    }

    #[Test]
    public function rectangle_renderer_applies_border(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 5, 'y' => 5, 'width' => 60, 'height' => 30,
            'content' => ['type' => 'rectangle'],
            'styles' => [
                'border' => ['width' => 2, 'color' => '#ff0000', 'style' => 'dashed'],
            ],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('border: 2mm dashed #ff0000', $html);
    }

    #[Test]
    public function rectangle_renderer_applies_border_radius(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
            'content' => ['type' => 'rectangle'],
            'styles' => ['borderRadius' => 5],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        $this->assertStringContainsString('border-radius: 5mm', $html);
        $this->assertStringContainsString('overflow: hidden', $html);
    }

    #[Test]
    public function rectangle_renderer_renders_plain_div_without_content(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 40, 'height' => 20,
            'content' => ['type' => 'rectangle'],
            'styles' => [],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        // Should be an empty div — no text content
        $this->assertMatchesRegularExpression('/<div[^>]*><\/div>/', $html);
        $this->assertStringContainsString('font-size: 0', $html);
        $this->assertStringContainsString('line-height: 0', $html);
    }

    #[Test]
    public function rectangle_renderer_resolves_color_variable(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 60, 'height' => 30,
            'content' => ['type' => 'rectangle', 'colorVariable' => 'status.color'],
            'styles' => [],
        ];

        $data = ['status' => ['color' => '#ff0000']];

        $html = $renderer->render($element, $data, $this->defaultPage);

        $this->assertStringContainsString('background-color: #ff0000', $html);
    }

    #[Test]
    public function rectangle_renderer_color_variable_overrides_static_background(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 60, 'height' => 30,
            'content' => ['type' => 'rectangle', 'colorVariable' => 'status.color'],
            'styles' => ['backgroundColor' => '#cccccc'],
        ];

        $data = ['status' => ['color' => '#00ff00']];

        $html = $renderer->render($element, $data, $this->defaultPage);

        // Dynamic color should override static backgroundColor
        $this->assertStringContainsString('background-color: #00ff00', $html);
    }

    #[Test]
    public function rectangle_renderer_falls_back_to_static_color_when_variable_not_found(): void
    {
        $renderer = new RectangleElementRenderer();

        $element = [
            'x' => 10, 'y' => 10, 'width' => 60, 'height' => 30,
            'content' => ['type' => 'rectangle', 'colorVariable' => 'missing.field'],
            'styles' => ['backgroundColor' => '#cccccc'],
        ];

        $html = $renderer->render($element, [], $this->defaultPage);

        // Falls back to static backgroundColor when variable resolution fails
        $this->assertStringContainsString('background-color: #cccccc', $html);
    }

    #[Test]
    public function container_renderer_renders_child_table_with_fill_mode(): void
    {
        $engine = new LayoutEngine();
        $engine->registerRenderers([
            new TableElementRenderer(),
            new ContainerElementRenderer(),
        ]);

        $element = [
            'x' => 10, 'y' => 10, 'width' => 150, 'height' => 60,
            'type' => 'container',
            'content' => [
                'type' => 'container',
                'children' => [
                    [
                        'id' => 'tbl-1',
                        'type' => 'table',
                        'positionMode' => 'fill',
                        'x' => 0, 'y' => 0, 'width' => 150, 'height' => 60,
                        'rotation' => 0,
                        'styles' => [],
                        'visible' => true,
                        'locked' => false,
                        'content' => [
                            'type' => 'table',
                            'columns' => [
                                ['key' => 'name', 'header' => 'Name', 'width' => 100],
                                ['key' => 'qty', 'header' => 'Qty', 'width' => 50],
                            ],
                            'showHeader' => true,
                            'rows' => [
                                ['name' => ['text' => 'Widget'], 'qty' => ['text' => '3']],
                                ['name' => ['text' => 'Gadget'], 'qty' => ['text' => '7']],
                            ],
                            'merges' => [],
                        ],
                    ],
                ],
                'layout' => 'vertical',
                'gap' => 2,
                'padding' => 4,
            ],
            'styles' => [],
        ];

        $html = $engine->renderElement($element, [], $this->defaultPage);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('table-layout: fixed', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Widget', $html);
        $this->assertStringContainsString('Gadget', $html);
        $this->assertStringContainsString('width: 66.67%', $html);
        $this->assertStringContainsString('<div class="pdf-element"', $html);
    }
}
