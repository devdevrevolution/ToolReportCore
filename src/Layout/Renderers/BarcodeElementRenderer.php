<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;
use Toolreport\Core\Layout\InterpolatesVariables;

class BarcodeElementRenderer implements ElementRendererInterface
{
    use InterpolatesVariables;

    public function type(): string
    {
        return 'barcode';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $content = $element['content'] ?? [];
        $styles = $element['styles'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 80;
        $height = $element['height'] ?? 30;

        $value = $content['value'] ?? '';
        $value = $this->interpolate($value, $data, $localData);
        $format = $content['format'] ?? 'code128';
        $showLabel = $content['showLabel'] ?? true;

        if (empty($value)) {
            return '';
        }

        // Use inline SVG for barcode rendering
        // For proper barcode rendering, we provide a placeholder and recommend
        // picqer/php-barcode-generator for production use
        $labelHtml = $showLabel
            ? "<div style=\"text-align: center; font-size: 7pt; margin-top: 2pt;\">{$this->escape($value)}</div>"
            : '';

        $html = "<div class=\"pdf-element\" style=\""
            . "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; "
            . "text-align: center; "
            . "\">"
            . "<div style=\"font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; "
            . "letter-spacing: 1pt; padding: 2pt; background: #fff; border: none;\">"
            . str_repeat('|', min((int) ($width / 0.8), 60))
            . "</div>"
            . $labelHtml
            . "</div>";

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}