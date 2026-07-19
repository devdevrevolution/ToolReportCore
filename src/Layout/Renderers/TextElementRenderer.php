<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Toolreport\Core\Layout\ElementRendererInterface;
use Toolreport\Core\Layout\InterpolatesVariables;

class TextElementRenderer implements ElementRendererInterface
{
    use InterpolatesVariables;

    public function type(): string
    {
        return 'text';
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $content = $element['content'] ?? [];
        $styles = $element['styles'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 100;
        $height = $element['height'] ?? 20;

        $rawText = $content['text'] ?? '';
        $variable = $content['variable'] ?? '';

        // If no {{ }} placeholders in text but a variable binding exists,
        // use the variable as the placeholder — this handles the drag-and-drop
        // flow where text is set to the field's display name and variable has the path.
        if ($variable !== '' && !str_contains($rawText, '{{')) {
            $rawText = '{{ ' . $variable . ' }}';
        }

        $text = $this->interpolate($rawText, $data, $localData);
        $text = $this->escape($text);

        $fontSize = $styles['fontSize'] ?? 10;
        $backgroundColor = $styles['backgroundColor'] ?? $styles['background_color'] ?? '';
        $borderRadius = $styles['borderRadius'] ?? $styles['border_radius'] ?? 0;
        $fontFamily = $styles['fontFamily'] ?? 'DejaVu Sans';
        $color = $styles['color'] ?? '#000000';
        $lineHeight = $styles['lineHeight'] ?? 1.4;
        // Support both new (fontWeight) and legacy (bold) keys for backward compatibility
        $isBold = ($styles['fontWeight'] ?? 'normal') === 'bold' || !empty($styles['bold']);
        $isItalic = ($styles['fontStyle'] ?? 'normal') === 'italic' || !empty($styles['italic']);
        $fontWeight = $isBold ? 'bold' : 'normal';
        $fontStyle = $isItalic ? 'italic' : 'normal';
        $textDecoration = !empty($styles['underline']) ? 'underline' : 'none';
        // Support both new (textAlign) and legacy (alignment) keys
        $alignment = $styles['textAlign'] ?? $styles['alignment'] ?? 'left';
        $verticalAlign = $styles['verticalAlign'] ?? 'top';

// Build inline CSS for the outer container (positioning + background)
        $css = "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; "
            . "font-family: {$fontFamily}; font-size: {$fontSize}pt; line-height: {$lineHeight}; "
            . "color: {$color}; font-weight: {$fontWeight}; font-style: {$fontStyle}; "
            . "text-decoration: {$textDecoration}; text-align: {$alignment}; "
            . "white-space: pre-wrap; word-wrap: break-word;";

        if ($backgroundColor !== '' && $backgroundColor !== null) {
            $css .= " background-color: {$backgroundColor};";
        }

        if ((float) $borderRadius > 0) {
            $css .= " border-radius: {$borderRadius}mm; overflow: hidden;";
        }

        // Apply user-specified padding (controls vertical/horizontal positioning)
        $padding = $styles['padding'] ?? ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
        $pt = (float) ($padding['top'] ?? 0);
        $pr = (float) ($padding['right'] ?? 0);
        $pb = (float) ($padding['bottom'] ?? 0);
        $pl = (float) ($padding['left'] ?? 0);

        if ($pt > 0 || $pr > 0 || $pb > 0 || $pl > 0) {
            $css .= " padding: {$pt}mm {$pr}mm {$pb}mm {$pl}mm;";
        }

        $html = "<div class=\"pdf-element\" style=\"{$css}\">{$text}</div>";

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}