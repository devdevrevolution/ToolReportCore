<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Containers\Table;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class Table implements Component
{
    /** @var TableRow[] */
    private array $rows;

    /** @var float[] */
    private array $column_widths;

    /** @var array{top: float, right: float, bottom: float, left: float} */
    private array $margin = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

    /**
     * @param TableRow[] $rows
     * @param float[]    $column_widths
     */
    public function __construct(array $rows, array $column_widths)
    {
        $this->rows = $rows;
        $this->column_widths = $column_widths;
    }

    /**
     * @param array{top?: float, right?: float, bottom?: float, left?: float} $margin
     */
    public function setMargin(array $margin): void
    {
        $this->margin = [
            'top'    => (float) ($margin['top'] ?? 0),
            'right'  => (float) ($margin['right'] ?? 0),
            'bottom' => (float) ($margin['bottom'] ?? 0),
            'left'   => (float) ($margin['left'] ?? 0),
        ];
    }

    /**
     * @return array{w: float, h: float}
     */
    public function getDimensions(): array
    {
        $total_height = 0.0;

        foreach ($this->rows as $row) {
            $dims = $row->getDimensions();
            $total_height += $dims['h'];
        }

        // Add margin to total dimensions
        $width = array_sum($this->column_widths) + $this->margin['left'] + $this->margin['right'];
        $height = $total_height + $this->margin['top'] + $this->margin['bottom'];

        return [
            'w' => $width,
            'h' => $height,
        ];
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $content_x = $x + $this->margin['left'];
        $content_y = $y + $this->margin['top'];
        $current_y = $content_y;

        foreach ($this->rows as $row) {
            $row->render($pdf, $content_x, $current_y);
            $row_dims = $row->getDimensions();
            $current_y += $row_dims['h'];
        }

        // Calculate content dimensions (without margin) for border drawing
        $content_width = array_sum($this->column_widths);
        $content_height = 0.0;
        foreach ($this->rows as $row) {
            $content_height += $row->getDimensions()['h'];
        }

        // Draw the outer border rectangle around the entire table
        $border_style = [
            'lineWidth' => 0.5,
            'lineColor' => [180, 180, 180],
        ];

        $border = $pdf->graph->getRect(
            $content_x,
            $content_y,
            $content_width,
            $content_height,
            '',
            $border_style
        );
        $pdf->page->addContent($border);

        // Header row (first row if present) gets a slightly darker bottom border
        if (count($this->rows) > 0) {
            $header_dims = $this->rows[0]->getDimensions();
            $header_line_style = [
                'lineWidth' => 0.5,
                'lineColor' => [150, 150, 150],
            ];

            $header_line = $pdf->graph->getLine(
                $content_x,
                $content_y + $header_dims['h'],
                $content_x + $content_width,
                $content_y + $header_dims['h'],
                $header_line_style
            );
            $pdf->page->addContent($header_line);
        }
    }

    public function setMaxWidth(float $maxWidth): void
    {
        // Table doesn't use maxWidth
    }

    public function setMaxHeight(float $maxHeight): void
    {
        // Table doesn't use maxHeight
    }
}
