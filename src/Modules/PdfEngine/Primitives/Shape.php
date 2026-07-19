<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Primitives;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class Shape implements Component
{
    private string $shape_type;
    private float $x1 = 0;
    private float $y1 = 0;
    private float $x2 = 0;
    private float $y2 = 0;
    private float $width = 0;
    private float $height = 0;
    private ?string $color = null;
    private ?string $fill_color = null;
    private float $stroke_width = 1;
    private string $line_style = 'solid';
    private float $border_radius = 0;

    /** @var array{w: float, h: float}|null */
    private ?array $cached_dimensions = null;

    /** @var array{top: float, right: float, bottom: float, left: float} */
    private array $margin = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

    private ?float $max_width = null;

    private ?float $max_height = null;

    public function __construct(string $shape_type = 'line')
    {
        $this->shape_type = $shape_type;
    }

    // ── Setters ──────────────────────────────────────────

    public function setX1(float $x1): void
    {
        $this->x1 = $x1;
        $this->cached_dimensions = null;
    }

    public function setY1(float $y1): void
    {
        $this->y1 = $y1;
        $this->cached_dimensions = null;
    }

    public function setX2(float $x2): void
    {
        $this->x2 = $x2;
        $this->cached_dimensions = null;
    }

    public function setY2(float $y2): void
    {
        $this->y2 = $y2;
        $this->cached_dimensions = null;
    }

    public function setWidth(float $width): void
    {
        $this->width = $width;
        $this->cached_dimensions = null;
    }

    public function setHeight(float $height): void
    {
        $this->height = $height;
        $this->cached_dimensions = null;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function setFillColor(?string $fill_color): void
    {
        $this->fill_color = $fill_color;
    }

    public function setStrokeWidth(float $stroke_width): void
    {
        $this->stroke_width = $stroke_width;
    }

    public function setLineStyle(string $line_style): void
    {
        $this->line_style = $line_style;
    }

    public function setBorderRadius(float $border_radius): void
    {
        $this->border_radius = $border_radius;
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
        $this->cached_dimensions = null;
    }

    // ── Dimensions ───────────────────────────────────────

    /**
     * @return array{w: float, h: float}
     */
    public function getDimensions(): array
    {
        if ($this->cached_dimensions !== null) {
            return $this->cached_dimensions;
        }

        $base_w = $this->effectiveWidth();
        $base_h = $this->effectiveHeight();

        $this->cached_dimensions = [
            'w' => $base_w + $this->margin['left'] + $this->margin['right'],
            'h' => $base_h + $this->margin['top'] + $this->margin['bottom'],
        ];

        return $this->cached_dimensions;
    }

    // ── Rendering (native PDF via tc-lib-pdf-graph) ─────

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $content_x = $x + $this->margin['left'];
        $content_y = $y + $this->margin['top'];

        $graph = $pdf->graph;
        $style = $this->buildStyle();
        $mode = $this->getPaintMode();

        $pdfContent = match ($this->shape_type) {
            'line'    => $this->buildLine($graph, $content_x, $content_y, $style),
            'ellipse' => $this->buildEllipse($graph, $content_x, $content_y, $style, $mode),
            'circle'  => $this->buildCircle($graph, $content_x, $content_y, $style, $mode),
            default   => $this->buildRect($graph, $content_x, $content_y, $style, $mode),
        };

        // Isolate shape rendering so fill/stroke colors do not leak into
        // subsequent elements (e.g. Label text color).
        $pdf->page->addContent('q ' . $pdfContent . ' Q');
    }

    /**
     * Build the style array for tc-lib-pdf-graph Draw methods.
     *
     * @return array<string, mixed>
     */
    private function buildStyle(): array
    {
        $style = [];

        $style['lineWidth'] = $this->stroke_width;
        $style['lineColor'] = $this->color ?? '#333333';

        if ($this->line_style === 'dashed') {
            $style['dashArray'] = [5, 5];
            $style['dashPhase'] = 0;
        } elseif ($this->line_style === 'dotted') {
            $style['dashArray'] = [2, 2];
            $style['dashPhase'] = 0;
        }

        if ($this->fill_color !== null && $this->fill_color !== 'none' && $this->fill_color !== '') {
            $style['fillColor'] = $this->fill_color;
        }

        return $style;
    }

    private function getPaintMode(): string
    {
        return ($this->fill_color !== null && $this->fill_color !== 'none' && $this->fill_color !== '')
            ? 'FD'
            : 'S';
    }

    private function buildLine(object $graph, float $cx, float $cy, array $style): string
    {
        return $graph->getLine(
            $cx + $this->x1,
            $cy + $this->y1,
            $cx + $this->x2,
            $cy + $this->y2,
            $style,
        );
    }

    private function effectiveWidth(): float
    {
        if ($this->shape_type === 'line') {
            return abs($this->x2 - $this->x1) ?: 0.1;
        }

        if ($this->width > 0 && $this->max_width !== null) {
            return min($this->width, $this->max_width);
        }
        if ($this->width > 0) {
            return $this->width;
        }

        return $this->max_width ?? 40;
    }

    private function effectiveHeight(): float
    {
        if ($this->shape_type === 'line') {
            return abs($this->y2 - $this->y1) ?: $this->stroke_width;
        }

        if ($this->height > 0 && $this->max_height !== null) {
            return min($this->height, $this->max_height);
        }
        if ($this->height > 0) {
            return $this->height;
        }

        return $this->max_height ?? 20;
    }

    private function buildRect(object $graph, float $cx, float $cy, array $style, string $mode): string
    {
        $w = $this->effectiveWidth();
        $h = $this->effectiveHeight();

        if ($this->border_radius > 0) {
            $maxR = min($w / 2, $h / 2);
            $rad = min($this->border_radius, $maxR);

            return $graph->getRoundedRect($cx, $cy, $w, $h, $rad, $rad, '1111', $mode, $style);
        }

        return $graph->getRect($cx, $cy, $w, $h, $mode, ['all' => $style]);
    }

    private function buildEllipse(object $graph, float $cx, float $cy, array $style, string $mode): string
    {
        $w = $this->effectiveWidth();
        $h = $this->effectiveHeight();

        return $graph->getEllipse(
            $cx + $w / 2,
            $cy + $h / 2,
            $w / 2,
            $h / 2,
            0,
            0,
            360,
            $mode,
            $style,
        );
    }

    private function buildCircle(object $graph, float $cx, float $cy, array $style, string $mode): string
    {
        $w = $this->effectiveWidth();
        $h = $this->effectiveHeight();
        $rad = min($w, $h) / 2;

        return $graph->getCircle(
            $cx + $w / 2,
            $cy + $h / 2,
            $rad,
            0,
            360,
            $mode,
            $style,
        );
    }

    public function setMaxWidth(float $maxWidth): void
    {
        $this->max_width = $maxWidth;
        $this->cached_dimensions = null;
    }

    public function setMaxHeight(float $maxHeight): void
    {
        $this->max_height = $maxHeight;
        $this->cached_dimensions = null;
    }
}
