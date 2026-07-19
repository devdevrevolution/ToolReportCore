<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Primitives;

use Com\Tecnick\Pdf\Graph\Draw;
use Com\Tecnick\Pdf\Page\Page;
use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Primitives\Shape;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class ShapeTest extends TestCase
{
    // ── getDimensions: line ──

    #[Test]
    public function getDimensions_returns_bounding_box_for_line(): void
    {
        $shape = new Shape('line');
        $shape->setX1(10);
        $shape->setY1(20);
        $shape->setX2(50);
        $shape->setY2(80);

        $dims = $shape->getDimensions();

        $this->assertSame(40.0, $dims['w']);
        $this->assertSame(60.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_handles_reversed_line_coordinates(): void
    {
        $shape = new Shape('line');
        $shape->setX1(50);
        $shape->setY1(80);
        $shape->setX2(10);
        $shape->setY2(20);

        $dims = $shape->getDimensions();

        $this->assertSame(40.0, $dims['w']);
        $this->assertSame(60.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_uses_minimum_width_for_vertical_line(): void
    {
        $shape = new Shape('line');
        $shape->setX1(10);
        $shape->setX2(10);
        $shape->setY1(20);
        $shape->setY2(80);
        $shape->setStrokeWidth(0.5);

        $dims = $shape->getDimensions();

        $this->assertSame(0.1, $dims['w']);
        $this->assertSame(60.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_caches_results(): void
    {
        $shape = new Shape('line');
        $shape->setX1(10);
        $shape->setY1(20);
        $shape->setX2(50);
        $shape->setY2(80);

        $first = $shape->getDimensions();
        $second = $shape->getDimensions();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function getDimensions_invalidates_cache_on_setter(): void
    {
        $shape = new Shape('line');
        $shape->setX1(0);
        $shape->setY1(0);
        $shape->setX2(100);
        $shape->setY2(100);

        $first = $shape->getDimensions();
        $this->assertSame(100.0, $first['w']);

        $shape->setX2(200);
        $second = $shape->getDimensions();
        $this->assertSame(200.0, $second['w']);
    }

    // ── getDimensions: rect ──

    #[Test]
    public function getDimensions_returns_configured_size_for_rect(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(80);
        $shape->setHeight(40);

        $dims = $shape->getDimensions();

        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(40.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_defaults_to_minimum_for_rect(): void
    {
        $shape = new Shape('rect');

        $dims = $shape->getDimensions();

        // Defaults to 40×20 when width/height are unset
        $this->assertEquals(40.0, $dims['w']);
        $this->assertEquals(20.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_explicit_width_is_respected_over_max_width(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(50);
        $shape->setMaxWidth(100);

        $dims = $shape->getDimensions();

        $this->assertSame(50.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_explicit_height_is_respected_over_max_height(): void
    {
        $shape = new Shape('rect');
        $shape->setHeight(30);
        $shape->setMaxHeight(100);

        $dims = $shape->getDimensions();

        $this->assertSame(30.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_max_width_used_when_explicit_width_not_set(): void
    {
        $shape = new Shape('rect');
        $shape->setMaxWidth(80);

        $dims = $shape->getDimensions();

        $this->assertSame(80.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_max_height_used_when_explicit_height_not_set(): void
    {
        $shape = new Shape('rect');
        $shape->setMaxHeight(60);

        $dims = $shape->getDimensions();

        $this->assertSame(60.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_explicit_width_is_capped_by_max_width(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(100);
        $shape->setMaxWidth(50);

        $dims = $shape->getDimensions();

        $this->assertSame(50.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_explicit_height_is_capped_by_max_height(): void
    {
        $shape = new Shape('rect');
        $shape->setHeight(100);
        $shape->setMaxHeight(40);

        $dims = $shape->getDimensions();

        $this->assertSame(40.0, $dims['h']);
    }

    // ── getDimensions: circle ──

    #[Test]
    public function getDimensions_returns_configured_size_for_circle(): void
    {
        $shape = new Shape('circle');
        $shape->setWidth(30);
        $shape->setHeight(30);

        $dims = $shape->getDimensions();

        $this->assertSame(30.0, $dims['w']);
        $this->assertSame(30.0, $dims['h']);
    }

    // ── getDimensions: ellipse ──

    #[Test]
    public function getDimensions_returns_configured_size_for_ellipse(): void
    {
        $shape = new Shape('ellipse');
        $shape->setWidth(60);
        $shape->setHeight(40);

        $dims = $shape->getDimensions();

        $this->assertSame(60.0, $dims['w']);
        $this->assertSame(40.0, $dims['h']);
    }

    // ── Render: line ──

    #[Test]
    public function render_draws_line_via_graph(): void
    {
        $shape = new Shape('line');
        $shape->setX1(5);
        $shape->setY1(10);
        $shape->setX2(100);
        $shape->setY2(50);

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->with(15.0, 30.0, 110.0, 70.0, $this->isArray())
            ->willReturn('l ');

        $page->expects($this->once())
            ->method('addContent')
            ->with('q l  Q');

        $shape->render($pdf, 10.0, 20.0);
    }

    // ── Render: rect ──

    #[Test]
    public function render_draws_rect_via_graph(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(80);
        $shape->setHeight(40);

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getRect')
            ->with(10.0, 20.0, 80.0, 40.0, 'S', $this->isArray())
            ->willReturn('r ');

        $page->expects($this->once())
            ->method('addContent')
            ->with('q r  Q');

        $shape->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_draws_rect_with_fill_when_fill_color_set(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(80);
        $shape->setHeight(40);
        $shape->setFillColor('#00FF00');

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getRect')
            ->with(10.0, 20.0, 80.0, 40.0, 'FD', $this->callback(function (array $style): bool {
                return isset($style['fillColor']) && $style['fillColor'] === '#00FF00';
            }))
            ->willReturn('r ');

        $shape->render($pdf, 10.0, 20.0);
    }

    // ── Render: rounded rect ──

    #[Test]
    public function render_draws_rounded_rect_when_border_radius_set(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(80);
        $shape->setHeight(40);
        $shape->setBorderRadius(5);

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getRoundedRect')
            ->with(10.0, 20.0, 80.0, 40.0, 5.0, 5.0, '1111', 'S', $this->isArray())
            ->willReturn('rr ');

        $page->expects($this->once())
            ->method('addContent')
            ->with('q rr  Q');

        $shape->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_clamps_border_radius_to_half_of_smallest_dimension(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(20);
        $shape->setHeight(10);
        $shape->setBorderRadius(999); // way too large

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getRoundedRect')
            ->with(10.0, 20.0, 20.0, 10.0, 5.0, 5.0, '1111', 'S', $this->isArray())
            ->willReturn('rr ');

        $shape->render($pdf, 10.0, 20.0);
    }

    // ── Render: circle ──

    #[Test]
    public function render_draws_circle_via_graph(): void
    {
        $shape = new Shape('circle');
        $shape->setWidth(30);
        $shape->setHeight(30);

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getCircle')
            ->with(25.0, 35.0, 15.0, 0, 360, 'S', $this->isArray())
            ->willReturn('c ');

        $page->expects($this->once())
            ->method('addContent')
            ->with('q c  Q');

        $shape->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_draws_circle_with_fill(): void
    {
        $shape = new Shape('circle');
        $shape->setWidth(30);
        $shape->setHeight(30);
        $shape->setFillColor('#FF0000');

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getCircle')
            ->with(25.0, 35.0, 15.0, 0, 360, 'FD', $this->callback(function (array $style): bool {
                return isset($style['fillColor']) && $style['fillColor'] === '#FF0000';
            }))
            ->willReturn('c ');

        $shape->render($pdf, 10.0, 20.0);
    }

    // ── Render: ellipse ──

    #[Test]
    public function render_draws_ellipse_via_graph(): void
    {
        $shape = new Shape('ellipse');
        $shape->setWidth(60);
        $shape->setHeight(40);

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getEllipse')
            ->with(40.0, 40.0, 30.0, 20.0, 0, 0, 360, 'S', $this->isArray())
            ->willReturn('e ');

        $page->expects($this->once())
            ->method('addContent')
            ->with('q e  Q');

        $shape->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_draws_ellipse_with_fill(): void
    {
        $shape = new Shape('ellipse');
        $shape->setWidth(60);
        $shape->setHeight(40);
        $shape->setFillColor('#0000FF');

        [$pdf, $graph, $page] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getEllipse')
            ->with(40.0, 40.0, 30.0, 20.0, 0, 0, 360, 'FD', $this->callback(function (array $style): bool {
                return isset($style['fillColor']) && $style['fillColor'] === '#0000FF';
            }))
            ->willReturn('e ');

        $shape->render($pdf, 10.0, 20.0);
    }

    // ── Style propagation ──

    #[Test]
    public function render_passes_stroke_width_in_style(): void
    {
        $shape = new Shape('line');
        $shape->setStrokeWidth(2.0);

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(),
                $this->callback(function (array $style): bool {
                    return $style['lineWidth'] === 2.0;
                })
            )
            ->willReturn('l ');

        $shape->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function render_passes_color_in_style(): void
    {
        $shape = new Shape('line');
        $shape->setColor('#FF0000');

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(),
                $this->callback(function (array $style): bool {
                    return isset($style['lineColor']) && $style['lineColor'] === '#FF0000';
                })
            )
            ->willReturn('l ');

        $shape->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function render_passes_fill_color_in_style(): void
    {
        $shape = new Shape('rect');
        $shape->setFillColor('#00FF00');

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getRect')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), 'FD',
                $this->callback(function (array $style): bool {
                    return isset($style['fillColor']) && $style['fillColor'] === '#00FF00';
                })
            )
            ->willReturn('r ');

        $shape->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function render_sets_dashed_line_style(): void
    {
        $shape = new Shape('line');
        $shape->setLineStyle('dashed');

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(),
                $this->callback(function (array $style): bool {
                    return isset($style['dashArray']) && $style['dashArray'] === [5, 5];
                })
            )
            ->willReturn('l ');

        $shape->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function render_sets_dotted_line_style(): void
    {
        $shape = new Shape('line');
        $shape->setLineStyle('dotted');

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(),
                $this->callback(function (array $style): bool {
                    return isset($style['dashArray']) && $style['dashArray'] === [2, 2];
                })
            )
            ->willReturn('l ');

        $shape->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function render_defaults_to_solid_line_style(): void
    {
        $shape = new Shape('line');

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(),
                $this->callback(function (array $style): bool {
                    return !isset($style['dashArray']);
                })
            )
            ->willReturn('l ');

        $shape->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function render_default_shape_type_is_line(): void
    {
        $shape = new Shape();

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getLine')
            ->willReturn('l ');

        $shape->render($pdf, 0.0, 0.0);
    }

    // ── Margin ──

    #[Test]
    public function render_offsets_by_margin(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(80);
        $shape->setHeight(40);
        $shape->setMargin(['top' => 5, 'left' => 10, 'right' => 0, 'bottom' => 0]);

        [$pdf, $graph] = $this->createPdfMock();
        $graph->expects($this->once())
            ->method('getRect')
            ->with(20.0, 25.0, 80.0, 40.0, 'S', $this->isArray())
            ->willReturn('r ');

        $shape->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function getDimensions_includes_margin(): void
    {
        $shape = new Shape('rect');
        $shape->setWidth(80);
        $shape->setHeight(40);
        $shape->setMargin(['top' => 5, 'right' => 10, 'bottom' => 5, 'left' => 10]);

        $dims = $shape->getDimensions();

        $this->assertSame(100.0, $dims['w']); // 80 + 10 + 10
        $this->assertSame(50.0, $dims['h']);  // 40 + 5 + 5
    }

    // ── Helpers ──

    /**
     * @return array{0: Tcpdf&\PHPUnit\Framework\MockObject\MockObject, 1: Draw&\PHPUnit\Framework\MockObject\MockObject, 2: Page&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function createPdfMock(): array
    {
        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        return [$pdf, $graph, $page];
    }
}
