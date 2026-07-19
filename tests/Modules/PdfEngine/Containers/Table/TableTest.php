<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Containers\Table;

use Com\Tecnick\Pdf\Graph\Draw;
use Com\Tecnick\Pdf\Page\Page;
use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\Table;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\TableCell;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\TableRow;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;
use Toolreport\Core\Tests\Modules\PdfEngine\CreatesMockComponents;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class TableTest extends TestCase
{
    use CreatesMockComponents;

    // ═══════════════════════════════════════
    //  TableCell
    // ═══════════════════════════════════════

    #[Test]
    public function tableCell_getDimensions_returns_content_height_plus_padding_and_fixed_width(): void
    {
        $content = $this->mockComponent(50.0, 20.0);
        $cell = new TableCell($content, 80.0, 3.0);

        $dims = $cell->getDimensions();

        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(26.0, $dims['h']); // 20 + 2 * 3
    }

    #[Test]
    public function tableCell_getDimensions_defaults_to_2_padding(): void
    {
        $content = $this->mockComponent(40.0, 15.0);
        $cell = new TableCell($content, 60.0);

        $dims = $cell->getDimensions();

        $this->assertSame(60.0, $dims['w']);
        $this->assertSame(19.0, $dims['h']); // 15 + 2 * 2
    }

    #[Test]
    public function tableCell_render_adds_padding_offset_to_content(): void
    {
        $content = $this->createMock(Component::class);
        $cell = new TableCell($content, 80.0, 4.0);

        $pdf = $this->createMock(Tcpdf::class);

        $content
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 14.0, 24.0); // x+4, y+4

        $cell->render($pdf, 10.0, 20.0);
    }

    // ═══════════════════════════════════════
    //  TableRow
    // ═══════════════════════════════════════

    #[Test]
    public function tableRow_getDimensions_returns_max_cell_height_and_sum_of_column_widths(): void
    {
        $col_widths = [80.0, 120.0];
        $cell_1 = new TableCell($this->mockComponent(50.0, 20.0), 80.0);
        $cell_2 = new TableCell($this->mockComponent(70.0, 30.0), 120.0);

        $row = new TableRow([$cell_1, $cell_2], $col_widths);

        $dims = $row->getDimensions();

        $this->assertSame(200.0, $dims['w']); // 80 + 120
        $this->assertSame(34.0, $dims['h']); // max(20+4, 30+4) = 34
    }

    #[Test]
    public function tableRow_render_positions_cells_at_correct_x_and_draws_grid(): void
    {
        $col_widths = [80.0, 120.0];

        $cell_1 = $this->createMock(TableCell::class);
        $cell_2 = $this->createMock(TableCell::class);

        $cell_1->method('getDimensions')->willReturn(['w' => 80.0, 'h' => 20.0]);
        $cell_2->method('getDimensions')->willReturn(['w' => 120.0, 'h' => 20.0]);

        $row = new TableRow([$cell_1, $cell_2], $col_widths);

        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        // Cell 1 at x=10
        $cell_1
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 30.0);

        // Cell 2 at x=10+80=90
        $cell_2
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 90.0, 30.0);

        // Row bottom line + one vertical separator for two columns.
        $graph
            ->expects($this->exactly(2))
            ->method('getLine')
            ->willReturn('l ');

        $page
            ->expects($this->exactly(2))
            ->method('addContent')
            ->with('l ');

        $row->render($pdf, 10.0, 30.0);
    }

    #[Test]
    public function tableRow_getDimensions_with_empty_cells_returns_zero_height(): void
    {
        $row = new TableRow([], [80.0]);
        // Column widths sum to 80 but no cells → height is 0

        $dims = $row->getDimensions();

        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(0.0, $dims['h']);
    }

    // ═══════════════════════════════════════
    //  Table
    // ═══════════════════════════════════════

    #[Test]
    public function table_getDimensions_returns_sum_of_row_heights_and_sum_of_column_widths(): void
    {
        $col_widths = [80.0, 120.0];

        $row_1_cells = [
            new TableCell($this->mockComponent(50.0, 15.0), 80.0),
            new TableCell($this->mockComponent(70.0, 25.0), 120.0),
        ];
        $row_2_cells = [
            new TableCell($this->mockComponent(50.0, 10.0), 80.0),
            new TableCell($this->mockComponent(70.0, 20.0), 120.0),
        ];

        $row_1 = new TableRow($row_1_cells, $col_widths);
        $row_2 = new TableRow($row_2_cells, $col_widths);

        $table = new Table([$row_1, $row_2], $col_widths);

        $dims = $table->getDimensions();

        // Width: 80 + 120 = 200
        $this->assertSame(200.0, $dims['w']);
        // Height: max(15+4, 25+4) + max(10+4, 20+4) = 29 + 24 = 53
        $this->assertSame(53.0, $dims['h']);
    }

    #[Test]
    public function table_render_positions_rows_at_correct_y_and_draws_border(): void
    {
        $col_widths = [80.0, 120.0];

        $row_1 = $this->createMock(TableRow::class);
        $row_1->method('getDimensions')->willReturn(['w' => 200.0, 'h' => 30.0]);

        $row_2 = $this->createMock(TableRow::class);
        $row_2->method('getDimensions')->willReturn(['w' => 200.0, 'h' => 20.0]);

        $table = new Table([$row_1, $row_2], $col_widths);

        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        // Row 1 at y=20
        $row_1
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 20.0);

        // Row 2 at y=20+30=50
        $row_2
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 50.0);

        // Outer border rect: at (10, 20, 200, 50)
        $graph
            ->expects($this->once())
            ->method('getRect')
            ->with(10.0, 20.0, 200.0, 50.0, '', $this->isArray())
            ->willReturn('rect ');

        // Header separator line: at (10, 50, 210, 50)
        $graph
            ->expects($this->once())
            ->method('getLine')
            ->with(10.0, 50.0, 210.0, 50.0, $this->isArray())
            ->willReturn('line ');

        $added_content = [];
        $page
            ->expects($this->exactly(2))
            ->method('addContent')
            ->willReturnCallback(function (string $content) use (&$added_content): void {
                $added_content[] = $content;
            });

        $table->render($pdf, 10.0, 20.0);

        $this->assertSame(['rect ', 'line '], $added_content);
    }

    #[Test]
    public function table_render_with_single_row_draws_border_and_no_header_line_if_no_second_row(): void
    {
        // Actually, header line is drawn for the first row even if it's the only one
        $col_widths = [100.0];
        $row = $this->createMock(TableRow::class);
        $row->method('getDimensions')->willReturn(['w' => 100.0, 'h' => 25.0]);

        $table = new Table([$row], $col_widths);

        $pdf = $this->createMock(Tcpdf::class);
        $graph = $this->createMock(Draw::class);
        $page = $this->createMock(Page::class);
        $pdf->graph = $graph;
        $pdf->page = $page;

        $graph
            ->expects($this->once())
            ->method('getRect')
            ->willReturn('rect ');

        // Header line is drawn even for single row
        $graph
            ->expects($this->once())
            ->method('getLine')
            ->willReturn('line ');

        $page
            ->expects($this->exactly(2))
            ->method('addContent');

        $table->render($pdf, 0.0, 0.0);
    }

    #[Test]
    public function table_getDimensions_with_empty_rows_returns_zero_height(): void
    {
        $table = new Table([], [80.0]);

        $dims = $table->getDimensions();

        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(0.0, $dims['h']);
    }
}
