<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Containers;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class VBox implements Component
{
    /** @var Component[] */
    private array $children;

    private float $padding;

    private ?float $fixed_width = null;

    private ?float $fixed_height = null;

    private ?float $max_width = null;

    private ?float $max_height = null;

    /** @var array{top: float, right: float, bottom: float, left: float} */
    private array $margin = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

    /**
     * @param Component[] $children
     */
    public function __construct(array $children, float $padding = 0)
    {
        $this->children = $children;
        $this->padding = $padding;
    }

    public function setWidth(float $width): void
    {
        $this->fixed_width = $width;
    }

    public function setHeight(float $height): void
    {
        $this->fixed_height = $height;
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
        $width = 0.0;
        $height = 0.0;

        foreach ($this->children as $child) {
            $dims = $child->getDimensions();
            $width = max($width, $dims['w']);
            $height += $dims['h'];
        }

        $count = count($this->children);
        if ($count > 1) {
            $height += $this->padding * ($count - 1);
        }

        if ($this->fixed_width !== null && $this->fixed_width > $width) {
            $width = $this->fixed_width;
        }

        if ($this->fixed_height !== null && $this->fixed_height > $height) {
            $height = $this->fixed_height;
        }

        // Add margin to total dimensions
        $width += $this->margin['left'] + $this->margin['right'];
        $height += $this->margin['top'] + $this->margin['bottom'];

        return ['w' => $width, 'h' => $height];
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $content_x = $x + $this->margin['left'];
        $content_y = $y + $this->margin['top'];
        $current_y = $content_y;

        // Determine available width for children (fill parent behavior)
        // When fixed_width is set, ALWAYS use it — it constrains children to the VBox width.
        // Only fall back to max-sibling-width when no fixed_width is set.
        $content_width = 0.0;
        if ($this->fixed_width !== null && $this->fixed_width > 0) {
            $content_width = $this->fixed_width;
        } else {
            foreach ($this->children as $child) {
                $dims = $child->getDimensions();
                $content_width = max($content_width, $dims['w']);
            }
        }

        // Determine available height for children (only when fixed/max height is set)
        $content_height = 0.0;
        if ($this->fixed_height !== null && $this->fixed_height > 0) {
            $content_height = $this->fixed_height;
        } elseif ($this->max_height !== null && $this->max_height > 0) {
            $content_height = $this->max_height;
        }

        // Set maxWidth on children so Labels word-wrap at container width
        foreach ($this->children as $child) {
            if ($content_width > 0) {
                $child->setMaxWidth($content_width);
            }
            if ($content_height > 0) {
                $child->setMaxHeight($content_height);
            }
            $child->render($pdf, $content_x, $current_y);
            $dims = $child->getDimensions();
            $current_y += $dims['h'] + $this->padding;
        }
    }

    public function setMaxWidth(float $maxWidth): void
    {
        // VBox propagates maxWidth to children at render time via setMaxWidth.
        // Store it so nested VBox children can use it if they don't have their own fixed_width.
        $this->fixed_width = $this->fixed_width ?? $maxWidth;
        $this->max_width = $maxWidth;
    }

    public function setMaxHeight(float $maxHeight): void
    {
        $this->fixed_height = $this->fixed_height ?? $maxHeight;
        $this->max_height = $maxHeight;
    }

    /**
     * @return Component[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
