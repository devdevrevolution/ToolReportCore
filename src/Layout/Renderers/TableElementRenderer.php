<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout\Renderers;

use Illuminate\Support\Facades\Log;
use Toolreport\Core\Layout\ElementRendererInterface;
use Toolreport\Core\Layout\InterpolatesVariables;

class TableElementRenderer implements ElementRendererInterface
{
    use InterpolatesVariables;

    public function type(): string
    {
        return 'table';
    }

    /**
     * Compute proportional column width percentages.
     *
     * Uses the element width as the reference so PDF proportions match the
     * canvas exactly: each column takes (col.width / element.width) × 100%
     * of the container, identical to how the canvas renders
     * (col.width × scale px within element.width × scale px).
     *
     * Returns an array of inline CSS width values (e.g. "width: 31.58%;")
     * indexed by column position, or an empty array if not all columns have widths.
     *
     * @param array<int, array{key: string, header: string, width?: float, align: string}> $columns
     * @param float $elementWidth Element width in mm — used as the 100% reference
     * @return array<int, string>
     */
    private function computeColWidths(array $columns, float $elementWidth): array
    {
        $allSet = true;

        foreach ($columns as $col) {
            if (!isset($col['width']) || $col['width'] <= 0) {
                $allSet = false;
            }
        }

        if (!$allSet || $elementWidth <= 0) {
            return [];
        }

        $widths = [];
        foreach ($columns as $col) {
            $pct = round($col['width'] / $elementWidth * 100, 2);
            $widths[] = "width: {$pct}%;";
        }

        return $widths;
    }

    public function render(array $element, array $data, array $page, array $localData = []): string
    {
        $content = $element['content'] ?? [];
        $styles = $element['styles'] ?? [];
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 190;
        $height = $element['height'] ?? 100;

        // Resolve columns: prefer new format, fall back to legacy header[]
        $columns = $this->resolveColumns($content);

        // Pre-compute proportional column widths (empty array if not all set)
        $colWidths = $this->computeColWidths($columns, $width);

        // Resolve rows from variable binding, fall back to static rows
        $rows = $this->resolveRows($content, $data, $localData);

        $merges = $content['merges'] ?? [];

        $hasHeader = $content['showHeader'] ?? $content['header'] ?? true;
        $borderWidth = $styles['borderWidth'] ?? 0.5;
        $borderColor = $styles['borderColor'] ?? '#cccccc';
        $headerBg = $styles['headerBackground'] ?? '#f5f5f5';
        $fontSize = $styles['fontSize'] ?? 9;
        $color = $styles['color'] ?? '#000000';

        $html = "<div class=\"pdf-element\" style=\""
            . "left: {$x}mm; top: {$y}mm; width: {$width}mm; height: {$height}mm; "
            . "overflow: visible; font-size: {$fontSize}pt; color: {$color}; "
            . "\">";

        $tableBorder = $borderWidth . 'pt solid ' . $borderColor;
        $html .= '<table class="pdf-table" style="border-collapse: collapse; border: ' . $tableBorder . '; table-layout: fixed; width: ' . $width . 'mm;">';

        // ── Column widths (<colgroup>) — ensures table-layout:fixed honors
        //     widths EVEN when thead/tbody cells have colspan/rowspan merges.
        //     Uses same pct formula as the designer's cellWidthStyle.
        if (!empty($colWidths)) {
            $html .= '<colgroup>';
            foreach ($colWidths as $w) {
                $html .= '<col style="' . $w . '">';
            }
            $html .= '</colgroup>';
        }

        // ── Header ──────────────────────────────────────
        if ($hasHeader && !empty($columns)) {
            $html .= '<thead><tr>';
            foreach ($columns as $colIdx => $col) {
                $headerText = $col['header'] ?? $col['key'] ?? '';
                $widthStyle = $colWidths[$colIdx] ?? '';
                $align = $col['align'] ?? 'left';

                // Per-column header style overrides
                $headerStyle = $col['headerStyle'] ?? [];
                $headerCss = $this->resolveCellStyleOverride($headerStyle, $styles);
                $headerBgFinal = $headerStyle['backgroundColor'] ?? $headerBg;

                $html .= "<th style=\""
                    . "background-color: {$headerBgFinal}; "
                    . "border: {$tableBorder}; "
                    . "text-align: {$align}; {$widthStyle} {$headerCss}"
                    . "\">{$this->escape($headerText)}</th>";
            }
            $html .= '</tr></thead>';
        }

        // ── Body ────────────────────────────────────────
        $html .= '<tbody>';
        if (!empty($rows) && !empty($columns)) {
            foreach ($rows as $rowIndex => $row) {
                $html .= '<tr>';

                foreach ($columns as $colIndex => $col) {
                    $key = $col['key'] ?? '';

                    // Skip cells that are covered by a merge origin
                    if ($this->isCellCovered($rowIndex, $colIndex, $merges)) {
                        continue;
                    }

                    // Check if this cell is a merge origin
                    $merge = $this->findMergeAt($rowIndex, $colIndex, $merges);
                    $colspan = $merge ? $merge['colspan'] : 1;
                    $rowspan = $merge ? $merge['rowspan'] : 1;

                    // Resolve cell value from static row data or dynamic data
                    $cellContent = $this->resolveCellContent($row, $key, $merge !== null);

                    // Apply per-cell style overrides
                    $cellStyleOverride = $this->resolveCellStyleOverride($cellContent, $styles);

                    $align = $col['align'] ?? 'left';
                    $cellStyle = ($rowIndex % 2 === 1 && !$merge) ? 'background-color: #fafafa;' : '';

                    // Apply proportional width — skip on merge origins (colspan handles it)
                    $colWidthStyle = (!$merge && isset($colWidths[$colIndex])) ? $colWidths[$colIndex] : '';

                    $html .= "<td"
                        . ($colspan > 1 ? " colspan=\"{$colspan}\"" : '')
                        . ($rowspan > 1 ? " rowspan=\"{$rowspan}\"" : '')
                        . " style=\""
                        . "border: {$tableBorder}; "
                        . "text-align: {$align}; "
                        . $cellStyle
                        . $cellStyleOverride
                        . $colWidthStyle
                        . "\">{$this->escape($cellContent['text'] ?? '')}</td>";
                }

                $html .= '</tr>';
            }
        } elseif (empty($columns)) {
            // No columns configured — show a placeholder cell
            $html .= '<tr><td style="border: ' . $tableBorder . '; color: #999; font-style: italic;">'
                . $this->escape('Configure columns in the designer')
                . '</td></tr>';
        }
        $html .= '</tbody>';

        $html .= '</table>';
        $html .= '</div>';

        // ── Log table dimensions for designer-vs-render comparison ──
        $colInfo = [];
        foreach ($columns as $i => $col) {
            $pct = isset($colWidths[$i]) ? trim($colWidths[$i], 'width: %;') : 'auto';
            $colInfo[] = [
                'idx' => $i,
                'key' => $col['key'],
                'header' => $col['header'],
                'width_mm' => $col['width'] ?? null,
                'width_pct' => $pct,
            ];
        }

        $rowInfo = [];
        foreach ($rows as $ri => $row) {
            $cellCount = is_array($row) ? count($row) : 0;
            $rowInfo[] = [
                'idx' => $ri,
                'cells' => $cellCount,
            ];
        }

        $mergeCount = count($merges);

        Log::debug('[TableElement] Render dimensions', [
            'element_id' => $element['id'] ?? 'unknown',
            'position_mm' => ['x' => $x, 'y' => $y],
            'size_mm' => ['width' => $width, 'height' => $height],
            'columns' => $colInfo,
            'rows' => $rowInfo,
            'merge_count' => $mergeCount,
        ]);

        return $html;
    }

    /**
     * Resolve columns from content, supporting both new and legacy formats.
     *
     * @param array<string, mixed> $content
     * @return array<int, array{key: string, header: string, width?: float, align: string}>
     */
    private function resolveColumns(array $content): array
    {
        // New format already in content.columns
        if (!empty($content['columns']) && is_array($content['columns'])) {
            $columns = [];
            foreach ($content['columns'] as $col) {
                if (!is_array($col)) continue;
                $columns[] = [
                    'key' => (string) ($col['key'] ?? ''),
                    'header' => (string) ($col['header'] ?? $col['key'] ?? ''),
                    'width' => isset($col['width']) ? (float) $col['width'] : null,
                    'align' => in_array($col['align'] ?? '', ['left', 'center', 'right'], true)
                        ? $col['align']
                        : 'left',
                    'headerStyle' => isset($col['headerStyle']) && is_array($col['headerStyle'])
                        ? $col['headerStyle']
                        : [],
                ];
            }
            return $columns;
        }

        // Legacy format: content.header = string[] + content.columnWidths = number[]
        if (!empty($content['header']) && is_array($content['header'])) {
            $widths = $content['columnWidths'] ?? [];
            $columns = [];
            foreach ($content['header'] as $i => $headerText) {
                $slug = $this->headerToKey((string) $headerText);
                $columns[] = [
                    'key' => $slug,
                    'header' => (string) $headerText,
                    'width' => isset($widths[$i]) ? (float) $widths[$i] : null,
                    'align' => 'left',
                ];
            }
            return $columns;
        }

        return [];
    }

    /**
     * Resolve rows from variable binding or static data.
     *
     * Priority:
     * 1. Variable binding → resolve from $data / $localData (dynamic)
     * 2. Static rows in content
     * 3. Empty array
     *
     * @param array<string, mixed> $content
     * @param array<string, mixed> $data
     * @param array<string, mixed> $localData
     * @return array<int, mixed>
     */
    private function resolveRows(array $content, array $data, array $localData = []): array
    {
        $variable = $content['variable'] ?? '';

        if ($variable !== '') {
            // Resolve collection from data context
            $collection = $this->resolveVariableKey($variable, $data, $localData);

            if (is_array($collection)) {
                $values = array_values($collection);
                return $values;
            }
        }

        // Fall back to static rows
        return $content['rows'] ?? [];
    }

    /**
     * Resolve the content of a single cell.
     *
     * When the row is a static row (keyed by column key), the cell content
     * is a TableCellContent array with 'text' and optional style overrides.
     * When it's a dynamic row (indexed array), the value comes from the key directly.
     *
     * @param mixed $row
     * @param string $key
     * @param bool $isMergeOrigin Whether this cell is a merge origin
     * @return array{text: string, fontFamily?: string, fontSize?: float, fontWeight?: string, fontStyle?: string, color?: string, textAlign?: string, backgroundColor?: string, verticalAlign?: string, nowrap?: bool, padding?: array{top: float, right: float, bottom: float, left: float}}
     */
    private function resolveCellContent(mixed $row, string $key, bool $isMergeOrigin): array
    {
        // Static row: key-value where value is TableCellContent
        if (is_array($row) && isset($row[$key]) && is_array($row[$key])) {
            return [
                'text' => (string) ($row[$key]['text'] ?? ''),
                'fontFamily' => isset($row[$key]['fontFamily']) ? (string) $row[$key]['fontFamily'] : null,
                'fontSize' => isset($row[$key]['fontSize']) ? (float) $row[$key]['fontSize'] : null,
                'fontWeight' => isset($row[$key]['fontWeight']) ? (string) $row[$key]['fontWeight'] : null,
                'fontStyle' => isset($row[$key]['fontStyle']) ? (string) $row[$key]['fontStyle'] : null,
                'color' => isset($row[$key]['color']) ? (string) $row[$key]['color'] : null,
                'textAlign' => isset($row[$key]['textAlign']) ? (string) $row[$key]['textAlign'] : null,
                'backgroundColor' => isset($row[$key]['backgroundColor']) ? (string) $row[$key]['backgroundColor'] : null,
                'verticalAlign' => isset($row[$key]['verticalAlign']) ? (string) $row[$key]['verticalAlign'] : null,
                'nowrap' => isset($row[$key]['nowrap']) ? (bool) $row[$key]['nowrap'] : false,
                'padding' => isset($row[$key]['padding']) && is_array($row[$key]['padding'])
                    ? [
                        'top' => (float) ($row[$key]['padding']['top'] ?? 0),
                        'right' => (float) ($row[$key]['padding']['right'] ?? 0),
                        'bottom' => (float) ($row[$key]['padding']['bottom'] ?? 0),
                        'left' => (float) ($row[$key]['padding']['left'] ?? 0),
                    ]
                    : null,
            ];
        }

        // Dynamic row: use dot-notation to resolve value, return as plain text
        $text = $key !== '' ? $this->arrayGet((array) $row, $key, '') : '';
        return ['text' => (string) $text];
    }

    /**
     * Build inline CSS from cell-level style overrides, merged over element defaults.
     *
     * @param array $cellContent
     * @param array $elementStyles
     * @return string Inline CSS fragment
     */
    private function resolveCellStyleOverride(array $cellContent, array $elementStyles): string
    {
        $css = '';

        if (!empty($cellContent['fontFamily'])) {
            $css .= 'font-family: ' . $cellContent['fontFamily'] . '; ';
        }
        if (!empty($cellContent['fontSize'])) {
            $css .= 'font-size: ' . $cellContent['fontSize'] . 'pt; ';
        }
        if (!empty($cellContent['fontWeight'])) {
            $css .= 'font-weight: ' . $cellContent['fontWeight'] . '; ';
        }
        if (!empty($cellContent['fontStyle'])) {
            $css .= 'font-style: ' . $cellContent['fontStyle'] . '; ';
        }
        if (!empty($cellContent['color'])) {
            $css .= 'color: ' . $cellContent['color'] . '; ';
        }
        if (!empty($cellContent['textAlign'])) {
            $css .= 'text-align: ' . $cellContent['textAlign'] . '; ';
        }
        if (!empty($cellContent['backgroundColor'])) {
            $css .= 'background-color: ' . $cellContent['backgroundColor'] . '; ';
        }
        if (!empty($cellContent['verticalAlign'])) {
            $css .= 'vertical-align: ' . $cellContent['verticalAlign'] . '; ';
        }
        if (!empty($cellContent['padding'])) {
            $p = $cellContent['padding'];
            $css .= 'padding: '
                . ($p['top'] ?? 0) . 'pt '
                . ($p['right'] ?? 0) . 'pt '
                . ($p['bottom'] ?? 0) . 'pt '
                . ($p['left'] ?? 0) . 'pt; ';
        }

        // No-wrap / ellipsis when nowrap is true
        if (!empty($cellContent['nowrap'])) {
            $css .= 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis; ';
        }

        return $css;
    }

    /**
     * Check whether a cell at (row, col) is covered by a merge.
     *
     * @param int $row
     * @param int $col
     * @param array<int, array{row: int, col: int, rowspan: int, colspan: int}> $merges
     * @return bool
     */
    private function isCellCovered(int $row, int $col, array $merges): bool
    {
        foreach ($merges as $m) {
            $mRow = (int) ($m['row'] ?? 0);
            $mCol = (int) ($m['col'] ?? 0);
            $mRowspan = (int) ($m['rowspan'] ?? 1);
            $mColspan = (int) ($m['colspan'] ?? 1);

            $inRow = $row >= $mRow && $row < $mRow + $mRowspan;
            $inCol = $col >= $mCol && $col < $mCol + $mColspan;
            $isOrigin = $row === $mRow && $col === $mCol;

            if ($inRow && $inCol && !$isOrigin) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find a merge origin at (row, col), if the cell is the top-left of a merge.
     *
     * @param int $row
     * @param int $col
     * @param array<int, array{row: int, col: int, rowspan: int, colspan: int}> $merges
     * @return array{row: int, col: int, rowspan: int, colspan: int}|null
     */
    private function findMergeAt(int $row, int $col, array $merges): ?array
    {
        foreach ($merges as $m) {
            if ((int) ($m['row'] ?? 0) === $row && (int) ($m['col'] ?? 0) === $col) {
                return [
                    'row' => (int) $m['row'],
                    'col' => (int) $m['col'],
                    'rowspan' => (int) ($m['rowspan'] ?? 1),
                    'colspan' => (int) ($m['colspan'] ?? 1),
                ];
            }
        }
        return null;
    }

    /**
     * Convert a header string to a sane key slug.
     * "Product Name" → "product_name"
     */
    private function headerToKey(string $header): string
    {
        $key = preg_replace('/[^a-zA-Z0-9_]+/', '_', $header);
        $key = trim($key, '_');
        $key = strtolower($key);
        return $key ?: 'col_' . md5($header);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
