<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Primitives;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class Image implements Component
{
    private string $url = '';
    private ?float $width = null;
    private ?float $height = null;
    private string $object_fit = 'contain';
    private float $opacity = 1.0;

    // Legacy border properties (kept for backward compat)
    private float $border_radius = 0;
    private float $border_width = 0;
    private string $border_color = '#000000';

    // Shape properties (like Shape component)
    private string $shape_type = 'rect';
    private ?string $fill_color = null;
    private ?string $stroke_color = null;
    private float $stroke_width = 0;
    private string $line_style = 'solid';

    /** @var array{top: float, right: float, bottom: float, left: float} */
    private array $margin = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

    /** @var array<string, mixed> */
    private array $global_data = [];

    /** @var array<string, mixed> */
    private array $local_data = [];

    private ?float $max_width = null;

    private ?float $max_height = null;

    // ── Setters ──────────────────────────────────

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setWidth(float $width): void
    {
        $this->width = $width;
    }

    public function setHeight(float $height): void
    {
        $this->height = $height;
    }

    public function setObjectFit(string $object_fit): void
    {
        $this->object_fit = $object_fit;
    }

    public function setOpacity(float $opacity): void
    {
        $this->opacity = max(0, min(1, $opacity));
    }

    public function setBorderRadius(float $border_radius): void
    {
        $this->border_radius = $border_radius;
    }

    public function setBorderWidth(float $border_width): void
    {
        $this->border_width = max(0, $border_width);
    }

    public function setBorderColor(string $border_color): void
    {
        $this->border_color = $border_color;
    }

    public function setShapeType(string $shape_type): void
    {
        $this->shape_type = $shape_type;
    }

    public function setFillColor(?string $fill_color): void
    {
        $this->fill_color = $fill_color;
    }

    public function setStrokeColor(?string $stroke_color): void
    {
        $this->stroke_color = $stroke_color;
    }

    public function setStrokeWidth(float $stroke_width): void
    {
        $this->stroke_width = max(0, $stroke_width);
    }

    public function setLineStyle(string $line_style): void
    {
        $this->line_style = $line_style;
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

    /** @param array<string, mixed> $data */
    public function setGlobalData(array $data): void
    {
        $this->global_data = $data;
    }

    /** @param array<string, mixed> $data */
    public function setLocalData(array $data): void
    {
        $this->local_data = $data;
    }

    // ── Component interface ──────────────────────

    public function getDimensions(): array
    {
        $w = $this->effectiveWidth();
        $h = $this->effectiveHeight();

        return [
            'w' => $w + $this->margin['left'] + $this->margin['right'],
            'h' => $h + $this->margin['top'] + $this->margin['bottom'],
        ];
    }

    /**
     * Determine the effective width.
     * Explicit width is respected; max_width acts as a cap/fallback.
     */
    private function effectiveWidth(): float
    {
        if ($this->width !== null && $this->max_width !== null) {
            return min($this->width, $this->max_width);
        }

        return $this->width ?? $this->max_width ?? 0;
    }

    /**
     * Determine the effective height.
     * Explicit height is respected; max_height acts as a cap/fallback.
     */
    private function effectiveHeight(): float
    {
        if ($this->height !== null && $this->max_height !== null) {
            return min($this->height, $this->max_height);
        }

        return $this->height ?? $this->max_height ?? 0;
    }

    public function setMaxWidth(float $maxWidth): void
    {
        $this->max_width = $maxWidth;
    }

    public function setMaxHeight(float $maxHeight): void
    {
        $this->max_height = $maxHeight;
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $resolved_url = $this->interpolate($this->url);

        // Encode pipe characters that break tc-lib-pdf URL parsing
        $resolved_url = str_replace('|', '%7C', $resolved_url);

        // If URL is empty or still contains unresolved placeholders, render nothing
        if ($resolved_url === '' || preg_match('/\{\{/', $resolved_url)) {
            return;
        }

        $content_x = $x + $this->margin['left'];
        $content_y = $y + $this->margin['top'];

        // The configured box dimensions — the allocated space for this image.
        // Explicit width/height are respected; max_width/max_height act as caps/fallbacks.
        $effective_w = $this->effectiveWidth();
        $effective_h = $this->effectiveHeight();
        $box_w = $effective_w > 0 ? $effective_w : null;
        $box_h = $effective_h > 0 ? $effective_h : null;

        try {
            // 1. Import image
            $iid = $pdf->image->add($resolved_url);

            // 2. Get intrinsic dimensions
            $key = $pdf->image->getKey($resolved_url);
            $intrinsic = $pdf->image->getImageDimensionsByKey($key);

            // 3. Calculate content dimensions based on objectFit
            [$draw_w, $draw_h] = $this->calculateDrawDimensions(
                $intrinsic['width'],
                $intrinsic['height'],
                $box_w,
                $box_h,
            );

            // 4. Center content within the box
            if ($box_w !== null && $box_h !== null) {
                $offset_x = ($box_w - $draw_w) / 2;
                $offset_y = ($box_h - $draw_h) / 2;
            } else {
                $offset_x = 0.0;
                $offset_y = 0.0;
            }

            $img_x = $content_x + $offset_x;
            $img_y = $content_y + $offset_y;

            // 5. Isolate image rendering so fill/stroke/opacity do not leak
            // into subsequent elements (e.g. Label text color).
            $pdf->page->addContent('q');

            // 6. Apply opacity
            if ($this->opacity < 1) {
                $pdf->setAlpha($this->opacity);
            }

            // 7. Render fill background + clip to shape
            $needs_clip = $this->shape_type !== 'rect' || $this->border_radius > 0;
            if ($needs_clip) {
                $this->renderShapeFillAndClip($pdf, $content_x, $content_y, $box_w ?? $draw_w, $box_h ?? $draw_h);
            } elseif ($this->fill_color !== null && $this->fill_color !== 'none' && $this->fill_color !== '') {
                // Simple rect fill (no clipping needed) — use graph library
                $pdf->page->addContent($this->buildGraphPath(
                    $pdf->graph,
                    $content_x, $content_y, $box_w ?? $draw_w, $box_h ?? $draw_h,
                    'f',
                    ['fillColor' => $this->fill_color],
                ));
            }

            // 8. Render image
            $pageH = $pdf->toUnit($pdf->page->getPage()['pheight']);
            $img_out = $pdf->image->getSetImage(
                $iid,
                $img_x,
                $img_y,
                $draw_w,
                $draw_h,
                $pageH,
            );
            $pdf->page->addContent($img_out);

            // 9. Render stroke (new shape stroke OR legacy border) inside the
            // same graphics state so its color is also isolated.
            $effective_stroke_width = $this->stroke_width > 0 ? $this->stroke_width : $this->border_width;
            $effective_stroke_color = $this->stroke_color ?? $this->border_color;
            if ($effective_stroke_width > 0) {
                $this->renderShapeStroke($pdf, $content_x, $content_y, $box_w ?? $draw_w, $box_h ?? $draw_h, $effective_stroke_width, $effective_stroke_color);
            }

            // 10. Restore graphics state (removes clip and color changes)
            $pdf->page->addContent('Q');

            // 11. Restore opacity
            if ($this->opacity < 1) {
                $pdf->setAlpha(1.0);
            }
        } catch (\Throwable $e) {
            \Log::warning('Image render failed', [
                'url' => $resolved_url,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return;
        }
    }

    // ── Interpolation (same pattern as Label) ────

    private function interpolate(string $input): string
    {
        $result = preg_replace_callback(
            '/\{\{\s*(\w+(?:\[\])?(?:\.\w+(?:\[\])?)*)\s*\}\}/',
            function (array $matches): string {
                $path = $matches[1];

                $value = $this->resolvePath($this->local_data, $path);
                if ($value !== null) {
                    return $this->stringify($value);
                }

                $value = $this->resolvePath($this->global_data, $path);
                if ($value !== null) {
                    return $this->stringify($value);
                }

                return $matches[0];
            },
            $input,
        );

        return $result !== null ? $result : $input;
    }

    /** @param array<string, mixed> $data */
    private function resolvePath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        return $this->resolveSegments($data, $segments);
    }

    /** @param array<string, mixed>|mixed $current */
    private function resolveSegments(mixed $current, array $segments): mixed
    {
        while (count($segments) > 0) {
            $segment = array_shift($segments);

            if (str_ends_with($segment, '[]')) {
                $key = substr($segment, 0, -2);
                if (!is_array($current) || !array_key_exists($key, $current)) {
                    return null;
                }
                $base = $current[$key];
                if (!is_array($base)) {
                    return null;
                }
                $results = [];
                foreach ($base as $item) {
                    $resolved = $this->resolveSegments($item, $segments);
                    if ($resolved !== null) {
                        $results[] = $resolved;
                    }
                }

                return $results !== [] ? $results : null;
            }

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => $this->stringify($v), $value));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    // ── Dimension calculation ────────────────────

    private function calculateDrawDimensions(int $srcW, int $srcH, ?float $boxW, ?float $boxH): array
    {
        $srcWmm = $srcW * 25.4 / 96;
        $srcHmm = $srcH * 25.4 / 96;

        if ($boxW === null && $boxH === null) {
            return [$srcWmm, $srcHmm];
        }

        if ($boxW !== null && $boxH === null) {
            $ratio = $boxW / $srcWmm;
            return [$boxW, $srcHmm * $ratio];
        }
        if ($boxW === null && $boxH !== null) {
            $ratio = $boxH / $srcHmm;
            return [$srcWmm * $ratio, $boxH];
        }

        return match ($this->object_fit) {
            'fill' => [$boxW, $boxH],
            'cover' => $this->scaleToCover($srcWmm, $srcHmm, $boxW, $boxH),
            default => $this->scaleToFit($srcWmm, $srcHmm, $boxW, $boxH),
        };
    }

    private function scaleToFit(float $srcW, float $srcH, float $boxW, float $boxH): array
    {
        $ratio = min($boxW / $srcW, $boxH / $srcH);
        return [$srcW * $ratio, $srcH * $ratio];
    }

    private function scaleToCover(float $srcW, float $srcH, float $boxW, float $boxH): array
    {
        $ratio = max($boxW / $srcW, $boxH / $srcH);
        return [$srcW * $ratio, $srcH * $ratio];
    }

    // ── Graph-based shape rendering ─────────────

    /**
     * Build a shape path using tc-lib-pdf-graph (correct Bezier arcs).
     *
     * Coordinates are in mm, top-left origin — the graph library handles
     * the conversion to PDF points internally.
     */
    private function buildGraphPath(
        object $graph,
        float $x,
        float $y,
        float $w,
        float $h,
        string $mode,
        array $style,
    ): string {
        $rx = $this->border_radius > 0 ? min($this->border_radius, $w / 2, $h / 2) : 0;
        $ry = $rx;

        return match ($this->shape_type) {
            'circle' => $graph->getCircle(
                $x + min($w, $h) / 2,
                $y + min($w, $h) / 2,
                min($w, $h) / 2,
                0,
                360,
                $mode,
                $style,
            ),
            'ellipse' => $graph->getEllipse(
                $x + $w / 2,
                $y + $h / 2,
                $w / 2,
                $h / 2,
                0,
                0,
                360,
                $mode,
                $style,
            ),
            default => $rx > 0
                ? $graph->getRoundedRect($x, $y, $w, $h, $rx, $ry, '1111', $mode, $style)
                : $graph->getRect($x, $y, $w, $h, $mode, $style),
        };
    }

    // ── Fill + Clip rendering ────────────────────

    /**
     * Render fill background and clip to shape (for non-rect shapes or rounded rect).
     * Uses tc-lib-pdf-graph for correct Bezier arc rendering.
     */
    private function renderShapeFillAndClip(Tcpdf $pdf, float $x, float $y, float $w, float $h): void
    {
        $graph = $pdf->graph;

        // 1. Fill background
        $fillColor = ($this->fill_color !== null && $this->fill_color !== 'none' && $this->fill_color !== '')
            ? $this->fill_color
            : '#FFFFFF';

        $pdf->page->addContent($this->buildGraphPath($graph, $x, $y, $w, $h, 'f', [
            'fillColor' => $fillColor,
        ]));

        // 2. Clip to shape (no paint — path defines clip region, stays open until outer Q)
        $pdf->page->addContent($this->buildGraphPath($graph, $x, $y, $w, $h, 'W n', []));
    }

    // ── Stroke rendering ────────────────────────

    /**
     * Render shape stroke (border).
     * Uses tc-lib-pdf-graph for correct Bezier arc rendering.
     */
    private function renderShapeStroke(Tcpdf $pdf, float $x, float $y, float $w, float $h, float $stroke_width, string $stroke_color): void
    {
        $graph = $pdf->graph;

        $style = [
            'lineColor' => $stroke_color,
            'lineWidth' => $stroke_width,
        ];

        if ($this->line_style === 'dashed') {
            $style['dashArray'] = [5, 3];
        } elseif ($this->line_style === 'dotted') {
            $style['dashArray'] = [1, 2];
        }

        $pdf->page->addContent($this->buildGraphPath($graph, $x, $y, $w, $h, 'S', $style));
    }

}
