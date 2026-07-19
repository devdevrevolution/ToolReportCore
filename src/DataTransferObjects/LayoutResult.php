<?php

declare(strict_types=1);

namespace Toolreport\Core\DataTransferObjects;

class LayoutResult
{
    public function __construct(
        public readonly string $html,
        public readonly string $title,
        public readonly string $paperSize,
        public readonly string $orientation,
        public readonly array $page,
        public readonly int $elementCount = 0,
    ) {}

    /**
     * Get the page dimensions for DomPDF.
     */
    public function pageDimensions(): array
    {
        return [$this->paperSize, $this->orientation];
    }

    /**
     * Get the margin values in mm.
     */
    public function margins(): array
    {
        return $this->page['margins'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
    }

    /**
     * Extract the inner HTML from the <body> tag.
     *
     * Used by composition PDF generation to concatenate multiple layouts
     * into a single HTML document without creating blank intermediate pages.
     */
    public function bodyContent(): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/s', $this->html, $matches)) {
            return trim($matches[1]);
        }

        return $this->html;
    }

    /**
     * Extract the <style> block from the <head>.
     *
     * Each layout may have slightly different margins/dimensions,
     * so styles must be scoped per page section.
     */
    public function headStyle(): string
    {
        if (preg_match('/<style[^>]*>(.*)<\/style>/s', $this->html, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
