<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Contracts;

use Com\Tecnick\Pdf\Tcpdf;

interface Component
{
    /** @return array{w: float, h: float} */
    public function getDimensions(): array;

    public function render(Tcpdf $pdf, float $x, float $y): void;

    /**
     * Set the maximum available width (mm) from the parent container.
     * Used by Labels inside containers to word-wrap at the container width.
     */
    public function setMaxWidth(float $maxWidth): void;

    /**
     * Set the maximum available height (mm) from the parent container.
     * Used by components that stretch to fill the cross-axis of a container.
     */
    public function setMaxHeight(float $maxHeight): void;
}
