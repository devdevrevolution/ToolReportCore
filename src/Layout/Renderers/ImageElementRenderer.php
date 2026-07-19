<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;
use Toolreport\Core\Layout\InterpolatesVariables;

class ImageElementRenderer implements ElementRendererInterface
{
    use InterpolatesVariables;

    public function type(): string
    {
        return 'image';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $content = $element['content'] ?? [];
        $styles = $element['styles'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 50;
        $height = $element['height'] ?? 50;

        // Resolve image URL: support both `src` (legacy) and `imageUrl` (current)
        $src = $content['imageUrl'] ?? $content['src'] ?? '';
        $variable = $content['variable'] ?? '';

        // If no {{ }} placeholders in src but a variable binding exists,
        // use the variable as the placeholder — same pattern as TextElementRenderer.
        if ($variable !== '' && !str_contains($src, '{{')) {
            $src = '{{ ' . $variable . ' }}';
        }

        $src = $this->interpolate($src, $data, $localData);

        if (empty($src)) {
            return '';
        }

        $opacity = $styles['opacity'] ?? 1;
        $borderRadius = $styles['borderRadius'] ?? $styles['border_radius'] ?? 0;

        // Use explicit mm dimensions on the img so DomPDF renders it at the
        // correct size. DomPDF supports `mm` units in CSS and renders the img
        // at the specified dimensions (may stretch if aspect ratio differs).
        // The container div provides positioning.
        $imgStyle = "width: {$width}mm; height: {$height}mm; object-fit: contain;";

        $containerStyle = "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; "
            . "opacity: {$opacity};";

        // Apply border-radius on the container with overflow: hidden so DomPDF
        // clips the image to the rounded shape. Both DomPDF 3.x and browsers
        // support border-radius + overflow: hidden for clipping child content.
        if ((float) $borderRadius > 0) {
            $containerStyle .= " border-radius: {$borderRadius}mm; overflow: hidden;";
        }

        $html = "<div class=\"pdf-element\" style=\"{$containerStyle}\">"
            . "<img src=\"{$this->escape($src)}\" style=\"{$imgStyle}\" />"
            . "</div>";

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}