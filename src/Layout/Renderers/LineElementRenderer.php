<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;

class LineElementRenderer implements ElementRendererInterface
{
    public function type(): string
    {
        return 'line';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $styles = $element['styles'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 100;
        $height = $element['height'] ?? 1;

        $thickness = $styles['thickness'] ?? 1;
        $color = $styles['color'] ?? '#000000';
        $style = $styles['style'] ?? 'solid'; // solid, dashed, dotted

        $borderStyle = match ($style) {
            'dashed' => 'dashed',
            'dotted' => 'dotted',
            default => 'solid',
        };

        $html = "<div class=\"pdf-element pdf-line\" style=\""
            . "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$thickness}mm; "
            . "border-bottom: {$thickness}pt {$borderStyle} {$color}; "
            . "font-size: 0; line-height: 0; "
            . "\"></div>";

        return $html;
    }
}