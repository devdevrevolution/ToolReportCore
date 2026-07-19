<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Engine;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;
use Toolreport\Core\Modules\PdfEngine\Containers\HBox;
use Toolreport\Core\Modules\PdfEngine\Containers\VBox;

/**
 * Renders debug dimension annotations on PDF components.
 *
 * When enabled, each component gets a printed text label showing
 * its type and effective dimensions (e.g. "Label 30.0x5.0 mm").
 * For containers (VBox/HBox), children are annotated recursively.
 */
class DebugAnnotation
{
    private const FONT_SIZE = 5;

    public function __construct(
        private readonly Tcpdf $pdf,
        private readonly FontMetrics $fontMetrics,
    ) {}

    /**
     * Annotate a component and all its children (recursive for containers).
     */
    public function annotate(Component $component, float $x, float $y, string $type): void
    {
        $this->annotateRecursive($component, $x, $y, $type);
    }

    /**
     * Recursively annotate a component and its children.
     */
    private function annotateRecursive(Component $component, float $x, float $y, string $type): void
    {
        $this->drawLabel($component, $x, $y, $type);

        if ($component instanceof VBox) {
            $this->annotateVBoxChildren($component, $x, $y);
        } elseif ($component instanceof HBox) {
            $this->annotateHBoxChildren($component, $x, $y);
        }
    }

    /**
     * Annotate children of a VBox.
     */
    private function annotateVBoxChildren(VBox $vbox, float $x, float $y): void
    {
        $current_y = $y;
        foreach ($vbox->getChildren() as $child) {
            $child_class = basename(str_replace('\\', '/', get_class($child)));
            $child_dims = $child->getDimensions();
            $this->annotateRecursive($child, $x, $current_y, $child_class);
            $current_y += $child_dims['h'];
        }
    }

    /**
     * Annotate children of an HBox.
     */
    private function annotateHBoxChildren(HBox $hbox, float $x, float $y): void
    {
        $current_x = $x;
        foreach ($hbox->getChildren() as $child) {
            $child_class = basename(str_replace('\\', '/', get_class($child)));
            $child_dims = $child->getDimensions();
            $this->annotateRecursive($child, $current_x, $y, $child_class);
            $current_x += $child_dims['w'];
        }
    }

    /**
     * Draw a single debug label at the bottom of a component.
     */
    private function drawLabel(Component $component, float $x, float $y, string $type): void
    {
        $dims = $component->getDimensions();
        $text = sprintf('%s %.1fx%.1f mm', $type, $dims['w'], $dims['h']);

        $fm = $this->fontMetrics->insertFont('helvetica', '', self::FONT_SIZE);
        if (isset($this->pdf->page)) {
            $this->pdf->page->addContent($fm['out']);
        }

        $this->pdf->addTextCell(
            txt: $text,
            posx: $x,
            posy: $y + $dims['h'] - 2,
            width: $dims['w'],
            height: 2,
            drawcell: false,
            clip: false,
        );
    }
}
