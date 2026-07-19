<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Engine;

use Com\Tecnick\Pdf\Tcpdf;

class FontMetrics
{
    private Tcpdf $pdf;

    /** @var array<string, array{out: string, ascent: float, descent: float, height: float}> */
    private array $font_cache = [];

    public function __construct(Tcpdf $pdf)
    {
        $this->pdf = $pdf;
    }

    public function insertFont(string $family = 'helvetica', string $style = '', float $size = 10): array
    {
        $cache_key = $family.'|'.$style.'|'.$size;

        if (isset($this->font_cache[$cache_key])) {
            return $this->font_cache[$cache_key];
        }

        $font = $this->pdf->font->insert($this->pdf->pon, $family, $style, $size);
        $this->font_cache[$cache_key] = $font;

        return $font;
    }

    public function getStringWidth(string $text, array $font_metrics): float
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || $chars === []) {
            return 0.0;
        }
        $ordArr = array_map('mb_ord', $chars);

        return $this->pdf->toUnit($this->pdf->font->getOrdArrWidth($ordArr));
    }

    public function getLineHeight(array $font_metrics): float
    {
        // Use font ascent + descent for actual line height, fall back to 1.2x font size
        $ascent = isset($font_metrics['ascent']) ? $this->pdf->toUnit($font_metrics['ascent']) : 0;
        $descent = isset($font_metrics['descent']) ? abs($this->pdf->toUnit($font_metrics['descent'])) : 0;

        if ($ascent > 0 || $descent > 0) {
            return $ascent + $descent;
        }

        // Fallback: estimate from font size
        return $font_metrics['size'] * 1.2;
    }
}
