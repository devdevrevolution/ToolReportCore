<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Engine;

use Com\Tecnick\Pdf\Graph\Draw;
use Com\Tecnick\Pdf\Page\Page;
use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Containers\HBox;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\Table;
use Toolreport\Core\Modules\PdfEngine\Containers\VBox;
use Toolreport\Core\Modules\PdfEngine\Engine\FontMetrics;
use Toolreport\Core\Modules\PdfEngine\Engine\ReportCompiler;
use Toolreport\Core\Modules\PdfEngine\Exceptions\UnknownComponentException;
use Toolreport\Core\Modules\PdfEngine\Primitives\Label;
use Toolreport\Core\Modules\PdfEngine\Primitives\Shape;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class ReportCompilerTest extends TestCase
{
    private ReportCompiler $report_compiler;

    protected function setUp(): void
    {
        $this->report_compiler = new ReportCompiler();
    }

    // ── buildContentTree: type mapping ──

    #[Test]
    public function buildContentTree_maps_VBox(): void
    {
        $node = [
            'type' => 'VBox',
            'children' => [],
        ];

        $component = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(VBox::class, $component);
    }

    #[Test]
    public function buildContentTree_maps_HBox(): void
    {
        $node = [
            'type' => 'HBox',
            'children' => [],
        ];

        $component = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(HBox::class, $component);
    }

    #[Test]
    public function buildContentTree_maps_Label(): void
    {
        $node = [
            'type' => 'Label',
            'text' => 'Hello',
        ];

        $component = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Label::class, $component);
    }

    #[Test]
    public function buildContentTree_maps_Shape(): void
    {
        $node = [
            'type' => 'Shape',
            'shapeType' => 'line',
        ];

        $component = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Shape::class, $component);
    }

    #[Test]
    public function buildContentTree_maps_Table(): void
    {
        $node = [
            'type' => 'Table',
            'columnWidths' => [100],
            'rows' => [
                [
                    ['type' => 'Label', 'text' => 'Cell'],
                ],
            ],
        ];

        $component = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Table::class, $component);
    }

    #[Test]
    public function buildContentTree_throws_for_unknown_type(): void
    {
        $node = [
            'type' => 'UnknownType',
        ];

        $this->expectException(UnknownComponentException::class);
        $this->expectExceptionMessage("Unknown component type: 'UnknownType'");

        $this->report_compiler->buildContentTree($node);
    }

    #[Test]
    public function buildContentTree_throws_for_empty_type(): void
    {
        $node = [
            'type' => '',
        ];

        $this->expectException(UnknownComponentException::class);
        $this->expectExceptionMessage("Unknown component type: ''");

        $this->report_compiler->buildContentTree($node);
    }

    // ── buildContentTree: Label configuration ──

    #[Test]
    public function buildContentTree_creates_label_with_text(): void
    {
        $node = [
            'type' => 'Label',
            'text' => 'Test Label',
        ];

        $label = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Label::class, $label);
        // We can't directly test the text since it's private,
        // but we can verify it's a valid Label instance
    }

    #[Test]
    public function buildContentTree_creates_label_with_font_config(): void
    {
        $node = [
            'type' => 'Label',
            'text' => 'Styled',
            'fontFamily' => 'times',
            'fontSize' => 14,
            'style' => 'B',
            'color' => '#FF0000',
            'width' => 100,
        ];

        $label = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Label::class, $label);
    }

    // ── buildContentTree: Shape configuration ──

    #[Test]
    public function buildContentTree_creates_shape_with_config(): void
    {
        $node = [
            'type' => 'Shape',
            'shapeType' => 'rect',
            'x1' => 10,
            'y1' => 20,
            'width' => 80,
            'height' => 40,
            'color' => '#0000FF',
            'fillColor' => '#EEEEEE',
            'strokeWidth' => 2,
            'lineStyle' => 'dashed',
        ];

        $shape = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Shape::class, $shape);
    }

    #[Test]
    public function buildContentTree_creates_shape_from_designer_w_h_keys(): void
    {
        $node = [
            'type' => 'Shape',
            'shapeType' => 'circle',
            'w' => 30,
            'h' => 30,
            'color' => '#0000FF',
        ];

        $shape = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Shape::class, $shape);
        $dims = $shape->getDimensions();
        $this->assertSame(30.0, $dims['w']);
        $this->assertSame(30.0, $dims['h']);
    }

    // ── buildContentTree: VBox with children ──

    #[Test]
    public function buildContentTree_creates_nested_boxes(): void
    {
        $node = [
            'type' => 'VBox',
            'padding' => 5,
            'children' => [
                [
                    'type' => 'Label',
                    'text' => 'Nested',
                ],
                [
                    'type' => 'HBox',
                    'children' => [
                        [
                            'type' => 'Label',
                            'text' => 'Inner',
                        ],
                    ],
                ],
            ],
        ];

        $vbox = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(VBox::class, $vbox);
    }

    // ── buildContentTree: Table with column widths ──

    #[Test]
    public function buildContentTree_creates_table_with_multiple_rows(): void
    {
        $node = [
            'type' => 'Table',
            'columnWidths' => [80, 120],
            'rows' => [
                [
                    ['type' => 'Label', 'text' => 'A1'],
                    ['type' => 'Label', 'text' => 'B1'],
                ],
                [
                    ['type' => 'Label', 'text' => 'A2'],
                    ['type' => 'Label', 'text' => 'B2'],
                ],
            ],
        ];

        $table = $this->report_compiler->buildContentTree($node);

        $this->assertInstanceOf(Table::class, $table);
    }

    // ── compile ──

    #[Test]
    public function compile_returns_pdf_binary(): void
    {
        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $pdf
            ->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf
            ->expects($this->once())
            ->method('getOutPDFString')
            ->willReturn('%PDF-1.7 mock');

        $graph
            ->expects($this->once())
            ->method('getLine')
            ->willReturn('line ');

        $page
            ->expects($this->once())
            ->method('addContent')
            ->with('q line  Q');

        $report_compiler = new ReportCompiler($pdf);

        $pdf_string = $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'header',
                        'content' => [
                            'type' => 'Shape',
                            'shapeType' => 'line',
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 20,
                            'y2' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringStartsWith('%PDF-', $pdf_string);
    }

    #[Test]
    public function compile_renders_detail_band_once_per_collection_item(): void
    {
        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $pdf
            ->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf
            ->expects($this->once())
            ->method('getOutPDFString')
            ->willReturn('%PDF-mock');

        $lines = [];
        $graph
            ->expects($this->exactly(2))
            ->method('getLine')
            ->willReturnCallback(function (float $x1, float $y1, float $x2, float $y2, array $style) use (&$lines): string {
                $lines[] = [$x1, $y1, $x2, $y2];

                return 'line ' . count($lines);
            });

        $page
            ->expects($this->exactly(2))
            ->method('addContent');

        $report_compiler = new ReportCompiler($pdf);

        $result = $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'content' => [
                            'type' => 'Shape',
                            'shapeType' => 'line',
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 10,
                            'y2' => 5,
                        ],
                    ],
                ],
            ],
        ], [
            'items' => [
                ['name' => 'First'],
                ['name' => 'Second'],
            ],
        ]);

        $this->assertSame('%PDF-mock', $result);
        $this->assertSame([
            [10.0, 10.0, 20.0, 15.0],
            [10.0, 15.0, 20.0, 20.0],
        ], $lines);
    }

    #[Test]
    public function compile_debug_renders_debug_text_label(): void
    {
        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        // FontMetrics needs $pdf->font and $pdf->pon
        $fontMock = $this->createMock(\Com\Tecnick\Pdf\Font\Stack::class);
        $fontMock->method('insert')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 5,
            'ascent' => 700,
            'descent' => -200,
        ]);
        $fontMock->method('getOrdArrWidth')->willReturn(30.0);
        $pdf->font = $fontMock;
        $pdf->pon = 1;

        $pdf
            ->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf
            ->expects($this->once())
            ->method('getOutPDFString')
            ->willReturn('%PDF-debug-mock');

        $pdf
            ->expects($this->once())
            ->method('addTextCell')
            ->with(
                txt: $this->callback(function (string $txt): bool {
                    return str_contains($txt, 'Shape');
                }),
            );

        $page
            ->expects($this->any())
            ->method('enableAutoPageBreak')
            ->with(false);

        $page
            ->expects($this->any())
            ->method('addContent')
            ->with($this->anything());

        $report_compiler = new ReportCompiler($pdf, null, true);

        $result = $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'content' => [
                            'type' => 'Shape',
                            'shapeType' => 'line',
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 10,
                            'y2' => 5,
                        ],
                    ],
                ],
            ],
        ], [
            'items' => [
                ['name' => 'First'],
            ],
        ]);

        $this->assertSame('%PDF-debug-mock', $result);
    }

    // ── classifyBands ──

    /**
     * Call the private classifyBands method via reflection.
     */
    private function callClassifyBands(array $all_bands): array
    {
        $method = new \ReflectionMethod(ReportCompiler::class, 'classifyBands');

        return $method->invoke($this->report_compiler, $all_bands);
    }

    #[Test]
    public function classifyBands_title_band_goes_to_title(): void
    {
        $bands = [
            ['type' => 'title', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertNotNull($result['title']);
        $this->assertSame('title', $result['title']['type']);
        $this->assertNull($result['detail']);
        $this->assertNull($result['summary']);
    }

    #[Test]
    public function classifyBands_detail_band_goes_to_detail(): void
    {
        $bands = [
            ['type' => 'detail', 'collectionPath' => 'items', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertNotNull($result['detail']);
        $this->assertSame('detail', $result['detail']['type']);
    }

    #[Test]
    public function classifyBands_summary_band_goes_to_summary(): void
    {
        $bands = [
            ['type' => 'summary', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertNotNull($result['summary']);
        $this->assertSame('summary', $result['summary']['type']);
    }

    #[Test]
    public function classifyBands_pageHeader_goes_to_topRepeating(): void
    {
        $bands = [
            ['type' => 'pageHeader', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertCount(1, $result['topRepeating']);
        $this->assertSame('pageHeader', $result['topRepeating'][0]['type']);
    }

    #[Test]
    public function classifyBands_columnHeader_goes_to_topRepeating(): void
    {
        $bands = [
            ['type' => 'columnHeader', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertCount(1, $result['topRepeating']);
        $this->assertSame('columnHeader', $result['topRepeating'][0]['type']);
    }

    #[Test]
    public function classifyBands_pageFooter_goes_to_pageFooter_field(): void
    {
        $bands = [
            ['type' => 'pageFooter', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertNull($result['pageFooter'] === null ? 'pageFooter should not be null' : null);
        $this->assertSame('pageFooter', $result['pageFooter']['type']);
        $this->assertEmpty($result['bottomRepeating']);
    }

    #[Test]
    public function classifyBands_columnFooter_goes_to_bottomRepeating(): void
    {
        $bands = [
            ['type' => 'columnFooter', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertCount(1, $result['bottomRepeating']);
        $this->assertSame('columnFooter', $result['bottomRepeating'][0]['type']);
    }

    #[Test]
    public function classifyBands_disabled_band_excluded(): void
    {
        $bands = [
            ['type' => 'title', 'enabled' => false, 'children' => []],
            ['type' => 'pageHeader', 'enabled' => false, 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertNull($result['title']);
        $this->assertEmpty($result['topRepeating']);
        $this->assertNull($result['detail']);
        $this->assertEmpty($result['bottomRepeating']);
        $this->assertNull($result['summary']);
    }

    #[Test]
    public function classifyBands_unknown_type_with_top_anchor_goes_to_topRepeating(): void
    {
        $bands = [
            ['type' => 'header', 'anchor' => 'top', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertCount(1, $result['topRepeating']);
        $this->assertSame('header', $result['topRepeating'][0]['type']);
    }

    #[Test]
    public function classifyBands_unknown_type_with_bottom_anchor_goes_to_bottomRepeating(): void
    {
        $bands = [
            ['type' => 'footer', 'anchor' => 'bottom', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertCount(1, $result['bottomRepeating']);
        $this->assertSame('footer', $result['bottomRepeating'][0]['type']);
    }

    #[Test]
    public function classifyBands_multiple_bands_of_same_type_all_appear(): void
    {
        $bands = [
            ['type' => 'pageHeader', 'children' => []],
            ['type' => 'columnHeader', 'children' => []],
            ['type' => 'pageHeader', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertCount(3, $result['topRepeating']);
    }

    #[Test]
    public function classifyBands_all_types_together(): void
    {
        $bands = [
            ['type' => 'title', 'children' => []],
            ['type' => 'pageHeader', 'children' => []],
            ['type' => 'detail', 'collectionPath' => 'items', 'children' => []],
            ['type' => 'pageFooter', 'children' => []],
            ['type' => 'summary', 'children' => []],
        ];

        $result = $this->callClassifyBands($bands);

        $this->assertNotNull($result['title']);
        $this->assertCount(1, $result['topRepeating']);
        $this->assertNotNull($result['detail']);
        $this->assertEmpty($result['bottomRepeating']);
        $this->assertNotNull($result['pageFooter']);
        $this->assertNotNull($result['summary']);
    }

    #[Test]
    public function classifyBands_empty_bands_returns_defaults(): void
    {
        $result = $this->callClassifyBands([]);

        $this->assertNull($result['title']);
        $this->assertEmpty($result['topRepeating']);
        $this->assertNull($result['detail']);
        $this->assertEmpty($result['bottomRepeating']);
        $this->assertNull($result['summary']);
    }

    // ── precomputeDetailHeights ──

    /**
     * Call the private precomputeDetailHeights method via reflection.
     */
    private function callPrecomputeDetailHeights(array $detail_nodes, array $collection): array
    {
        $method = new \ReflectionMethod(ReportCompiler::class, 'precomputeDetailHeights');

        return $method->invoke($this->report_compiler, $detail_nodes, $collection);
    }

    #[Test]
    public function precomputeDetailHeights_empty_collection_returns_empty_array(): void
    {
        $detail_nodes = [
            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 10, 'height' => 20]],
        ];

        $result = $this->callPrecomputeDetailHeights($detail_nodes, []);

        $this->assertSame([], $result);
    }

    #[Test]
    public function precomputeDetailHeights_three_items_returns_three_heights(): void
    {
        $detail_nodes = [
            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 10, 'height' => 20]],
        ];
        $collection = [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
            ['name' => 'Charlie'],
        ];

        $result = $this->callPrecomputeDetailHeights($detail_nodes, $collection);

        $this->assertCount(3, $result);
        // Shape with height=20 at y=0 → 20.0
        $this->assertSame(20.0, $result[0]);
        $this->assertSame(20.0, $result[1]);
        $this->assertSame(20.0, $result[2]);
    }

    #[Test]
    public function precomputeDetailHeights_heights_match_node_dimensions(): void
    {
        // Two nodes stacked: first at y=0 h=15, second at y=15 h=10 → max bottom = 25
        $detail_nodes = [
            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 10, 'height' => 15]],
            ['x' => 0, 'y' => 15, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 10, 'height' => 10]],
        ];
        $collection = [
            ['val' => 1],
            ['val' => 2],
        ];

        $result = $this->callPrecomputeDetailHeights($detail_nodes, $collection);

        $this->assertCount(2, $result);
        // node1: y=0 + h=15 = 15, node2: y=15 + h=10 = 25 → max = 25
        $this->assertSame(25.0, $result[0]);
        $this->assertSame(25.0, $result[1]);
    }

    // ── distributeItemsIntoPages ──

    /**
     * Call the private distributeItemsIntoPages method via reflection.
     */
    private function callDistributeItemsIntoPages(array $detail_heights, float $available_first, float $available_middle): array
    {
        $method = new \ReflectionMethod(ReportCompiler::class, 'distributeItemsIntoPages');

        return $method->invoke($this->report_compiler, $detail_heights, $available_first, $available_middle);
    }

    #[Test]
    public function distributeItemsIntoPages_fifteen_items_two_pages(): void
    {
        // 15 items × 20mm each with 200mm available → 10 on page 0, 5 on page 1
        $heights = array_fill(0, 15, 20.0);

        $result = $this->callDistributeItemsIntoPages($heights, 200.0, 200.0);

        $this->assertCount(2, $result);
        $this->assertCount(10, $result[0]);
        $this->assertCount(5, $result[1]);
        // Page 0: items 0-9, Page 1: items 10-14
        $this->assertSame(range(0, 9), $result[0]);
        $this->assertSame(range(10, 14), $result[1]);
    }

    #[Test]
    public function distributeItemsIntoPages_single_oversized_item_one_page(): void
    {
        // Single item 150mm > 100mm available → still 1 page (oversized rule)
        $heights = [150.0];

        $result = $this->callDistributeItemsIntoPages($heights, 100.0, 100.0);

        $this->assertCount(1, $result);
        $this->assertSame([0], $result[0]);
    }

    #[Test]
    public function distributeItemsIntoPages_exact_fit_one_page(): void
    {
        // 2 items × 50mm = exactly 100mm → 1 page, no break
        $heights = [50.0, 50.0];

        $result = $this->callDistributeItemsIntoPages($heights, 100.0, 100.0);

        $this->assertCount(1, $result);
        $this->assertSame([0, 1], $result[0]);
    }

    #[Test]
    public function distributeItemsIntoPages_different_first_vs_middle(): void
    {
        // First page has 50mm available (title takes space), middle has 100mm
        // 4 items × 30mm = 120mm total
        // Page 0 (50mm): items 0 (30mm), item 1 (30mm) → 60 > 50 but fits? No: 30 <= 50 → item 0 fits (30). 30+30=60 > 50, and 30 < 50 so → new page. Wait...
        // Let me re-think: item 0 (30mm) fits in 50mm → page 0. current_h=30.
        // item 1 (30mm): 30+30=60 > 50, and 30 < 50 (not oversized) → new page. page 1. current_h=30.
        // item 2 (30mm): 30+30=60 <= 100 → fits. current_h=60.
        // item 3 (30mm): 60+30=90 <= 100 → fits. current_h=90.
        // Result: page 0=[0], page 1=[1,2,3]
        $heights = [30.0, 30.0, 30.0, 30.0];

        $result = $this->callDistributeItemsIntoPages($heights, 50.0, 100.0);

        $this->assertCount(2, $result);
        $this->assertSame([0], $result[0]);
        $this->assertSame([1, 2, 3], $result[1]);
    }

    #[Test]
    public function distributeItemsIntoPages_empty_array_single_empty_page(): void
    {
        $result = $this->callDistributeItemsIntoPages([], 200.0, 200.0);

        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]);
    }

    #[Test]
    public function distributeItemsIntoPages_just_overflows_creates_new_page(): void
    {
        // 3 items: 40 + 40 + 40 = 120 > 100 available
        // item 0: 40 <= 100 → page 0, h=40
        // item 1: 40+40=80 <= 100 → page 0, h=80
        // item 2: 80+40=120 > 100, 40 < 100 → new page. page 1
        $heights = [40.0, 40.0, 40.0];

        $result = $this->callDistributeItemsIntoPages($heights, 100.0, 100.0);

        $this->assertCount(2, $result);
        $this->assertSame([0, 1], $result[0]);
        $this->assertSame([2], $result[1]);
    }

    // ── Integration: compile multi-page ──

    /**
     * Helper: create a mock Tcpdf with graph/page mocks and track getLine/getRect calls.
     */
    private function createCompileMock(): array
    {
        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $capture = new class {
            /** @var list<array{float, float, float, float}> */
            public array $lines = [];

            public function __invoke(): string
            {
                $args = func_get_args();
                $x1 = (float) ($args[0] ?? 0);
                $y1 = (float) ($args[1] ?? 0);
                $x2 = (float) ($args[2] ?? 0);
                $y2 = (float) ($args[3] ?? 0);
                $this->lines[] = [$x1, $y1, $x2, $y2];

                return 'shape ' . count($this->lines);
            }
        };

        $graph->method('getLine')->willReturnCallback($capture);
        $graph->method('getRect')->willReturnCallback($capture);

        $page->method('addContent');

        $pdf->method('getOutPDFString')->willReturn('%PDF-mock');

        return [$pdf, $graph, $capture];
    }

    #[Test]
    public function compile_multi_page_renders_all_items(): void
    {
        // 8 items × 30mm each, available ~200mm → should split into pages
        // page: 210×297, margins 10 → content_h = 277
        // detail items: Shape line with height=30
        // Available on first page: 277 - 0 (no title) - 0 (no bottom) = 277
        // 9 items × 30 = 270 ≤ 277 → 9 items on page 0
        // item 9: 270+30=300 > 277, 30 < 277 → new page
        // Page 1: items 9-11 (3 items)
        // Total: 2 pages → addPage called once
        [$pdf, $graph, $capture] = $this->createCompileMock();

        $pdf->expects($this->exactly(2))
            ->method('addPage')
            ->willReturn([]);

        $report_compiler = new ReportCompiler($pdf);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 50, 'height' => 30]],
                        ],
                    ],
                ],
            ],
        ], [
            'items' => array_map(fn($i) => ['id' => $i], range(0, 11)),
        ]);

        // 12 items rendered total
        $this->assertCount(12, $capture->lines);
    }

    // ── Integration: title first page only ──

    #[Test]
    public function compile_title_renders_only_on_first_page(): void
    {
        // Config with title + detail. 8 items × 30mm = 240mm
        // Available first page: 277 - title_h(20) = 257
        // 8 items × 30 = 240 ≤ 257 → all fit on one page? No wait...
        // 9 items × 30 = 270 ≤ 257? No, 270 > 257
        // So 8 items × 30 = 240 ≤ 257 → 8 items on page 0
        // item 8: 240+30=270 > 257, 30 < 257 → new page
        // page 1: items 8-11 (4 items)
        // 2 pages total
        // Title shape: line from (0,0) to (999,0) — unique x2=999
        // Detail shape: line from (0,0) to (10,5) — x2=10
        [$pdf, $graph, $capture] = $this->createCompileMock();

        $pdf->expects($this->exactly(2))
            ->method('addPage')
            ->willReturn([]);

        $report_compiler = new ReportCompiler($pdf);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'title',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 999, 'y2' => 0]],
                        ],
                    ],
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 50, 'height' => 30]],
                        ],
                    ],
                ],
            ],
        ], [
            'items' => array_map(fn($i) => ['id' => $i], range(0, 11)),
        ]);

        // Title (x2 = margin_left + 999 = 1009) rendered exactly once — on page 0 only
        $title_lines = array_filter($capture->lines, fn($l) => $l[2] === 1009.0);
        $this->assertCount(1, $title_lines);
    }

    // ── Integration: summary last page only ──

    #[Test]
    public function compile_summary_renders_only_on_last_page(): void
    {
        // Config with detail + summary. 8 items × 30mm
        // Available first page: 277 - 0 = 277 (no title)
        // 9 items × 30 = 270 ≤ 277 → 9 on page 0
        // item 9: 270+30=300 > 277, 30 < 277 → new page
        // page 1: items 9-11 (3 items) → last page
        // Summary shape: line from (0,0) to (888,0) — unique x2=888
        // Detail shape: line from (0,0) to (10,5) — x2=10
        [$pdf, $graph, $capture] = $this->createCompileMock();

        $pdf->expects($this->exactly(2))
            ->method('addPage')
            ->willReturn([]);

        $report_compiler = new ReportCompiler($pdf);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 50, 'height' => 30]],
                        ],
                    ],
                    [
                        'type' => 'summary',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 888, 'y2' => 0]],
                        ],
                    ],
                ],
            ],
        ], [
            'items' => array_map(fn($i) => ['id' => $i], range(0, 11)),
        ]);

        // Summary (x2 = margin_left + 888 = 898) rendered exactly once — on last page only
        $summary_lines = array_filter($capture->lines, fn($l) => $l[2] === 898.0);
        $this->assertCount(1, $summary_lines);
    }

    // ── Integration: empty/null collection ──

    #[Test]
    public function compile_empty_collection_single_page(): void
    {
        [$pdf, $graph, $capture] = $this->createCompileMock();

        $pdf->expects($this->once())
            ->method('addPage');

        $report_compiler = new ReportCompiler($pdf);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'title',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 999, 'y2' => 0]],
                        ],
                    ],
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 50, 'height' => 30]],
                        ],
                    ],
                    [
                        'type' => 'summary',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 888, 'y2' => 0]],
                        ],
                    ],
                ],
            ],
        ], []);

        // Title + detail (static, no collection) + summary rendered, no extra pages
        // Should be 3 shapes: title line + detail rect + summary line
        $this->assertCount(3, $capture->lines, 'Expected title + detail + summary shapes, got: ' . json_encode($capture->lines));
        $title_lines = array_filter($capture->lines, fn($l) => $l[2] === 1009.0);
        $summary_lines = array_filter($capture->lines, fn($l) => $l[2] === 898.0);
        $this->assertCount(1, $title_lines);
        $this->assertCount(1, $summary_lines);
    }

    // ── Integration: oversized single item ──

    #[Test]
    public function compile_oversized_single_item_renders_without_page_break(): void
    {
        // 1 item × 150mm, available ~277mm (no title/summary) → renders on page, no extra page
        [$pdf, $graph, $capture] = $this->createCompileMock();

        $pdf->expects($this->once())
            ->method('addPage');

        $report_compiler = new ReportCompiler($pdf);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'items',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Shape', 'shapeType' => 'rect', 'width' => 50, 'height' => 150]],
                        ],
                    ],
                ],
            ],
        ], [
            'items' => [['id' => 1]],
        ]);

        // 1 item rendered, no extra page
        $this->assertCount(1, $capture->lines);
    }

    // ── Integration: Label interpolation inside detail band ──

    #[Test]
    public function compile_interpolates_label_in_detail_band_with_collection(): void
    {
        // Detail band with collectionPath "results" and a Label with "{{ name }}"
        // PokeAPI-like data with 2 items → Label should interpolate "bulbasaur" and "ivysaur"
        //
        // FontMetrics::insertFont() accesses $pdf->font->insert() which needs a real
        // Font\Stack. We use a mock of Tcpdf and set the public `font` + `pon` properties.

        $fontMock = $this->createMock(\Com\Tecnick\Pdf\Font\Stack::class);
        $fontMock->method('insert')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 10,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ]);
        $fontMock->method('getOrdArrWidth')->willReturn(50.0);

        $pdf = $this->createMock(Tcpdf::class);
        $pdf->font = $fontMock;
        $pdf->pon = 0;

        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $pdf->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf->method('getOutPDFString')->willReturn('%PDF-mock');
        $page->method('addContent');

        $pdf->method('toUnit')
            ->willReturnArgument(0);

        // Capture text rendered by Labels
        $textCalls = [];
        $pdf->method('addTextCell')
            ->willReturnCallback(function (string $txt, ...$rest) use (&$textCalls) {
                $textCalls[] = $txt;
            });

        $report_compiler = new ReportCompiler($pdf);

        $pokeData = [
            'count' => 1351,
            'next' => 'https://pokeapi.co/api/v2/pokemon/?offset=20&limit=20',
            'previous' => null,
            'results' => [
                ['name' => 'bulbasaur', 'url' => 'https://pokeapi.co/api/v2/pokemon/1/'],
                ['name' => 'ivysaur', 'url' => 'https://pokeapi.co/api/v2/pokemon/2/'],
            ],
        ];

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'results',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Label', 'text' => '{{ name }}']],
                        ],
                    ],
                ],
            ],
        ], $pokeData);

        // Each item in the collection should render with interpolated name
        $this->assertNotEmpty($textCalls, 'Label should have rendered text, got empty');
        $this->assertContains('bulbasaur', $textCalls, 'Expected "bulbasaur" in rendered labels, got: ' . implode(', ', $textCalls));
        $this->assertContains('ivysaur', $textCalls, 'Expected "ivysaur" in rendered labels, got: ' . implode(', ', $textCalls));
        // The literal placeholder should NOT appear
        foreach ($textCalls as $call) {
            $this->assertStringNotContainsString('{{', $call, 'Label should interpolate placeholders, not render them literally. Got: ' . $call);
        }
    }

    // ── Integration: Label with array path inside detail band ──

    #[Test]
    public function compile_interpolates_array_path_label_in_detail_band(): void
    {
        // When a user drops a field "results[].name" on a detail band,
        // the label text becomes "{{ results[].name }}".
        // Inside the band iteration, local_data is a single item (no "results" key),
        // so it falls back to global_data (full response) where "results[].name" resolves.
        $fontMock = $this->createMock(\Com\Tecnick\Pdf\Font\Stack::class);
        $fontMock->method('insert')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 10,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ]);
        $fontMock->method('getOrdArrWidth')->willReturn(50.0);

        $pdf = $this->createMock(Tcpdf::class);
        $pdf->font = $fontMock;
        $pdf->pon = 0;

        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $pdf->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf->method('getOutPDFString')->willReturn('%PDF-mock');
        $page->method('addContent');

        $pdf->method('toUnit')
            ->willReturnArgument(0);

        $textCalls = [];
        $pdf->method('addTextCell')
            ->willReturnCallback(function (string $txt, ...$rest) use (&$textCalls) {
                $textCalls[] = $txt;
            });

        $report_compiler = new ReportCompiler($pdf);

        $pokeData = [
            'count' => 1351,
            'next' => 'https://pokeapi.co/api/v2/pokemon/?offset=20&limit=20',
            'previous' => null,
            'results' => [
                ['name' => 'bulbasaur', 'url' => 'https://pokeapi.co/api/v2/pokemon/1/'],
                ['name' => 'ivysaur', 'url' => 'https://pokeapi.co/api/v2/pokemon/2/'],
            ],
        ];

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'collectionPath' => 'results',
                        'children' => [
                            ['x' => 0, 'y' => 0, 'node' => ['type' => 'Label', 'text' => '{{ results[].name }}']],
                        ],
                    ],
                ],
            ],
        ], $pokeData);

        $this->assertNotEmpty($textCalls, 'Label should have rendered text, got empty');
        // {{ results[].name }} resolves via global_data → all names joined
        $rendered = implode(', ', $textCalls);
        $this->assertStringContainsString('bulbasaur', $rendered, 'Expected "bulbasaur" in: ' . $rendered);
        $this->assertStringContainsString('ivysaur', $rendered, 'Expected "ivysaur" in: ' . $rendered);
        foreach ($textCalls as $call) {
            $this->assertStringNotContainsString('{{', $call, 'Label should interpolate, got literal: ' . $call);
        }
    }

    // ── Integration: circle Shape inside VBox keeps designer dimensions ──

    #[Test]
    public function compile_circle_in_vbox_uses_designer_dimensions(): void
    {
        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $circleCalls = [];
        $graph->method('getCircle')
            ->willReturnCallback(function (...$args) use (&$circleCalls): string {
                $circleCalls[] = $args;
                return 'c ';
            });

        $page->method('addContent');
        $pdf->method('getOutPDFString')->willReturn('%PDF-mock');
        $pdf->expects($this->once())->method('addPage')->willReturn([]);

        $report_compiler = new ReportCompiler($pdf);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'children' => [
                            [
                                'x' => 10,
                                'y' => 20,
                                'node' => [
                                    'type' => 'VBox',
                                    'width' => 100,
                                    'height' => 200,
                                    'children' => [
                                        [
                                            'type' => 'Shape',
                                            'shapeType' => 'circle',
                                            'w' => 30,
                                            'h' => 30,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $circleCalls, 'Expected one circle render call');
        [$cx, $cy, $radius] = $circleCalls[0];

        // margins 10 → band origin at (10,10); node at (10,20) → VBox origin (20,30)
        // circle 30×30 → center at (20+15, 30+15), radius 15
        $this->assertSame(35.0, $cx);
        $this->assertSame(45.0, $cy);
        $this->assertSame(15.0, $radius);
    }

    // ── Integration: root band Label keeps explicit width and wraps text ──

    #[Test]
    public function compile_root_band_label_wraps_text_at_its_width(): void
    {
        $fontMock = $this->createMock(\Com\Tecnick\Pdf\Font\Stack::class);
        $fontMock->method('insert')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 10,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ]);

        $pdf = $this->createMock(Tcpdf::class);
        $pdf->font = $fontMock;
        $pdf->pon = 0;

        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $pdf->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf->method('getOutPDFString')->willReturn('%PDF-mock');
        $page->method('addContent');
        $page->method('getPage')->willReturn(['pheight' => 297.0]);
        $page->method('getKUnit')->willReturn(1.0);
        $pdf->method('toUnit')->willReturnArgument(0);

        $fontMetrics = $this->createMock(FontMetrics::class);
        $fontMetrics->method('insertFont')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 10,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ]);
        $fontMetrics->method('getLineHeight')->willReturn(10.0);
        // Width 50: each individual word fits, any two-word combo exceeds 50.
        $fontMetrics->method('getStringWidth')->willReturnCallback(function (string $text) {
            $wordCount = count(explode(' ', $text));
            return $wordCount * 30.0;
        });

        $textCalls = [];
        $pdf->method('addTextCell')
            ->willReturnCallback(function (string $txt, ...$rest) use (&$textCalls) {
                $textCalls[] = $txt;
            });

        $report_compiler = new ReportCompiler($pdf, $fontMetrics);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'children' => [
                            [
                                'x' => 0,
                                'y' => 0,
                                'node' => [
                                    'type' => 'Label',
                                    'text' => 'One Two Three',
                                    'width' => 50,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], []);

        // Each word should be on its own line because two words exceed width 50.
        $this->assertSame(['One', 'Two', 'Three'], $textCalls);
    }

    #[Test]
    public function compile_root_band_label_with_fixed_size_emits_clip_rect(): void
    {
        $fontMock = $this->createMock(\Com\Tecnick\Pdf\Font\Stack::class);
        $fontMock->method('insert')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 10,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ]);

        $pdf = $this->createMock(Tcpdf::class);
        $pdf->font = $fontMock;
        $pdf->pon = 0;

        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $page->method('getPage')->willReturn(['pheight' => 297.0]);
        $page->method('getKUnit')->willReturn(1.0);
        $page->method('enableAutoPageBreak');
        $pdf->graph = $graph;
        $pdf->page = $page;

        $pdf->expects($this->once())
            ->method('addPage')
            ->willReturn([]);

        $pdf->method('getOutPDFString')->willReturn('%PDF-mock');
        $page->method('addContent');
        $pdf->method('toUnit')->willReturnArgument(0);

        $contentCalls = [];
        $page->method('addContent')
            ->willReturnCallback(function (string $content) use (&$contentCalls): void {
                $contentCalls[] = $content;
            });

        $fontMetrics = $this->createMock(FontMetrics::class);
        $fontMetrics->method('insertFont')->willReturn([
            'out' => '/F1 1 Tf',
            'size' => 10,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ]);
        $fontMetrics->method('getLineHeight')->willReturn(10.0);
        $fontMetrics->method('getStringWidth')->willReturn(100.0);

        $report_compiler = new ReportCompiler($pdf, $fontMetrics);

        $report_compiler->compile([
            'page' => [
                'width' => 210,
                'height' => 297,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'type' => 'detail',
                        'children' => [
                            [
                                'x' => 5,
                                'y' => 5,
                                'node' => [
                                    'type' => 'Label',
                                    'text' => 'Supercalifragilistic',
                                    'width' => 50,
                                    'height' => 20,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], []);

        $allContent = implode('', $contentCalls);

        // Graphics state saved and restored
        $this->assertStringContainsString('q', $allContent);
        $this->assertStringContainsString('Q', $allContent);
        // Clip rectangle: band margin (10) + node x/y (5,5) + margin (0) = (15,15)
        // PDF y = 297 - 15 - 20 = 262
        $this->assertStringContainsString(' 15.000 262.000 50.000 20.000 re W n', $allContent);
    }
}
