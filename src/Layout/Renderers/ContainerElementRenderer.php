<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;
use Toolreport\Core\Layout\HasLayoutEngine;
use Toolreport\Core\Layout\InterpolatesVariables;

class ContainerElementRenderer implements ElementRendererInterface
{
    use HasLayoutEngine;
    use InterpolatesVariables;

    public function type(): string
    {
        return 'container';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $x = (float) ($element['x'] ?? 0);
        $y = (float) ($element['y'] ?? 0);
        $width = (float) ($element['width'] ?? 100);
        $height = (float) ($element['height'] ?? 80);
        $styles = $element['styles'] ?? [];
        $content = $element['content'] ?? [];
        $children = $content['children'] ?? [];

        // Build container CSS
        $css = "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; overflow: hidden; position: absolute;";

        // Background
        $backgroundColor = $styles['backgroundColor'] ?? $styles['background_color'] ?? '';
        if ($backgroundColor !== '' && $backgroundColor !== null) {
            $css .= " background-color: {$backgroundColor};";
        }

        // Border
        $border = $styles['border'] ?? null;
        if ($border !== null) {
            $bw = (float) ($border['width'] ?? 1);
            $bc = $border['color'] ?? '#000000';
            $bs = $border['style'] ?? 'solid';
            $css .= " border: {$bw}mm {$bs} {$bc};";
        }

        // Border radius
        $borderRadius = $styles['borderRadius'] ?? $styles['border_radius'] ?? 0;
        if ((float) $borderRadius > 0) {
            $css .= " border-radius: {$borderRadius}mm;";
        }

        // Render children
        $renderedChildren = '';
        foreach ($children as $child) {
            // Skip invisible children
            if (isset($child['visible']) && $child['visible'] === false) {
                continue;
            }

            $positionMode = $child['positionMode'] ?? 'absolute';

            if ($positionMode === 'fill') {
                // Fill mode: override child dimensions to occupy the full container
                $fillChild = $child;
                $fillChild['x'] = 0;
                $fillChild['y'] = 0;
                $fillChild['width'] = $width;
                $fillChild['height'] = $height;
                $renderedChildren .= ($this->layoutEngine?->renderElement($fillChild, $data, $page, $localData) ?? '') . "\n";
            } else {
                // Absolute mode: use child's own coordinates (relative to container)
                $renderedChildren .= ($this->layoutEngine?->renderElement($child, $data, $page, $localData) ?? '') . "\n";
            }
        }

        $innerContent = $renderedChildren !== '' ? "\n" . $renderedChildren : '';

        return "<div class=\"pdf-element\" style=\"{$css}\">{$innerContent}</div>";
    }
}
