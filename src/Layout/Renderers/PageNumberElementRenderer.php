<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;

class PageNumberElementRenderer implements ElementRendererInterface
{
    public function type(): string
    {
        return 'page_number';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $styles = $element['styles'] ?? [];
        $content = $element['content'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 50;
        $height = $element['height'] ?? 10;

        // Support both new (textAlign) and legacy (alignment) keys
        $alignment = $styles['textAlign'] ?? $styles['alignment'] ?? 'center';
        $format = $content['format'] ?? 'page {PAGE_NUM} of {PAGE_COUNT}';
        $fontSize = $styles['fontSize'] ?? 8;
        $color = $styles['color'] ?? '#666666';
        $verticalAlign = $styles['verticalAlign'] ?? 'top';

        // Build inline CSS — add flexbox for vertical alignment when not 'top'
        $css = "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; "
            . "font-size: {$fontSize}pt; color: {$color}; text-align: {$alignment};";

        if ($verticalAlign !== 'top') {
            $css .= " display: flex; align-items: " . ($verticalAlign === 'middle' ? 'center' : 'flex-end') . ';';
        }

        $html = "<div class=\"pdf-element page-number\" style=\"{$css}\">"
            . "<span class=\"page-number-text\">{$this->escape($format)}</span>"
            . "</div>";

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}