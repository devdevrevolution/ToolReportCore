<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Primitives;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;
use Toolreport\Core\Modules\PdfEngine\Engine\FontMetrics;

class Label implements Component
{
    private string $text;
    private string $font_family = 'helvetica';
    private float $font_size = 10;
    private string $style = '';
    private ?string $color = null;
    private ?float $width = null;
    private ?float $height = null;
    private ?float $max_width = null;
    private ?float $max_height = null;
    private FontMetrics $font_metrics;

    /** @var array<string, mixed> */
    private array $global_data = [];

    /** @var array<string, mixed> */
    private array $local_data = [];

    /** @var array<int, string> */
    private array $lines = [];

    private float $calculated_width = 0;
    private float $calculated_height = 0;

    /** @var array{top: float, right: float, bottom: float, left: float} */
    private array $margin = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

    public function __construct(string $text, FontMetrics $font_metrics)
    {
        $this->text = $text;
        $this->font_metrics = $font_metrics;
    }

    public function setFontFamily(string $font_family): void
    {
        $this->font_family = $font_family;
    }

    public function setFontSize(float $font_size): void
    {
        $this->font_size = $font_size;
    }

    public function setStyle(string $style): void
    {
        $this->style = $style;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function setWidth(?float $width): void
    {
        $this->width = $width;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function setMaxWidth(float $maxWidth): void
    {
        $this->max_width = $maxWidth;
    }

    public function setMaxHeight(float $maxHeight): void
    {
        $this->max_height = $maxHeight;
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
     * @param array<string, mixed> $data
     */
    public function setGlobalData(array $data): void
    {
        $this->global_data = $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setLocalData(array $data): void
    {
        $this->local_data = $data;
    }

    /**
     * Interpolate {{field}} variables in text.
     * Supports dot-path and [] array notation.
     * Checks local data first, then global data.
     *
     * Examples:
     *   {{ name }}                  → scalar value
     *   {{ client.name }}           → nested dot-path
     *   {{ results[].name }}        → iterate array, join values
     *   {{ orders[].items[].qty }}  → nested array iteration
     */
    private function interpolate(string $input): string
    {
        $result = preg_replace_callback('/\{\{\s*(\w+(?:\[\])?(?:\.\w+(?:\[\])?)*)\s*\}\}/', function (array $matches): string {
            $path = $matches[1];

            // Check local data first
            $value = $this->resolvePath($this->local_data, $path);
            if ($value !== null) {
                return $this->stringify($value);
            }

            // Then global data
            $value = $this->resolvePath($this->global_data, $path);
            if ($value !== null) {
                return $this->stringify($value);
            }

            // Keep original placeholder if not found
            return $matches[0];
        }, $input);

        return $result !== null ? $result : $input;
    }

    /**
     * Resolve a dot-path (with optional [] array notation) against data.
     *
     * Segments ending with [] iterate the array and resolve the remaining
     * path for each element, collecting all results.
     *
     * @param array<string, mixed> $data
     */
    private function resolvePath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        return $this->resolveSegments($data, $segments);
    }

    /**
     * @param array<string, mixed>|mixed $current
     * @param list<string> $segments
     */
    private function resolveSegments(mixed $current, array $segments): mixed
    {
        while (count($segments) > 0) {
            $segment = array_shift($segments);

            if (str_ends_with($segment, '[]')) {
                // Array iteration: resolve the base key, then iterate each element
                $key = substr($segment, 0, -2);

                if (!is_array($current) || !array_key_exists($key, $current)) {
                    return null;
                }

                $items = $current[$key];
                if (!is_array($items)) {
                    return null;
                }

                // Resolve remaining segments for each array element
                $results = [];
                foreach (array_values($items) as $item) {
                    $resolved = $this->resolveSegments($item, $segments);
                    if ($resolved !== null) {
                        $results[] = $resolved;
                    }
                }

                return $results !== [] ? $results : null;
            }

            // Simple key lookup
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? '');
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => $this->stringify($v), $value));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    private function calculateLayout(): void
    {
        $font_metrics = $this->font_metrics->insertFont(
            $this->font_family,
            $this->style,
            $this->font_size
        );

        $interpolated = $this->interpolate($this->text);
        $line_height = $this->font_metrics->getLineHeight($font_metrics);

        // Determine the effective width for word-wrap:
        // Explicit width wins; max_width only acts as a cap. This lets a Label
        // inside a wide VBox keep its own narrow width for wrapping.
        $effective_width = null;
        if ($this->width !== null && $this->width > 0) {
            $effective_width = $this->width;
        }
        if ($this->max_width !== null && $this->max_width > 0) {
            $effective_width = $effective_width === null
                ? $this->max_width
                : min($effective_width, $this->max_width);
        }

        if ($effective_width !== null && $effective_width > 0) {
            // Word-wrap within the effective width
            $this->lines = $this->wordWrap($interpolated, $font_metrics, $effective_width);
            $this->calculated_width = $effective_width;
        } else {
            // Single line, auto-width
            $this->lines = [$interpolated];
            $this->calculated_width = $this->font_metrics->getStringWidth($interpolated, $font_metrics);
        }

        $this->calculated_height = count($this->lines) * $line_height;

        // Explicit height wins; max_height acts as a cap.
        $effective_height = null;
        if ($this->height !== null && $this->height > 0) {
            $effective_height = $this->height;
        }
        if ($this->max_height !== null && $this->max_height > 0) {
            $effective_height = $effective_height === null
                ? $this->max_height
                : min($effective_height, $this->max_height);
        }
        if ($effective_height !== null && $effective_height > 0) {
            $this->calculated_height = $effective_height;
        }
    }

    /**
     * @param array{out: string} $font_metrics
     * @return array<int, string>
     */
    private function wordWrap(string $text, array $font_metrics, float $max_width): array
    {
        $lines = [];
        $words = explode(' ', $text);

        if (count($words) === 0) {
            return [''];
        }

        $current_line = '';

        foreach ($words as $word) {
            $test_line = $current_line === '' ? $word : $current_line . ' ' . $word;
            $line_width = $this->font_metrics->getStringWidth($test_line, $font_metrics);

            if ($line_width > $max_width && $current_line !== '') {
                $lines[] = $current_line;
                $current_line = $word;
            } else {
                $current_line = $test_line;
            }
        }

        if ($current_line !== '') {
            $lines[] = $current_line;
        }

        return $lines;
    }

    /**
     * @return array{w: float, h: float}
     */
    public function getDimensions(): array
    {
        if ($this->calculated_width === 0.0 && $this->calculated_height === 0.0) {
            $this->calculateLayout();
        }

        // Add margin to total dimensions
        $width = $this->calculated_width + $this->margin['left'] + $this->margin['right'];
        $height = $this->calculated_height + $this->margin['top'] + $this->margin['bottom'];

        return [
            'w' => $width,
            'h' => $height,
        ];
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $this->calculateLayout();

        $font_metrics = $this->font_metrics->insertFont(
            $this->font_family,
            $this->style,
            $this->font_size
        );

        $line_height = $this->font_metrics->getLineHeight($font_metrics);

        $content_x = $x + $this->margin['left'];
        $content_y = $y + $this->margin['top'];
        $current_y = $content_y;

        // When a fixed height is in effect, only render lines that fit (overflow: hidden)
        $visible_lines = $this->lines;
        if ($this->calculated_height > 0 && $line_height > 0) {
            $max_count = (int) floor($this->calculated_height / $line_height);
            $visible_lines = array_slice($this->lines, 0, max(1, $max_count));
        }

        $has_fixed_width = $this->width !== null && $this->width > 0;
        $has_fixed_height = $this->height !== null && $this->height > 0;
        $clip_width = $this->calculated_width > 0 ? $this->calculated_width : 0;
        $clip_height = $this->calculated_height > 0 ? $this->calculated_height : 0;
        $should_clip = ($has_fixed_width || $has_fixed_height)
            && $clip_width > 0
            && $clip_height > 0
            && isset($pdf->page);

        // Determine if we need to isolate color changes via q/Q
        $has_color = $this->color !== null && $this->parseColor($this->color) !== null;
        $needs_graphics_state = $has_color || $should_clip;

        if ($needs_graphics_state && isset($pdf->page)) {
            $pdf->page->addContent('q');
        }

        // Set font color via PDF rg operator (fill color = text color)
        if ($has_color && isset($pdf->page)) {
            $rgb = $this->parseColor($this->color);
            $r = round($rgb['r'] / 255, 4);
            $g = round($rgb['g'] / 255, 4);
            $b = round($rgb['b'] / 255, 4);
            $pdf->page->addContent("{$r} {$g} {$b} rg");
        }

        if ($should_clip) {
            $this->startClip($pdf, $content_x, $content_y, $clip_width, $clip_height);
        }

        foreach ($visible_lines as $line) {
            if (isset($pdf->page)) {
                $pdf->page->addContent($font_metrics['out']);
            }

            $pdf->addTextCell(
                txt: $line,
                posx: $content_x,
                posy: $current_y,
                width: $this->calculated_width > 0 ? $this->calculated_width : $this->font_metrics->getStringWidth($line, $font_metrics),
                height: 0,
                drawcell: false,
                clip: false
            );
            $current_y += $line_height;
        }

        // Restore graphics state (color + clip)
        if ($needs_graphics_state && isset($pdf->page)) {
            $pdf->page->addContent('Q');
        }
    }

    /**
     * Parse a CSS-like color string (#RRGGBB or #RGB) into RGB components.
     *
     * @return array{r: int, g: int, b: int}|null
     */
    private function parseColor(string $color): ?array
    {
        $color = ltrim($color, '#');

        if (!ctype_xdigit($color)) {
            return null;
        }

        if (strlen($color) === 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        if (strlen($color) !== 6) {
            return null;
        }

        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    /**
     * Start a rectangular clip region around the Label's content area.
     * PDF coordinate system uses bottom-left origin.
     */
    private function startClip(Tcpdf $pdf, float $x, float $y, float $w, float $h): void
    {
        if (! isset($pdf->page)) {
            return;
        }

        $k = $pdf->page->getKUnit();
        $pageH = $this->getPageHeight($pdf);

        $out = '';
        $out .= sprintf(
            ' %.3F %.3F %.3F %.3F re W n',
            $x * $k,
            ($pageH - $y - $h) * $k,
            $w * $k,
            $h * $k
        );

        $pdf->page->addContent($out);
    }

    private function getPageHeight(Tcpdf $pdf): float
    {
        return $pdf->toUnit($pdf->page->getPage()['pheight']);
    }
}
