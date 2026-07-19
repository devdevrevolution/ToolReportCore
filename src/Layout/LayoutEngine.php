<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout;

use Toolreport\Core\DataTransferObjects\LayoutResult;
use Toolreport\Core\Exceptions\InvalidLayoutException;

class LayoutEngine
{
    /** @var array<string, ElementRendererInterface> */
    private array $renderers = [];

    /**
     * Band types that render as fixed elements repeating at the top of every page.
     * pageHeader repeats at the top of every page via position: fixed.
     */
    private const FIXED_TOP_TYPES = ['pageHeader'];

    /**
     * Band types that render as fixed elements repeating at the bottom of every page.
     * Only pageFooter repeats at the bottom of every page.
     */
    private const FIXED_BOTTOM_TYPES = ['pageFooter'];

    /**
     * Register an element renderer.
     *
     * If the renderer implements setLayoutEngine(), the engine injects itself
     * so the renderer can render child elements recursively.
     */
    public function registerRenderer(ElementRendererInterface $renderer): void
    {
        $this->renderers[$renderer->type()] = $renderer;

        if (method_exists($renderer, 'setLayoutEngine')) {
            $renderer->setLayoutEngine($this);
        }
    }

    /**
     * Register multiple renderers at once.
     *
     * @param ElementRendererInterface[] $renderers
     */
    public function registerRenderers(array $renderers): void
    {
        foreach ($renderers as $renderer) {
            $this->registerRenderer($renderer);
        }
    }

    /**
     * Convert a designer JSON layout + data into positioned HTML.
     *
     * @param array $config The full template config (page + elements or page + bands).
     * @param array $data   Variable data for interpolation.
     * @param string $title Optional document title.
     * @return LayoutResult
     * @throws InvalidLayoutException
     */
    public function render(array $config, array $data = [], string $title = 'Document'): LayoutResult
    {
        $this->validateConfig($config);

        $page = $config['page'];

        // Route to band-based rendering or flat element iteration
        if (!empty($config['page']['bands'])) {
            return $this->renderBands($config, $data, $title);
        }

        return $this->renderElements($config, $data, $title);
    }

    /**
     * Render layout using flat element iteration (v1/v2 backward-compatible path).
     */
    private function renderElements(array $config, array $data, string $title): LayoutResult
    {
        $page = $config['page'];
        $elements = $config['children'] ?? [];
        $paperSize = $page['paper_size'] ?? 'a4';
        $orientation = $page['orientation'] ?? 'portrait';

        $rendered = [];
        foreach ($elements as $element) {
            $rendered[] = $this->renderElement($element, $data, $page);
        }

        $bodyContent = implode("\n", $rendered);
        $html = $this->wrapInDocument($bodyContent, $page, $title);

        return new LayoutResult(
            html: $html,
            title: $title,
            paperSize: $paperSize,
            orientation: $orientation,
            page: $page,
            elementCount: count($elements),
        );
    }

    /**
     * Render layout using band-based iteration with iReport-style pagination.
     *
     * Band rendering follows iReport conventions:
     * - Title: flowing band, first page only
     * - PageHeader: flowing band, appears after title on first page
     * - ColumnHeader: flowing band, appears after pageHeader on first page
     * - Detail (fill): flowing band, repeated per collection item
     * - ColumnFooter: flowing band, after detail content
     * - PageFooter: fixed (position:fixed), repeats at bottom of every page
     * - Summary: flowing band, last page only
     *
     * All bands except pageFooter use .pdf-band containers (position: relative, explicit height)
     * so DomPDF handles page breaks naturally. pageFooter uses position: fixed.
     * Elements inside .pdf-band use position:absolute relative to their band container.
     *
     * iReport first-page visual order:
     *   Title → PageHeader → ColumnHeader → Detail rows → ColumnFooter → Summary
     *   PageFooter (fixed at bottom, repeats on every page)
     */
    private function renderBands(array $config, array $data, string $title): LayoutResult
    {
        $page = $config['page'];
        $bands = $page['bands'];
        $paperSize = $page['paper_size'] ?? 'a4';
        $orientation = $page['orientation'] ?? 'portrait';

        $this->validateBandHeights($page);

        $margins = $page['margins'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];

        // Categorize bands: pageHeader fixed at top, pageFooter fixed at bottom,
        // everything else flows in document order.
        $fixedTopBands = [];
        $fixedBottomBands = [];
        $flowingBandsTop = [];    // title, columnHeader (top-anchored)
        $fillBands = [];           // detail (fill-anchored)
        $summaryBands = [];
        $flowingBandsBottom = [];  // columnFooter etc.

        foreach ($bands as $band) {
            // Skip disabled bands — they are NOT rendered in PDF output
            if (($band['enabled'] ?? true) === false) {
                continue;
            }

            $bandType = $band['type'] ?? '';
            $anchor = $band['anchor'] ?? 'top';

            if (in_array($bandType, self::FIXED_BOTTOM_TYPES, true) && $anchor === 'bottom') {
                $fixedBottomBands[] = $band;
            } elseif (in_array($bandType, self::FIXED_TOP_TYPES, true) && $anchor === 'top') {
                $fixedTopBands[] = $band;
            } elseif ($bandType === 'summary') {
                $summaryBands[] = $band;
            } elseif ($anchor === 'top') {
                // Top-anchored bands that ARE NOT fixed-top flow in document order:
                // title, columnHeader, etc.
                $flowingBandsTop[] = $band;
            } elseif ($anchor === 'fill') {
                $fillBands[] = $band;
            } elseif ($anchor === 'bottom'
                && !in_array($bandType, self::FIXED_BOTTOM_TYPES, true)
                && $bandType !== 'summary'
            ) {
                $flowingBandsBottom[] = $band;
            }
        }

        // Calculate fixed header/footer heights for body padding
        $fixedTopHeight = 0.0;
        foreach ($fixedTopBands as $band) {
            $fixedTopHeight += (float) ($band['height'] ?? 0);
        }

        $fixedBottomHeight = 0.0;
        foreach ($fixedBottomBands as $band) {
            $fixedBottomHeight += (float) ($band['height'] ?? 0);
        }

        $elementCount = 0;

        /**
         * Count elements recursively including container children.
         */
        $countElements = function (array $elements) use (&$countElements): int {
            $count = 0;
            foreach ($elements as $el) {
                $count++;
                $elType = $el['type'] ?? '';
                if ($elType === 'container') {
                    $content = $el['content'] ?? [];
                    $children = $content['children'] ?? [];
                    $count += $countElements($children);
                }
            }
            return $count;
        };

        // ── Render fixed top bands (pageHeader: position: fixed, top of every page) ──
        $fixedTopHtml = [];
        $pageHeight = (float) ($page['height'] ?? 297);
        $marginTop = (float) ($margins['top'] ?? 10);
        $marginBottom = (float) ($margins['bottom'] ?? 10);
        $marginLeft = (float) ($margins['left'] ?? 10);

        // Fixed top bands stack downward from the top edge of the printable area.
        // With position:fixed, coordinates are relative to the page (not the body margin),
        // so we must add marginLeft to x and compute y relative to the page top.
        $fixedTopY = $marginTop;
        foreach ($fixedTopBands as $band) {
            foreach ($band['children'] ?? [] as $element) {
                $el = $element;
                $el['y'] = ($element['y'] ?? 0) + $fixedTopY;
                // Compensate left margin: x in the designer is relative to the content area,
                // but position:fixed renders relative to the page edge.
                $el['x'] = ($element['x'] ?? 0) + $marginLeft;
                $fixedTopHtml[] = $this->renderFixedElement($el, $data, $page, 'top');
            }
            $fixedTopY += (float) ($band['height'] ?? 0);
            $elementCount += $countElements($band['children'] ?? []);
        }

        // ── Render fixed bottom bands (pageFooter: position: fixed, bottom of every page) ──
        $fixedBottomHtml = [];

        // Fixed bottom bands stack upward from the bottom edge of the printable area.
        // With position:fixed, coordinates are relative to the page (not the body margin),
        // so we must add marginLeft to x and compute top relative to the page bottom.
        $fixedBottomY = $pageHeight - $marginBottom;
        foreach (array_reverse($fixedBottomBands) as $band) {
            $fixedBottomY -= (float) ($band['height'] ?? 0);
            foreach ($band['children'] ?? [] as $element) {
                $el = $element;
                $el['y'] = ($element['y'] ?? 0) + $fixedBottomY;
                // Compensate left margin: x in the designer is relative to the content area,
                // but position:fixed renders relative to the page edge.
                $el['x'] = ($element['x'] ?? 0) + $marginLeft;
                $fixedBottomHtml[] = $this->renderFixedElement($el, $data, $page, 'bottom');
            }
            $elementCount += $countElements($band['children'] ?? []);
        }

        // ── Render flowing content as band containers ──
        // iReport order: Title → PageHeader → ColumnHeader → Detail → ColumnFooter → Summary
        // All flow naturally via .pdf-band divs — DomPDF handles page breaks.
        $flowingHtml = [];

        // Top-anchored bands flow first (title, pageHeader, columnHeader)
        foreach ($flowingBandsTop as $band) {
            $bandHtml = $this->renderBandContainer($band, $data, $page);
            if ($bandHtml !== '') {
                $flowingHtml[] = $bandHtml;
                $elementCount += $countElements($band['children'] ?? []);
            }
        }

        // Fill/detail bands
        foreach ($fillBands as $band) {
            $bandType = $band['type'] ?? '';
            $collectionPath = $band['collectionPath'] ?? null;
            $datasourceId = $band['datasourceId'] ?? null;

            // Detail bands iterate over a collection — it's their purpose.
            // collectionPath = "" means root data is the collection (root-level array API).
            // collectionPath = "orders" means $data['orders'] is the collection.
            // collectionPath = null means no explicit collection binding:
            //   - If datasourceId is set, it likely had collectionPath="" before
            //     ConvertEmptyStringsToNull nullified it. Treat as root array.
            //   - If no datasourceId, render once (no iteration data available).
            $iterates = false;
            if ($bandType === 'detail') {
                if ($collectionPath !== null && $collectionPath !== '') {
                    // Named collection path, e.g. "orders" or "data.results"
                    $iterates = true;
                } elseif ($collectionPath === '' || $datasourceId !== null) {
                    // Empty string or null with datasource → root-level array iteration.
                    // Convert null to "" for resolveCollection (both mean "root data").
                    $collectionPath = '';
                    $iterates = true;
                }
                // Otherwise: collectionPath is null and no datasourceId → render once.
            }

            if ($iterates) {
                $collection = $this->resolveCollection($data, $collectionPath);

                if ($collection === null || $collection === []) {
                    continue;
                }

                foreach ($collection as $item) {
                    $localData = is_array($item) ? $item : [$item];
                    $bandHtml = $this->renderBandContainer($band, $data, $page, $localData);
                    if ($bandHtml !== '') {
                        $flowingHtml[] = $bandHtml;
                        $elementCount += $countElements($band['children'] ?? []);
                    }
                }
            } else {
                // Fill band without collection: render once
                $bandHtml = $this->renderBandContainer($band, $data, $page);
                if ($bandHtml !== '') {
                    $flowingHtml[] = $bandHtml;
                    $elementCount += $countElements($band['children'] ?? []);
                }
            }
        }

        // Flowing bottom bands (columnFooter, etc.)
        foreach ($flowingBandsBottom as $band) {
            $bandHtml = $this->renderBandContainer($band, $data, $page);
            if ($bandHtml !== '') {
                $flowingHtml[] = $bandHtml;
                $elementCount += $countElements($band['children'] ?? []);
            }
        }

        // Summary bands — rendered at the end, last page only
        foreach ($summaryBands as $band) {
            $bandHtml = $this->renderBandContainer($band, $data, $page);
            if ($bandHtml !== '') {
                $flowingHtml[] = $bandHtml;
                $elementCount += $countElements($band['children'] ?? []);
            }
        }

        // Combine: fixed header, fixed footer, then flowing bands.
        // Fixed header goes first so it renders at the top layer.
        $bodyContent = implode("\n", [
            ...$fixedTopHtml,
            ...$fixedBottomHtml,
            ...$flowingHtml,
        ]);

        // Body padding: add fixed header/footer heights so flowing content
        // doesn't overlap with the fixed-position bands.
        $html = $this->wrapInDocument($bodyContent, $page, $title, $fixedTopHeight, $fixedBottomHeight);

        return new LayoutResult(
            html: $html,
            title: $title,
            paperSize: $paperSize,
            orientation: $orientation,
            page: $page,
            elementCount: $elementCount,
        );
    }

    /**
     * Render a band as a positioned container div with its elements inside.
     *
     * Each band becomes:
     *   <div class="pdf-band" style="height: Xmm; position: relative;">
     *     <div class="pdf-element" style="left: ...mm; top: ...mm; ...">...</div>
     *   </div>
     *
     * This uses CSS flow for natural page breaking by DomPDF.
     * Elements inside use their band-relative Y coordinates.
     */
    private function renderBandContainer(array $band, array $data, array $page, array $localData = []): string
    {
        $elements = $band['children'] ?? [];
        if (empty($elements)) {
            // Still output the band div for spacing even if empty
            $height = (float) ($band['height'] ?? 0);
            return '<div class="pdf-band" style="height: ' . $this->escape((string) $height) . 'mm; position: relative;"></div>';
        }

        $height = (float) ($band['height'] ?? 0);
        $rendered = [];

        foreach ($elements as $element) {
            $rendered[] = $this->renderElement($element, $data, $page, $localData);
        }

        $innerContent = implode("\n", $rendered);

        return '<div class="pdf-band" style="height: ' . $this->escape((string) $height) . 'mm; position: relative;">'
            . "\n" . $innerContent . "\n"
            . '</div>';
    }

    /**
     * Render an element as a fixed-position element (repeats on every page).
     * Adds the 'pdf-fixed' CSS class so DomPDF renders it on every page.
     */
    private function renderFixedElement(array $element, array $data, array $page, string $position): string
    {
        $html = $this->renderElement($element, $data, $page);

        // Add 'pdf-fixed' class for position:fixed
        $html = str_replace('class="pdf-element"', 'class="pdf-element pdf-fixed"', $html);

        return $html;
    }

    /**
     * Validate band heights against the printable area.
     *
     * @throws InvalidLayoutException
     */
    private function validateBandHeights(array $page): void
    {
        $bands = $page['bands'] ?? [];
        if (empty($bands)) {
            return;
        }

        $margins = $page['margins'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
        $printableHeight = (float) $page['height'] - (float) $margins['top'] - (float) $margins['bottom'];

        $totalHeight = 0.0;
        foreach ($bands as $index => $band) {
            // Skip disabled bands — they don't participate in layout
            if (($band['enabled'] ?? true) === false) {
                continue;
            }

            $height = $band['height'] ?? null;

            if ($height === null || !is_numeric($height) || (float) $height <= 0) {
                throw InvalidLayoutException::invalidBandHeight((int) $index);
            }

            $totalHeight += (float) $height;
        }

        if ($totalHeight > $printableHeight) {
            $overflow = $totalHeight - $printableHeight;
            throw InvalidLayoutException::bandHeightOverflow($totalHeight, $printableHeight, $overflow);
        }
    }

    /**
     * Resolve a collection from data using a collectionPath.
     *
     * An empty collectionPath means the root data IS the collection
     * (e.g., when the API response is a root-level array).
     * Both null and "" are treated as "root data" for detail bands.
     */
    private function resolveCollection(array $data, string $collectionPath): ?array
    {
        $path = preg_replace('/\[\]$/', '', $collectionPath);

        // Empty path = root data is the collection (root-level array response)
        if ($path === '' || $path === '0') {
            // Root-level data must be an indexed array to iterate
            if ($this->isIndexedArray($data)) {
                return $data;
            }

            // If root is an object, wrap it as a single-item collection
            return [$data];
        }

        $value = $this->arrayGetNested($data, $path);

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            return [$value];
        }

        return $value;
    }

    /**
     * Resolve a dot-notation path from a nested array.
     */
    private function arrayGetNested(array $data, string $key): mixed
    {
        $segments = explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Validate the config structure.
     *
     * @throws InvalidLayoutException
     */
    private function validateConfig(array $config): void
    {
        if (!isset($config['page'])) {
            throw InvalidLayoutException::missingField('page');
        }

        if (!isset($config['page']['width']) || !isset($config['page']['height'])) {
            throw InvalidLayoutException::invalidStructure('Page dimensions (width, height) are required.');
        }
    }

    /**
     * Render a single element by delegating to the appropriate renderer.
     *
     * Public so that renderers needing child rendering (e.g., ContainerElementRenderer)
     * can dispatch child elements through the engine.
     *
     * @throws InvalidLayoutException
     */
    public function renderElement(array $element, array $data, array $page, array $localData = []): string
    {
        $type = $element['type'] ?? '';

        if (!isset($this->renderers[$type])) {
            throw InvalidLayoutException::invalidElementType($type);
        }

        return $this->renderers[$type]->render($element, $data, $page, $localData);
    }

    /**
     * Wrap rendered elements in a full HTML document with CSS.
     *
     * @param string $bodyContent The rendered HTML elements
     * @param array $page Page configuration
     * @param string $title Document title
     * @param float $fixedTopHeight Total height of fixed-top bands (pageHeader + columnHeader)
     * @param float $fixedBottomHeight Total height of fixed-bottom bands (pageFooter)
     */
    private function wrapInDocument(
        string $bodyContent,
        array $page,
        string $title,
        float $fixedTopHeight = 0.0,
        float $fixedBottomHeight = 0.0,
    ): string {
        $margins = $page['margins'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
        $pageWidth = $page['width'] ?? 210;

        $usableWidth = $pageWidth - $margins['left'] - $margins['right'];

        // Content area starts at: page margin top + fixed header height
        // Content area ends before: page margin bottom + fixed footer height
        $marginTop = (float) $margins['top'] + $fixedTopHeight;
        $marginBottom = (float) $margins['bottom'] + $fixedBottomHeight;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$this->escape($title)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
            position: relative;
            width: {$usableWidth}mm;
            margin: {$marginTop}mm {$margins['right']}mm {$marginBottom}mm {$margins['left']}mm;
        }
        .pdf-element {
            position: absolute;
        }
        .pdf-element img {
            max-width: 100%;
            height: auto;
        }
        .pdf-band {
            position: relative;
            width: 100%;
        }
        .pdf-fixed {
            position: fixed !important;
        }
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-table th, .pdf-table td {
            padding: 0;
            line-height: 1;
            text-align: left;
        }
        .page-number {
            position: absolute;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
        .pdf-line {
            overflow: visible;
        }
    </style>
</head>
<body>
{$bodyContent}
</body>
</html>
HTML;
    }

    /**
     * Escape HTML special characters.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Get all registered renderer types.
     *
     * @return string[]
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->renderers);
    }

    /**
     * Check if an array is a sequential (indexed) array.
     */
    private function isIndexedArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}