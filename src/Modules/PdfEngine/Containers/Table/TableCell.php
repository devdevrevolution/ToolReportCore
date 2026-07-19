<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Containers\Table;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

class TableCell implements Component
{
    private Component $content;

    private float $width;

    private float $padding;

    public function __construct(Component $content, float $width, float $padding = 2)
    {
        $this->content = $content;
        $this->width = $width;
        $this->padding = $padding;
    }

    /**
     * @return array{w: float, h: float}
     */
    public function getDimensions(): array
    {
        $content_dims = $this->content->getDimensions();

        return [
            'w' => $this->width,
            'h' => $content_dims['h'] + 2 * $this->padding,
        ];
    }

    public function render(Tcpdf $pdf, float $x, float $y): void
    {
        // Pass maxWidth to content (cell width minus padding on both sides)
        $this->content->setMaxWidth($this->width - 2 * $this->padding);
        $this->content->render($pdf, $x + $this->padding, $y + $this->padding);
    }

    public function setMaxWidth(float $maxWidth): void
    {
        // TableCell passes maxWidth to content in render()
    }

    public function setMaxHeight(float $maxHeight): void
    {
        // TableCell doesn't use maxHeight directly; it could be passed to content if needed.
    }
}
