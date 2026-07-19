<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;
use Toolreport\Core\Layout\InterpolatesVariables;

class RectangleElementRenderer implements ElementRendererInterface
{
    use InterpolatesVariables;

    public function type(): string
    {
        return 'rectangle';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $content = $element['content'] ?? [];
        $styles = $element['styles'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 80;
        $height = $element['height'] ?? 40;

        $backgroundColor = $styles['backgroundColor'] ?? $styles['background_color'] ?? '';
        $borderRadius = $styles['borderRadius'] ?? $styles['border_radius'] ?? 0;

        // Resolve colorVariable — the dynamic color from data
        $colorVariable = $content['colorVariable'] ?? '';
        if ($colorVariable !== '') {
            $resolvedColor = $this->interpolate('{{ ' . $colorVariable . ' }}', $data, $localData);
            // Only use the resolved value if it's not the raw placeholder (variable not found)
            if ($resolvedColor !== '' && !str_starts_with($resolvedColor, '{{')) {
                $backgroundColor = $resolvedColor;
            }
        }

        $css = "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; "
            . "font-size: 0; line-height: 0;";

        if ($backgroundColor !== '' && $backgroundColor !== null) {
            $css .= " background-color: {$backgroundColor};";
        }

        // Border support
        $border = $styles['border'] ?? null;
        if ($border !== null) {
            $bw = (float) ($border['width'] ?? 1);
            $bc = $border['color'] ?? '#000000';
            $bs = $border['style'] ?? 'solid';
            $css .= " border: {$bw}mm {$bs} {$bc};";
        }

        if ((float) $borderRadius > 0) {
            $css .= " border-radius: {$borderRadius}mm; overflow: hidden;";
        }

        return "<div class=\"pdf-element\" style=\"{$css}\"></div>";
    }
}