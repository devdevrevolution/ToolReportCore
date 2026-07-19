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

class LayoutEngineBandTest extends TestCase
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

    private function baseConfig(): array
    {
        return [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
        ];
    }

    // ── Title band (flowing, first page only) ──

    #[Test]
    public function it_renders_title_band_as_flowing(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 30,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Report Title'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // Title is inside a pdf-band container (position: relative, height: 30mm)
        // Element Y is band-relative: element.y(5) = 5mm
        $this->assertStringContainsString('pdf-band', $result->html);
        $this->assertStringContainsString('height: 30mm', $result->html);
        $this->assertStringContainsString('top: 5mm', $result->html);
        // Title is NOT position:fixed — it's a flowing band
        $this->assertStringContainsString('position: relative', $result->html);
        $this->assertStringNotContainsString('pdf-fixed', $this->extractElementHtml($result->html, 'Report Title'));
        $this->assertStringContainsString('Report Title', $result->html);
        $this->assertEquals(1, $result->elementCount);
    }

    // ── Page Header (fixed band, repeats on every page via position: fixed) ──

    #[Test]
    public function it_renders_page_header_as_fixed(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Page Header'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // pageHeader is a fixed band (position: fixed via pdf-fixed class)
        $this->assertStringContainsString('pdf-fixed', $result->html);
        $this->assertStringContainsString('Page Header', $result->html);
        // Element Y is page-absolute: marginTop(10) + element.y(2) = 12mm
        $this->assertStringContainsString('top: 12mm', $result->html);
        // Element X is page-absolute: marginLeft(10) + element.x(10) = 20mm
        $this->assertStringContainsString('left: 20mm', $result->html);
        // Body margin includes fixed header height: marginTop(10) + bandHeight(12) = 22mm
        $this->assertStringContainsString('margin: 22mm', $result->html);
        $this->assertEquals(1, $result->elementCount);
    }

    // ── Column Header (flowing band, after pageHeader) ──

    #[Test]
    public function it_renders_column_header_as_flowing(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'PH'], 'styles' => []],
                ],
            ],
            [
                'id' => 'columnHeader', 'type' => 'columnHeader', 'anchor' => 'top', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'CH'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // pageHeader is fixed, columnHeader is flowing
        $this->assertStringContainsString('pdf-fixed', $result->html);
        $this->assertStringContainsString('pdf-band', $result->html);
        // PH element uses page-absolute coords: marginTop(10) + element.y(2) = 12mm
        $this->assertStringContainsString('top: 12mm', $result->html);
        // CH element uses band-relative coords: element.y(1) = 1mm
        $this->assertStringContainsString('top: 1mm', $result->html);
        // PH appears before CH in the HTML (fixed elements rendered before flowing)
        $phPos = strpos($result->html, 'PH');
        $chPos = strpos($result->html, 'CH');
        $this->assertLessThan($chPos, $phPos, 'pageHeader should appear before columnHeader');
        // Body margin includes fixed header height: marginTop(10) + bandHeight(12) = 22mm
        $this->assertStringContainsString('margin: 22mm', $result->html);
    }

    // ── Page Footer (fixed, repeats on every page) ──

    #[Test]
    public function it_renders_page_footer_as_fixed_at_bottom(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Page Footer'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // pageFooter should be position:fixed
        $this->assertStringContainsString('position: fixed', $result->html);
        $this->assertStringContainsString('Page Footer', $result->html);
        // Y position: pageHeight(297) - marginBottom(10) - pageFooter.height(12) + element.y(1) = 276
        $this->assertStringContainsString('top: 276mm', $result->html);
        // Body margin-bottom: 10 (margin) + 12 (pageFooter) = 22
        $this->assertStringContainsString('22mm 10mm', $result->html);
    }

    // ── Full report with all band types ──

    #[Test]
    public function it_renders_full_report_with_fixed_and_flowing_bands(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 190, 'height' => 10, 'content' => ['text' => 'Title'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'PH'], 'styles' => []],
                ],
            ],
            [
                'id' => 'columnHeader', 'type' => 'columnHeader', 'anchor' => 'top', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'CH'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 100, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
            [
                'id' => 'columnFooter', 'type' => 'columnFooter', 'anchor' => 'bottom', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'CF'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'PF'], 'styles' => []],
                ],
            ],
            [
                'id' => 'summary', 'type' => 'summary', 'anchor' => 'bottom', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 3, 'width' => 190, 'height' => 12, 'content' => ['text' => 'Summary'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => [['name' => 'Item1'], ['name' => 'Item2']]];

$result = $this->engine->render($config, $data);

        // iReport flow order: Title → PageHeader → ColumnHeader → Detail → ColumnFooter → Summary
        // pageHeader and pageFooter are fixed (position:fixed).
        // All other bands are flowing .pdf-band containers.

        // Flowing band elements use band-relative Y coordinates
        $this->assertStringContainsString('top: 5mm', $result->html);   // Title: y=5 inside band
        $this->assertStringContainsString('top: 1mm', $result->html);   // CH: y=1 inside band
        $this->assertStringContainsString('top: 0mm', $result->html);   // Detail: y=0 inside band
        $this->assertStringContainsString('top: 2mm', $result->html);   // CF: y=2 inside band
        $this->assertStringContainsString('top: 3mm', $result->html);   // Summary: y=3 inside band

        // PH is fixed: page-absolute coords = marginTop(10) + element.y(2) = 12mm
        $this->assertStringContainsString('top: 12mm', $result->html);
        // pageFooter is fixed at bottom
        $this->assertStringContainsString('top: 276mm', $result->html); // PF: fixed at bottom
        $this->assertStringContainsString('pdf-fixed', $result->html);

        // Band containers with correct heights (flowing bands only)
        $this->assertStringContainsString('height: 20mm', $result->html); // title
        $this->assertStringContainsString('height: 10mm', $result->html); // columnHeader/detail/columnFooter

        // Body margin: top includes PH height, bottom includes PF height
        // Top margin = 10 (margin) + 12 (pageHeader) = 22
        // Bottom margin = 10 (margin) + 12 (pageFooter) = 22
        $this->assertStringContainsString('margin: 22mm', $result->html);
        $this->assertStringContainsString('22mm 10mm', $result->html);

        // iReport flow order in HTML
        $titlePos = strpos($result->html, 'Title');
        $phPos = strpos($result->html, 'PH');
        $chPos = strpos($result->html, 'CH');
        $detailPos = strpos($result->html, 'Item1');
        $this->assertLessThan($titlePos, $phPos, 'PH before Title');
        $this->assertLessThan($chPos, $phPos, 'PH before CH');
        $this->assertLessThan($detailPos, $chPos, 'CH before detail');

        // All content present
        $this->assertStringContainsString('Title', $result->html);
        $this->assertStringContainsString('PH', $result->html);
        $this->assertStringContainsString('CH', $result->html);
        $this->assertStringContainsString('Item1', $result->html);
        $this->assertStringContainsString('Item2', $result->html);
        $this->assertStringContainsString('CF', $result->html);
        $this->assertStringContainsString('PF', $result->html);
        $this->assertStringContainsString('Summary', $result->html);

        // Element count: title(1)+PH(1)+CH(1)+detail(2)+CF(1)+PF(1)+summary(1) = 8
        $this->assertEquals(8, $result->elementCount);
    }

    // ── Detail band repetition ──

    #[Test]
    public function it_repeats_detail_band_for_collection_items(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 100, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
        ];

        $data = [
            'items' => [
                ['name' => 'Item1'],
                ['name' => 'Item2'],
                ['name' => 'Item3'],
            ],
        ];

        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Item1', $result->html);
        $this->assertStringContainsString('Item2', $result->html);
        $this->assertStringContainsString('Item3', $result->html);
        $this->assertEquals(3, $result->elementCount);
        // Each detail item is inside its own pdf-band container (height: 10mm)
        // Elements within bands use band-relative Y: element.y=0 → top: 0mm
        $this->assertStringContainsString('pdf-band', $result->html);
        $this->assertStringContainsString('height: 10mm', $result->html);
    }

    #[Test]
    public function it_skips_detail_band_with_empty_collection(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Header'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => []];

        $result = $this->engine->render($config, $data);
        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Header', $result->html);
    }

    #[Test]
    public function it_skips_detail_band_when_collection_key_missing(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Header'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'orders',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => '{{id}}'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => [['id' => 1]]];

        $result = $this->engine->render($config, $data);
        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Header', $result->html);
    }

    #[Test]
    public function it_passes_local_data_to_renderers_in_detail_band(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 100, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
        ];

        $data = [
            'name' => 'Global Name',
            'items' => [['name' => 'Local Item']],
        ];

        $result = $this->engine->render($config, $data);
        $this->assertStringContainsString('Local Item', $result->html);
        $this->assertStringNotContainsString('Global Name', $result->html);
    }

    #[Test]
    public function it_wraps_non_array_collection_value_as_single_item(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'singleItem',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Widget'], 'styles' => []],
                ],
            ],
        ];

        $data = ['singleItem' => ['name' => 'Widget']];
        $result = $this->engine->render($config, $data);
        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Widget', $result->html);
    }

    #[Test]
    public function it_strips_array_notation_from_collection_path(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'orders[]',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => '{{id}}'], 'styles' => []],
                ],
            ],
        ];

        $data = ['orders' => [['id' => '1'], ['id' => '2']]];
        $result = $this->engine->render($config, $data);
        $this->assertEquals(2, $result->elementCount);
    }

    #[Test]
    public function it_resolves_dot_notation_in_collection_path(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'data.orders[]',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
        ];

        $data = ['data' => ['orders' => [['name' => 'Order A'], ['name' => 'Order B']]]];
        $result = $this->engine->render($config, $data);
        $this->assertStringContainsString('Order A', $result->html);
        $this->assertStringContainsString('Order B', $result->html);
        $this->assertEquals(2, $result->elementCount);
    }

    // ── Backward compatibility ──

    #[Test]
    public function it_falls_back_to_flat_elements_when_no_bands(): void
    {
        $config = [
            'page' => [
                'width' => 210, 'height' => 297, 'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'elements' => [
                ['type' => 'text', 'x' => 10, 'y' => 10, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Flat Element'], 'styles' => []],
            ],
        ];

        $result = $this->engine->render($config);
        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Flat Element', $result->html);
        // v1/v2 path doesn't add fixed header/footer padding
        $this->assertStringContainsString('margin: 10mm', $result->html);
    }

    #[Test]
    public function it_falls_back_to_flat_elements_when_bands_empty(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [];
        $config['elements'] = [
            ['type' => 'text', 'x' => 10, 'y' => 10, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Fallback'], 'styles' => []],
        ];

        $result = $this->engine->render($config);
        $this->assertEquals(1, $result->elementCount);
        $this->assertStringContainsString('Fallback', $result->html);
    }

    // ── Bands without explicit anchor default to 'top' ──

    #[Test]
    public function it_handles_bands_without_anchor_as_top_anchored(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'type' => 'header',
                'height' => 20,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'NoAnchor'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);
        $this->assertEquals(1, $result->elementCount);
        // Band without anchor defaults to 'top' → rendered as flowing pdf-band
        // Element Y is band-relative: element.y(0) = 0mm
        $this->assertStringContainsString('pdf-band', $result->html);
        $this->assertStringContainsString('NoAnchor', $result->html);
    }

    // ── Summary band renders at end of flowing content ──

    #[Test]
    public function it_renders_summary_after_detail_content(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 100, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
            [
                'id' => 'summary', 'type' => 'summary', 'anchor' => 'bottom', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 3, 'width' => 190, 'height' => 12, 'content' => ['text' => 'Total'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => [['name' => 'A'], ['name' => 'B']]];
        $result = $this->engine->render($config, $data);

        // Detail items: each inside a pdf-band, element Y band-relative (y=0 → top: 0mm)
        // Summary: inside its own pdf-band, element Y band-relative (y=3 → top: 3mm)
        $this->assertStringContainsString('top: 3mm', $result->html);
        $this->assertStringContainsString('Total', $result->html);
        // Summary is NOT fixed (it's a flowing band, last page only)
        $this->assertStringNotContainsString('pdf-fixed', $this->extractElementHtml($result->html, 'Total'));
        // Summary is inside a pdf-band container
        $this->assertStringContainsString('pdf-band', $result->html);
    }

    // ── Page footer fixed ──

    #[Test]
    public function it_renders_page_footer_and_column_footer_separately(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Data'], 'styles' => []],
                ],
            ],
            [
                'id' => 'columnFooter', 'type' => 'columnFooter', 'anchor' => 'bottom', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'ColFooter'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'PageFoot'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => []];
        $result = $this->engine->render($config, $data);

        // pageFooter is fixed (position:fixed at bottom)
        // columnFooter is a flowing band (after detail)
        // Detail Y: inside band, band-relative (y=0 → top:0mm)
        $this->assertStringContainsString('Data', $result->html);
        // ColumnFooter: inside its band (y=2 → top:2mm)
        $this->assertStringContainsString('top: 2mm', $result->html);
        // PageFooter Y: 297 - 10 - 12 + 1 = 276mm (fixed at bottom)
        $this->assertStringContainsString('top: 276mm', $result->html);
        // All bands are in pdf-band containers except pageFooter
        $this->assertStringContainsString('pdf-band', $result->html);
        // pageFooter uses pdf-fixed
        $this->assertStringContainsString('pdf-fixed', $result->html);
    }

    // ── Dynamic overflow ──

    #[Test]
    public function it_allows_dynamic_overflow_during_detail_repetition(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Header'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Item'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 10, 'content' => ['text' => 'Footer'], 'styles' => []],
                ],
            ],
        ];

        // 30 items × 10mm detail + 20mm title = dynamic overflow
        $data = ['items' => array_map(fn($i) => ['name' => "Item $i"], range(1, 30))];

        $result = $this->engine->render($config, $data);

        $this->assertStringContainsString('Header', $result->html);
        $this->assertStringContainsString('Footer', $result->html);
        // 30 detail + 1 title + 1 pageFooter = 32 elements
        $this->assertEquals(32, $result->elementCount);
    }

    // ── Multi-page pagination ──

    #[Test]
    public function it_wraps_flowing_bands_in_pdf_band_containers(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 25,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Title'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // Title band must be wrapped in a pdf-band div
        $this->assertStringContainsString('class="pdf-band"', $result->html);
        $this->assertStringContainsString('height: 25mm', $result->html);
        $this->assertStringContainsString('position: relative', $result->html);
        // Element inside the band uses band-relative Y (5mm, not page-absolute)
        $this->assertStringContainsString('top: 5mm', $result->html);
    }

    #[Test]
    public function it_renders_each_detail_item_in_separate_band_container(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 100, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => [['name' => 'Alpha'], ['name' => 'Beta'], ['name' => 'Gamma']]];

        $result = $this->engine->render($config, $data);

        // Each detail item gets its own pdf-band container with height: 10mm
        // Count the number of pdf-band divs (should be 3, one per item)
        $bandCount = substr_count($result->html, 'class="pdf-band"');
        $this->assertEquals(3, $bandCount);
        // Height should appear once per band
        $heightCount = substr_count($result->html, 'height: 10mm');
        $this->assertEquals(3, $heightCount);
        // Each element uses Y=2 relative to its band
        $yCount = substr_count($result->html, 'top: 2mm');
        $this->assertEquals(3, $yCount);
        // All content present
        $this->assertStringContainsString('Alpha', $result->html);
        $this->assertStringContainsString('Beta', $result->html);
        $this->assertStringContainsString('Gamma', $result->html);
    }

    #[Test]
    public function it_keeps_fixed_bands_outside_pdf_band_containers(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'PH'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'PF'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // Both pageHeader and pageFooter are fixed bands (pdf-fixed)
        $this->assertStringContainsString('pdf-fixed', $result->html);
        $this->assertStringContainsString('PH', $result->html);
        $this->assertStringContainsString('PF', $result->html);
        // No flowing bands = no pdf-band class in element attributes
        $this->assertStringNotContainsString('class="pdf-band"', $result->html);
        // Both pageHeader and pageFooter use pdf-fixed class
        $phElement = $this->extractElementHtml($result->html, 'PH');
        $this->assertStringContainsString('pdf-fixed', $phElement);
    }

    #[Test]
    public function it_renders_flowing_bands_in_correct_order(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 10, 'content' => ['text' => 'Title'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 100, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
            [
                'id' => 'columnFooter', 'type' => 'columnFooter', 'anchor' => 'bottom', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => 'ColFooter'], 'styles' => []],
                ],
            ],
            [
                'id' => 'summary', 'type' => 'summary', 'anchor' => 'bottom', 'height' => 15,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 10, 'content' => ['text' => 'Summary'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => [['name' => 'Row1']]];

        $result = $this->engine->render($config, $data);

        // Verify the flowing band order in the HTML:
        // Title → Detail → ColumnFooter → Summary
        $titlePos = strpos($result->html, 'Title');
        $detailPos = strpos($result->html, 'Row1');
        $colFooterPos = strpos($result->html, 'ColFooter');
        $summaryPos = strpos($result->html, 'Summary');

        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($detailPos);
        $this->assertNotFalse($colFooterPos);
        $this->assertNotFalse($summaryPos);

        $this->assertLessThan($detailPos, $titlePos, 'Title should appear before Detail');
        $this->assertLessThan($colFooterPos, $detailPos, 'Detail should appear before ColumnFooter');
        $this->assertLessThan($summaryPos, $colFooterPos, 'ColumnFooter should appear before Summary');
    }

    #[Test]
    public function it_renders_many_detail_items_for_multi_page_pdf(): void
    {
        // Simulate 50 detail rows — this will overflow a single A4 page
        // Each row is 10mm, so 50 rows = 500mm of detail content
        // A4 portrait = 297mm, with margins top=10 bottom=10 → ~277mm printable
        // This means we need multiple pages, handled by DomPDF via CSS flow
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Header'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => '{{name}}'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Footer'], 'styles' => []],
                ],
            ],
        ];

        // 50 items × 10mm = 500mm of detail content (overflows A4 297mm page)
        $data = ['items' => array_map(fn($i) => ['name' => "Item $i"], range(1, 50))];

        $result = $this->engine->render($config, $data);

        // pageHeader is fixed (pdf-fixed), detail items are flowing (pdf-band), pageFooter is fixed (pdf-fixed)
        $this->assertStringContainsString('pdf-band', $result->html);
        $this->assertStringContainsString('pdf-fixed', $result->html);
        $this->assertStringContainsString('Header', $result->html);
        $this->assertStringContainsString('Footer', $result->html);

        // 50 detail items = 50 flowing band containers (pageHeader is now fixed, not counted here)
        $bandCount = substr_count($result->html, 'class="pdf-band"');
        $this->assertEquals(50, $bandCount);

        // Body margin: top=22mm (10+12 for header), bottom=22mm (10+12 for footer)
        $this->assertStringContainsString('margin: 22mm', $result->html);

        // Total elements: 1 (pageHeader) + 50 (detail) + 1 (pageFooter) = 52
        $this->assertEquals(52, $result->elementCount);
    }

    #[Test]
    public function it_produces_css_flow_html_for_dompdf_pagination(): void
    {
        // This test verifies the key CSS properties that enable DomPDF pagination:
        // - Flowing content is in .pdf-band divs (position: relative, explicit height)
        // - Fixed content uses .pdf-fixed (position: fixed)
        // - Body has margin for fixed headers/footers
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 15,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 0, 'y' => 0, 'width' => 210, 'height' => 12, 'content' => ['text' => 'HEADER'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'datasourceId' => null, 'collectionPath' => 'items',
                'elements' => [
                    ['type' => 'text', 'x' => 0, 'y' => 0, 'width' => 210, 'height' => 8, 'content' => ['text' => '{{label}}'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 15,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 0, 'y' => 0, 'width' => 210, 'height' => 12, 'content' => ['text' => 'FOOTER'], 'styles' => []],
                ],
            ],
        ];

        $data = ['items' => [['label' => 'Row1'], ['label' => 'Row2']]];

        $result = $this->engine->render($config, $data);

        // CSS must include .pdf-band with position: relative
        $this->assertStringContainsString('.pdf-band {', $result->html);
        $this->assertStringContainsString('position: relative', $result->html);

        // CSS must include .pdf-fixed with position: fixed (for pageFooter)
        $this->assertStringContainsString('.pdf-fixed {', $result->html);
        $this->assertStringContainsString('position: fixed !important', $result->html);

        // Body margin: top=25mm (10+15 for header), bottom=25mm (10+15 for footer)
        $this->assertStringContainsString('margin: 25mm', $result->html);
        $this->assertStringContainsString('25mm 10mm', $result->html);

        // pageHeader is fixed, detail items are flowing bands
        // 2 detail = 2 band containers
        $this->assertEquals(2, substr_count($result->html, 'class="pdf-band"'));

        // Both pageHeader and pageFooter get pdf-fixed class
        $this->assertStringContainsString('class="pdf-element pdf-fixed"', $result->html);
    }

    // ── Disabled bands ──

    #[Test]
    public function it_skips_disabled_band_when_rendering(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'enabled' => true,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Visible Title'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageHeader', 'type' => 'pageHeader', 'anchor' => 'top', 'height' => 12,
                'enabled' => false,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 2, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Hidden Header'], 'styles' => []],
                ],
            ],
            [
                'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'height' => 10,
                'enabled' => true,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Visible Detail'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // Enabled bands render their content
        $this->assertStringContainsString('Visible Title', $result->html);
        $this->assertStringContainsString('Visible Detail', $result->html);
        // Disabled band content should NOT appear
        $this->assertStringNotContainsString('Hidden Header', $result->html);
        // Element count: only enabled bands contribute
        $this->assertEquals(2, $result->elementCount);
    }

    #[Test]
    public function it_skips_disabled_fixed_band(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                'enabled' => true,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Title'], 'styles' => []],
                ],
            ],
            [
                'id' => 'pageFooter', 'type' => 'pageFooter', 'anchor' => 'bottom', 'height' => 12,
                'enabled' => false,
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 1, 'width' => 190, 'height' => 8, 'content' => ['text' => 'Hidden Footer'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);

        // Enabled band renders
        $this->assertStringContainsString('Title', $result->html);
        // Disabled fixed band should NOT appear
        $this->assertStringNotContainsString('Hidden Footer', $result->html);
        // Fixed band enabled by default (no enabled=false set)
        $this->assertEquals(1, $result->elementCount);
    }

    #[Test]
    public function it_defaults_enabled_to_true_when_not_set(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            [
                'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'height' => 20,
                // No 'enabled' key — should default to true
                'datasourceId' => null, 'collectionPath' => null,
                'elements' => [
                    ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Default Enabled'], 'styles' => []],
                ],
            ],
        ];

        $result = $this->engine->render($config);
        $this->assertStringContainsString('Default Enabled', $result->html);
        $this->assertEquals(1, $result->elementCount);
    }

    /**
     * Helper: extract the HTML element div containing a specific text.
     */
    private function extractElementHtml(string $html, string $text): string
    {
        // Find the div that contains the text
        $pattern = '/<div[^>]*>' . preg_quote($text, '/') . '<\/div>/';
        if (preg_match($pattern, $html, $matches)) {
            return $matches[0];
        }
        return '';
    }
}