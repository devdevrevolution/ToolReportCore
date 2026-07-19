<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Containers;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class HBox implements Component
{
    /** @var Component[] */
    private array $children;

    private ?float $fixed_width = null;

    private ?float $fixed_height = null;

    private ?float $max_width = null;

    private ?float $max_height = null;

    /** @var array{top: float, right: float, bottom: float, left: float} */
    private array $margin = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

    /**
     * @param Component[] $children
     */
    public function __construct(array $children)
    {
        $this->children = $children;
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
        $natural_width = 0.0;
        $max_height = 0.0;

        foreach ($this->children as $child) {
            $dims = $child->getDimensions();
            $natural_width += $dims['w'];
            $max_height = max($max_height, $dims['h']);
        }

        if ($this->fixed_width !== null && $this->fixed_width > $natural_width) {
            $natural_width = $this->fixed_width;
        }

        if ($this->fixed_height !== null && $this->fixed_height > $max_height) {
            $max_height = $this->fixed_height;
        }

        // Add margin to total dimensions
        $natural_width += $this->margin['left'] + $this->margin['right'];
        $max_height += $this->margin['top'] + $this->margin['bottom'];

        return ['w' => $natural_width, 'h' => $max_height];
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        $child_widths = [];
        $natural_width = 0.0;

        foreach ($this->children as $child) {
            $dims = $child->getDimensions();
            $child_widths[] = $dims['w'];
            $natural_width += $dims['w'];
        }

        $current_x = $x + $this->margin['left'];
        $content_y = $y + $this->margin['top'];

        // Determine available height for children
        $content_height = 0.0;
        if ($this->fixed_height !== null && $this->fixed_height > 0) {
            $content_height = $this->fixed_height;
        } elseif ($this->max_height !== null && $this->max_height > 0) {
            $content_height = $this->max_height;
        } else {
            foreach ($this->children as $child) {
                $dims = $child->getDimensions();
                $content_height = max($content_height, $dims['h']);
            }
        }

        foreach ($this->children as $i => $child) {
            $child_w = $child_widths[$i];

            // Set maxWidth so Labels word-wrap at their allocated width
            $child->setMaxWidth($child_w);
            if ($content_height > 0) {
                $child->setMaxHeight($content_height);
            }
            $child->render($pdf, $current_x, $content_y);

            $current_x += $child_w;
        }
    }

    public function setMaxWidth(float $maxWidth): void
    {
        // HBox propagates maxWidth to children at render time.
        // Store it so the proportional distribution uses the constrained width.
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
