<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Containers\Table;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class TableRow implements Component
{
    /** @var TableCell[] */
    private array $cells;

    /** @var float[] */
    private array $column_widths;

    /**
     * @param TableCell[] $cells
     * @param float[]     $column_widths
     */
    public function __construct(array $cells, array $column_widths)
    {
        $this->cells = $cells;
        $this->column_widths = $column_widths;
    }

    /**
     * @return array{w: float, h: float}
     */
    public function getDimensions(): array
    {
        $max_height = 0.0;

        foreach ($this->cells as $cell) {
            $dims = $cell->getDimensions();
            $max_height = max($max_height, $dims['h']);
        }

        return [
            'w' => array_sum($this->column_widths),
            'h' => $max_height,
        ];
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $cell_x = $x;

        foreach ($this->cells as $i => $cell) {
            $cell->render($pdf, $cell_x, $y);
            $cell_x += $this->column_widths[$i];
        }

        $dims = $this->getDimensions();

        // Draw horizontal line at bottom of the row
        $this->drawHorizontalLine($pdf, $x, $y + $dims['h'], $dims['w']);

        // Draw vertical lines between columns
        $col_x = $x;
        $last_index = count($this->column_widths) - 1;

        for ($i = 0; $i < $last_index; $i++) {
            $col_x += $this->column_widths[$i];
            $this->drawVerticalLine($pdf, $col_x, $y, $dims['h']);
        }
    }

    private function drawHorizontalLine(Tcpdf $pdf, float $x, float $y, float $width): void
    {
        $style = $this->gridLineStyle();
        $line = $pdf->graph->getLine($x, $y, $x + $width, $y, $style);
        $pdf->page->addContent($line);
    }

    private function drawVerticalLine(Tcpdf $pdf, float $x, float $y, float $height): void
    {
        $style = $this->gridLineStyle();
        $line = $pdf->graph->getLine($x, $y, $x, $y + $height, $style);
        $pdf->page->addContent($line);
    }

    /**
     * @return array<string, mixed>
     */
    private function gridLineStyle(): array
    {
        return [
            'lineWidth' => 0.2,
            'lineColor' => [208, 208, 208], // #D0D0D0
        ];
    }

    public function setMaxWidth(float $maxWidth): void
    {
        // TableRow doesn't use maxWidth
    }

    public function setMaxHeight(float $maxHeight): void
    {
        // TableRow doesn't use maxHeight
    }
}
