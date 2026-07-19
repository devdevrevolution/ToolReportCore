<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Engine;

use Com\Tecnick\Pdf\Tcpdf;
use Toolreport\Core\Modules\PdfEngine\Containers\HBox;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\Table;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\TableCell;
use Toolreport\Core\Modules\PdfEngine\Containers\Table\TableRow;
use Toolreport\Core\Modules\PdfEngine\Containers\VBox;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;
use Toolreport\Core\Modules\PdfEngine\Exceptions\UnknownComponentException;
use Toolreport\Core\Modules\PdfEngine\Primitives\Image;
use Toolreport\Core\Modules\PdfEngine\Primitives\Label;
use Toolreport\Core\Modules\PdfEngine\Primitives\Shape;

class ReportCompiler
{
    private array $data = [];

    private ?Tcpdf $pdf = null;

    private ?FontMetrics $font_metrics = null;

    private ?DebugAnnotation $debugAnnotation = null;

    public function __construct(
        private readonly ?Tcpdf $injected_pdf = null,
        private readonly ?FontMetrics $injected_font_metrics = null,
        private readonly bool $debug = false,
        private readonly array $fileOptions = [],
    ) {}

    public function compile(array $config, array $data = []): string
    {
        $this->data = $data;

        $page_config = $config['page'] ?? $config;
        $page_width = (float) ($page_config['width'] ?? 210);
        $page_height = (float) ($page_config['height'] ?? 297);
        $margins = $page_config['margins'] ?? $page_config['margin'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];

        $this->pdf = $this->injected_pdf ?? new Tcpdf('mm', true, false, true, '', null, $this->fileOptions);
        $this->font_metrics = $this->injected_font_metrics ?? new FontMetrics($this->pdf);

        if ($this->debug) {
            $this->debugAnnotation = new DebugAnnotation($this->pdf, $this->font_metrics);
        }

        $margin_top = (float) ($margins['top'] ?? 10);
        $margin_right = (float) ($margins['right'] ?? 10);
        $margin_bottom = (float) ($margins['bottom'] ?? 10);
        $margin_left = (float) ($margins['left'] ?? 10);

        $this->pdf->addPage();
        // We manage pagination ourselves — disable tc-lib-pdf's auto page break
        // to prevent addTextCell overflow from silently creating extra pages.
        $this->pdf->page->enableAutoPageBreak(false);

        $all_bands = $page_config['bands'] ?? [];

        // ── Phase 1: Pre-distribution ──────────────────

        // 1. Classify bands by pagination role
        $classified = $this->classifyBands($all_bands);
        $title = $classified['title'];
        $top_repeat = $classified['topRepeating'];
        $detail = $classified['detail'];
        $bottom_repeat = $classified['bottomRepeating'];
        $summary = $classified['summary'];
        $page_footer = $classified['pageFooter'];

        // 2. Compute band heights (use band['height'] property, not content height)
        $title_height = ($title !== null)
            ? (float) ($title['height'] ?? 0)
            : 0.0;

        $top_repeat_total = 0.0;
        foreach ($top_repeat as $band) {
            $top_repeat_total += (float) ($band['height'] ?? 0);
        }

        $bottom_repeat_total = 0.0;
        foreach ($bottom_repeat as $band) {
            $bottom_repeat_total += (float) ($band['height'] ?? 0);
        }

        $summary_height = ($summary !== null)
            ? (float) ($summary['height'] ?? 0)
            : 0.0;

        $page_footer_height = ($page_footer !== null)
            ? (float) ($page_footer['height'] ?? 0)
            : 0.0;

        // 3. Compute available heights per page type
        $available_first = $page_height - $margin_top - $margin_bottom - $title_height - $top_repeat_total - $bottom_repeat_total - $page_footer_height;
        $available_middle = $page_height - $margin_top - $margin_bottom - $top_repeat_total - $bottom_repeat_total - $page_footer_height;

        // 4. Pre-distribute detail items into page buckets
        $detail_nodes = [];
        $collection = [];
        $page_buckets = [[]];

        if ($detail !== null) {
            $detail_nodes = $this->extractRootNodes($detail);
            $collection_path = $detail['collectionPath'] ?? null;
            if ($collection_path !== null) {
                $collection = $this->resolveCollection($collection_path);
            }
            if ($collection !== null && is_array($collection) && count($collection) > 0) {
                $detail_heights = $this->precomputeDetailHeights($detail_nodes, $collection);
                $page_buckets = $this->distributeItemsIntoPages($detail_heights, $available_first, $available_middle);
            }
        }

        $total_pages = count($page_buckets);

        // ── Phase 2: Render loop ───────────────────────

        foreach ($page_buckets as $page_idx => $page_items) {
            if ($page_idx > 0) {
                $this->pdf->addPage();
                $this->pdf->page->enableAutoPageBreak(false);
            }

            $this->renderPage(
                $page_idx,
                $total_pages,
                $page_items,
                $title,
                $top_repeat,
                $detail_nodes,
                $collection,
                $bottom_repeat,
                $summary,
                $page_footer,
                $margin_left,
                $page_height,
                $margin_top,
                $margin_bottom,
                $top_repeat_total,
                $bottom_repeat_total,
                $summary_height,
                $page_footer_height,
            );
        }

        return $this->pdf->getOutPDFString();
    }

    /**
     * Render a single page with the correct bands for that page role.
     *
     * Does NOT call addPage() — the caller controls page creation.
     *
     * @param  int  $page_index  Zero-based page index
     * @param  int  $total_pages  Total number of pages
     * @param  array<int, int>  $page_item_indices  Collection item indices for this page
     * @param  array<int, array<string, mixed>>  $top_repeat_bands
     * @param  array<int, array<string, mixed>>  $detail_nodes
     * @param  array<int, mixed>|null  $collection
     * @param  array<int, array<string, mixed>>  $bottom_repeat_bands  (columnFooter)
     */
    private function renderPage(
        int $page_index,
        int $total_pages,
        array $page_item_indices,
        ?array $title,
        array $top_repeat_bands,
        array $detail_nodes,
        ?array $collection,
        array $bottom_repeat_bands,
        ?array $summary,
        ?array $page_footer,
        float $margin_left,
        float $page_height,
        float $margin_top,
        float $margin_bottom,
        float $top_repeat_total,
        float $bottom_repeat_total,
        float $summary_height,
        float $page_footer_height,
    ): void {
        $cursor_y = $margin_top;

        // First page: render title band
        if ($page_index === 0 && $title !== null) {
            $cursor_y = $this->renderBand($title, $margin_left, $cursor_y, true);
        }

        // Render top repeating bands
        foreach ($top_repeat_bands as $band) {
            $cursor_y = $this->renderBand($band, $margin_left, $cursor_y);
        }

        // Render detail items for this page
        if (count($page_item_indices) > 0 && count($detail_nodes) > 0 && $collection !== null) {
            foreach ($page_item_indices as $item_idx) {
                $item = $collection[$item_idx] ?? null;
                if ($item === null) {
                    continue;
                }
                $local_data = is_array($item) ? $item : ['value' => $item];
                $this->renderRootNodes($detail_nodes, $margin_left, $cursor_y, $local_data);
                $cursor_y += $this->computeRootNodesHeight($detail_nodes);
            }
        } elseif (count($detail_nodes) > 0) {
            // No collection items — render detail band once as static content
            // so composite elements are visible even without data (e.g. preview).
            $this->renderRootNodes($detail_nodes, $margin_left, $cursor_y, []);
            $cursor_y += $this->computeRootNodesHeight($detail_nodes);
        }

        // Summary band positioning (only on last page)
        $is_last_page = ($page_index === $total_pages - 1);
        if ($is_last_page && $summary !== null) {
            $summary_position = $summary['summaryPosition'] ?? 'afterDetail';

            if ($summary_position === 'pageBottom') {
                // iReport style: summary at bottom of page, above pageFooter
                $page_footer_start_y = $page_height - $margin_bottom - $page_footer_height;
                $summary_start_y = $page_footer_start_y - $summary_height;
                $this->renderBand($summary, $margin_left, $summary_start_y, true);
            } else {
                // After detail: right after the last detail item
                $this->renderBand($summary, $margin_left, $cursor_y, true);
                $cursor_y += $summary_height;
            }
        }

        // Column footer: right after summary (or after detail if no summary)
        foreach ($bottom_repeat_bands as $band) {
            $cursor_y = $this->renderBand($band, $margin_left, $cursor_y);
        }

        // Page footer: at the very bottom of the page
        $page_footer_start_y = $page_height - $margin_bottom - $page_footer_height;
        if ($page_footer !== null) {
            $this->renderBand($page_footer, $margin_left, $page_footer_start_y, true);
        }
    }

    /**
     * Extract the array of absolutely-positioned root nodes from a band.
     *
     * Supports two shapes:
     *  - new: `band['children']` is an array of CompositeNode with x/y.
     *  - legacy: `band['content']` is a single CompositeNode (VBox/HBox or
     *    leaf). Its children (if any) are extracted and auto-positioned at
     *    x=0 with y derived from each child's natural height stacked
     *    vertically — preserving the original flow order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractRootNodes(array $band): array
    {
        if (isset($band['children']) && is_array($band['children'])) {
            return $band['children'];
        }

        $content = $band['content'] ?? null;
        if ($content === null || ! is_array($content)) {
            return [];
        }

        // Legacy shape: a single root node. If it's a container, lift its
        // children as top-level roots; otherwise treat the node itself as
        // the only root.
        if (isset($content['children']) && is_array($content['children'])) {
            $stacked_y = 0.0;
            $roots = [];
            foreach ($content['children'] as $child) {
                $child['x'] = $child['x'] ?? 0;
                $child['y'] = $child['y'] ?? $stacked_y;
                $roots[] = $child;
                $stacked_y += (float) ($child['height'] ?? $child['h'] ?? 10);
            }

            return $roots;
        }

        $content['x'] = $content['x'] ?? 0;
        $content['y'] = $content['y'] ?? 0;

        return [$content];
    }

    /**
     * Render an array of absolutely-positioned root nodes. Each node is
     * placed at (band_x + node.x, band_y + node.y). Root nodes that are
     * containers (VBox/HBox) still lay out their children in flow relative
     * to the container's origin.
     *
     * @param  array<int, array<string, mixed>>  $root_nodes
     * @param  array<string, mixed>  $local_data
     */
    private function renderRootNodes(array $root_nodes, float $band_x, float $band_y, array $local_data): void
    {
        foreach ($root_nodes as $node) {
            // CompositeRoot format: {id, x, y, width?, height?, node: {type, ...}}
            // Legacy format: {type, ...} directly on the node
            $contentNode = $node['node'] ?? $node;

            // When the CompositeRoot specifies display dimensions, merge them
            // into the content node so the component respects the user's config.
            if (isset($node['node'])) {
                if (isset($node['width'])) {
                    $contentNode['width'] = $node['width'];
                }
                if (isset($node['height'])) {
                    $contentNode['height'] = $node['height'];
                }
            }

            $component = $this->buildContentTree($contentNode, $local_data);
            $x = $band_x + (float) ($node['x'] ?? 0);
            $y = $band_y + (float) ($node['y'] ?? 0);
            $component->render($this->pdf, $x, $y);

            // Debug: annotate effective dimensions
            if ($this->debug && $this->debugAnnotation) {
                $this->debugAnnotation->annotate($component, $x, $y, $contentNode['type'] ?? '?');
            }
        }
    }

    /**
     * Compute the vertical span occupied by a set of absolutely-positioned
     * root nodes (max y+height) relative to the band origin. Used by the
     * compiler to advance `current_y` after rendering the band.
     *
     * @param  array<int, array<string, mixed>>  $root_nodes
     */
    private function computeRootNodesHeight(array $root_nodes): float
    {
        $max_bottom = 0.0;
        foreach ($root_nodes as $node) {
            $contentNode = $node['node'] ?? $node;

            // Merge CompositeRoot display dimensions (same as renderRootNodes)
            if (isset($node['node'])) {
                if (isset($node['width'])) {
                    $contentNode['width'] = $node['width'];
                }
                if (isset($node['height'])) {
                    $contentNode['height'] = $node['height'];
                }
            }

            $component = $this->buildContentTree($contentNode, []);
            $dims = $component->getDimensions();
            $bottom = (float) ($node['y'] ?? 0) + $dims['h'];
            if ($bottom > $max_bottom) {
                $max_bottom = $bottom;
            }
        }

        return $max_bottom;
    }

    /**
     * Render a single band at the given $cursor_y and return the new cursor.
     *
     * Supports collection/detail bands (iterate items) and static bands.
     * When $skip_collection is true, the band is rendered as static even if
     * it has a collectionPath — used for title/summary during pagination.
     */
    private function renderBand(array $band, float $margin_left, float $cursor_y, bool $skip_collection = false): float
    {
        $nodes = $this->extractRootNodes($band);
        $band_height = (float) ($band['height'] ?? 0);

        $collection_path = $band['collectionPath'] ?? null;
        $band_type = $band['type'] ?? '';

        if (! $skip_collection && $collection_path !== null && ($band_type === 'detail' || $band_type === '')) {
            $collection = $this->resolveCollection($collection_path);
            if ($collection === null || ! is_array($collection) || count($collection) === 0) {
                return $cursor_y + $band_height;
            }

            foreach (array_values($collection) as $item) {
                $local_data = is_array($item) ? $item : ['value' => $item];
                $this->renderRootNodes($nodes, $margin_left, $cursor_y, $local_data);
                $cursor_y += $this->computeRootNodesHeight($nodes);
            }
        } else {
            if (count($nodes) > 0) {
                $this->renderRootNodes($nodes, $margin_left, $cursor_y, []);
            }
        }

        // Advance cursor by band height (not content height)
        return $cursor_y + $band_height;
    }

    /**
     * Greedy distribution of detail items into page buckets.
     *
     * @param  float[]  $detail_heights  Pre-computed height for each item
     * @param  float  $available_first  Available detail height on the first page
     * @param  float  $available_middle  Available detail height on middle/last pages
     * @return array<int, array<int>> pages[page_idx][] = item_index
     */
    private function distributeItemsIntoPages(array $detail_heights, float $available_first, float $available_middle): array
    {
        if (count($detail_heights) === 0) {
            return [[]];
        }

        $pages = [[]];
        $current_page = 0;
        $current_page_height = 0.0;

        foreach ($detail_heights as $idx => $item_height) {
            $available = ($current_page === 0) ? $available_first : $available_middle;
            $remaining = $available - $current_page_height;

            if ($item_height <= $remaining + 0.001) {
                // Fits on current page
                $pages[$current_page][] = $idx;
                $current_page_height += $item_height;
            } elseif ($item_height > $available) {
                // Oversized item — render on current page anyway
                $pages[$current_page][] = $idx;
                $current_page_height += $item_height;
            } else {
                // Doesn't fit — new page
                $pages[] = [$idx];
                $current_page++;
                $current_page_height = $item_height;
            }
        }

        return $pages;
    }

    /**
     * Pre-compute heights for all detail items without rendering.
     *
     * @param  array<int, array<string, mixed>>  $detail_nodes
     * @param  array<int, mixed>  $collection
     * @return float[]
     */
    private function precomputeDetailHeights(array $detail_nodes, array $collection): array
    {
        if (count($collection) === 0) {
            return [];
        }

        $heights = [];
        foreach (array_values($collection) as $item) {
            $local_data = is_array($item) ? $item : ['value' => $item];
            // Build component trees with item data to compute actual rendered height
            $nodes_with_data = [];
            foreach ($detail_nodes as $node) {
                $contentNode = $node['node'] ?? $node;

                // Merge CompositeRoot display dimensions (same as renderRootNodes)
                if (isset($node['node'])) {
                    if (isset($node['width'])) {
                        $contentNode['width'] = $node['width'];
                    }
                    if (isset($node['height'])) {
                        $contentNode['height'] = $node['height'];
                    }
                }

                $component = $this->buildContentTree($contentNode, $local_data);
                $dims = $component->getDimensions();
                $nodes_with_data[] = [
                    'y' => $node['y'] ?? 0,
                    '_height' => $dims['h'],
                ];
            }
            $max_bottom = 0.0;
            foreach ($nodes_with_data as $n) {
                $bottom = (float) $n['y'] + $n['_height'];
                if ($bottom > $max_bottom) {
                    $max_bottom = $bottom;
                }
            }
            $heights[] = $max_bottom;
        }

        return $heights;
    }

    /**
     * Classify bands by their pagination role.
     *
     * @param  array<int, array<string, mixed>>  $all_bands
     * @return array{title: ?array, topRepeating: array, detail: ?array, bottomRepeating: array, summary: ?array, pageFooter: ?array}
     */
    private function classifyBands(array $all_bands): array
    {
        $result = [
            'title' => null,
            'topRepeating' => [],
            'detail' => null,
            'bottomRepeating' => [],
            'summary' => null,
            'pageFooter' => null,
        ];

        foreach ($all_bands as $band) {
            if (! ($band['enabled'] ?? true)) {
                continue;
            }

            $type = $band['type'] ?? '';
            $anchor = $band['anchor'] ?? 'top';

            match ($type) {
                'title' => $result['title'] = $band,
                'detail' => $result['detail'] = $band,
                'summary' => $result['summary'] = $band,
                'pageFooter' => $result['pageFooter'] = $band,
                'pageHeader', 'columnHeader' => $result['topRepeating'][] = $band,
                'columnFooter' => $result['bottomRepeating'][] = $band,
                default => match ($anchor) {
                    'bottom' => $result['bottomRepeating'][] = $band,
                    default => $result['topRepeating'][] = $band,
                },
            };
        }

        return $result;
    }

    public function buildContentTree(array $node, array $local_data = []): Component
    {
        $type = $node['type'] ?? '';

        return match ($type) {
            'VBox' => $this->buildVBox($node, $local_data),
            'HBox' => $this->buildHBox($node, $local_data),
            'Label' => $this->buildLabel($node, $local_data),
            'Shape' => $this->buildShape($node),
            'Image' => $this->buildImage($node, $local_data),
            'Table' => $this->buildTable($node, $local_data),
            default => throw new UnknownComponentException($type),
        };
    }

    private function buildVBox(array $node, array $local_data): Component
    {
        $children = [];
        foreach ($node['children'] ?? [] as $child_node) {
            $children[] = $this->buildContentTree($child_node, $local_data);
        }

        $vbox = new VBox(
            $children,
            (float) ($node['padding'] ?? 0)
        );

        if (isset($node['width'])) {
            $vbox->setWidth((float) $node['width']);
        }

        if (isset($node['height'])) {
            $vbox->setHeight((float) $node['height']);
        }

        if (isset($node['margin']) && is_array($node['margin'])) {
            $vbox->setMargin($node['margin']);
        }

        return $vbox;
    }

    private function buildHBox(array $node, array $local_data): Component
    {
        $children = [];
        foreach ($node['children'] ?? [] as $child_node) {
            $children[] = $this->buildContentTree($child_node, $local_data);
        }

        $hbox = new HBox($children);
        if (isset($node['width'])) {
            $hbox->setWidth((float) $node['width']);
        }

        if (isset($node['height'])) {
            $hbox->setHeight((float) $node['height']);
        }

        if (isset($node['margin']) && is_array($node['margin'])) {
            $hbox->setMargin($node['margin']);
        }

        return $hbox;
    }

    private function buildLabel(array $node, array $local_data): Label
    {
        $label = new Label(
            $node['text'] ?? '',
            $this->getFontMetrics(),
        );

        if (isset($node['fontFamily'])) {
            $label->setFontFamily($node['fontFamily']);
        }
        if (isset($node['fontSize'])) {
            $label->setFontSize((float) $node['fontSize']);
        }
        if (isset($node['style'])) {
            $label->setStyle($node['style']);
        }
        if (isset($node['color'])) {
            $label->setColor($node['color']);
        }
        if (isset($node['width'])) {
            $label->setWidth((float) $node['width']);
        }
        if (isset($node['height'])) {
            $label->setHeight((float) $node['height']);
        }
        if (isset($node['margin']) && is_array($node['margin'])) {
            $label->setMargin($node['margin']);
        }

        $label->setGlobalData($this->data);
        $label->setLocalData($local_data);

        return $label;
    }

    private function buildShape(array $node): Shape
    {
        $shape = new Shape($node['shapeType'] ?? 'line');

        if (isset($node['x1'])) {
            $shape->setX1((float) $node['x1']);
        }
        if (isset($node['y1'])) {
            $shape->setY1((float) $node['y1']);
        }
        if (isset($node['x2'])) {
            $shape->setX2((float) $node['x2']);
        }
        if (isset($node['y2'])) {
            $shape->setY2((float) $node['y2']);
        }
        if (isset($node['width'])) {
            $shape->setWidth((float) $node['width']);
        } elseif (isset($node['w'])) {
            $shape->setWidth((float) $node['w']);
        }
        if (isset($node['height'])) {
            $shape->setHeight((float) $node['height']);
        } elseif (isset($node['h'])) {
            $shape->setHeight((float) $node['h']);
        }
        if (isset($node['color'])) {
            $shape->setColor($node['color']);
        }
        if (isset($node['fillColor'])) {
            $shape->setFillColor($node['fillColor']);
        }
        if (isset($node['strokeWidth'])) {
            $shape->setStrokeWidth((float) $node['strokeWidth']);
        }
        if (isset($node['lineStyle'])) {
            $shape->setLineStyle($node['lineStyle']);
        }
        if (isset($node['borderRadius'])) {
            $shape->setBorderRadius((float) $node['borderRadius']);
        }
        if (isset($node['margin']) && is_array($node['margin'])) {
            $shape->setMargin($node['margin']);
        }

        return $shape;
    }

    private function buildImage(array $node, array $local_data): Image
    {
        $image = new Image();
        $image->setUrl($node['url'] ?? '');

        if (isset($node['width'])) {
            $image->setWidth((float) $node['width']);
        }
        if (isset($node['height'])) {
            $image->setHeight((float) $node['height']);
        }
        if (isset($node['objectFit'])) {
            $image->setObjectFit($node['objectFit']);
        }
        if (isset($node['opacity'])) {
            $image->setOpacity((float) $node['opacity']);
        }
        if (isset($node['borderRadius'])) {
            $image->setBorderRadius((float) $node['borderRadius']);
        }
        if (isset($node['margin']) && is_array($node['margin'])) {
            $image->setMargin($node['margin']);
        }

        // Shape properties
        if (isset($node['shapeType'])) {
            $image->setShapeType($node['shapeType']);
        }
        if (isset($node['fillColor'])) {
            $image->setFillColor($node['fillColor']);
        }
        if (isset($node['strokeColor'])) {
            $image->setStrokeColor($node['strokeColor']);
        }
        if (isset($node['strokeWidth'])) {
            $image->setStrokeWidth((float) $node['strokeWidth']);
        }
        if (isset($node['lineStyle'])) {
            $image->setLineStyle($node['lineStyle']);
        }

        $image->setGlobalData($this->data);
        $image->setLocalData($local_data);

        return $image;
    }

    private function buildTable(array $node, array $local_data): Table
    {
        $column_widths = array_map('floatval', $node['columnWidths'] ?? []);
        $rows = [];

        foreach ($node['rows'] ?? [] as $row_node) {
            $cells = [];
            foreach ($row_node as $i => $cell_node) {
                $child = $this->buildContentTree($cell_node, $local_data);
                $width = $column_widths[$i] ?? 20;
                $cells[] = new TableCell($child, $width);
            }
            $rows[] = new TableRow($cells, $column_widths);
        }

        $table = new Table($rows, $column_widths);

        if (isset($node['margin']) && is_array($node['margin'])) {
            $table->setMargin($node['margin']);
        }

        return $table;
    }

    private function getFontMetrics(): FontMetrics
    {
        if ($this->font_metrics === null) {
            if ($this->pdf === null) {
                $tcpdf_class = Tcpdf::class;
                $this->pdf = $this->injected_pdf ?? new $tcpdf_class('mm', true, false, true);
            }

            $this->font_metrics = $this->injected_font_metrics ?? new FontMetrics($this->pdf);
        }

        return $this->font_metrics;
    }

    private function resolveCollection(string $path): ?array
    {
        $path = preg_replace('/\[\]$/', '', $path);
        $segments = explode('.', $path);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        if (is_array($current)) {
            if (array_keys($current) !== range(0, count($current) - 1)) {
                return [$current];
            }

            return $current;
        }

        return [$current];
    }
}
