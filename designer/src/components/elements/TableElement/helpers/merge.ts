import type { CellMerge, TableCellContent } from '@/types/designer'

/**
 * Check whether a cell at (row, col) is covered (consumed) by an existing merge,
 * meaning it should NOT be rendered as a standalone cell.
 *
 * A cell is "covered" when another merge origin at (m.row, m.col) spans
 * enough rows/columns to include it, and it is NOT the origin cell itself.
 */
export function isCellCovered(
    row: number,
    col: number,
    merges: CellMerge[],
): boolean {
    return merges.some(m => {
        const inRowRange = row >= m.row && row < m.row + m.rowspan
        const inColRange = col >= m.col && col < m.col + m.colspan
        const isOrigin = row === m.row && col === m.col
        return inRowRange && inColRange && !isOrigin
    })
}

/**
 * Find the merge origin that covers cell (row, col), if any.
 * Returns null when the cell is not part of any merge,
 * or when it IS the origin cell (we handle origins differently in render).
 */
export function getMergeAt(
    row: number,
    col: number,
    merges: CellMerge[],
): CellMerge | null {
    for (const m of merges) {
        const inRowRange = row >= m.row && row < m.row + m.rowspan
        const inColRange = col >= m.col && col < m.col + m.colspan
        const isOrigin = row === m.row && col === m.col
        if (inRowRange && inColRange && isOrigin) {
            return m
        }
    }
    return null
}

/**
 * Find ALL merges that include cell (row, col) — used for unmerge detection.
 * Returns merges where this cell is anywhere within the range (origin or covered).
 */
export function getMergesContaining(
    row: number,
    col: number,
    merges: CellMerge[],
): CellMerge[] {
    return merges.filter(m => {
        const inRowRange = row >= m.row && row < m.row + m.rowspan
        const inColRange = col >= m.col && col < m.col + m.colspan
        return inRowRange && inColRange
    })
}

/**
 * Check whether a new merge candidate would overlap any existing merge.
 * Used to prevent invalid merge creation.
 */
export function wouldOverlap(
    candidate: CellMerge,
    existing: CellMerge[],
): boolean {
    const cEndRow = candidate.row + candidate.rowspan
    const cEndCol = candidate.col + candidate.colspan

    return existing.some(m => {
        const mEndRow = m.row + m.rowspan
        const mEndCol = m.col + m.colspan

        const overlapRows = candidate.row < mEndRow && cEndRow > m.row
        const overlapCols = candidate.col < mEndCol && cEndCol > m.col

        return overlapRows && overlapCols
    })
}

/**
 * Clamp a merge candidate so it doesn't exceed the table boundaries.
 */
export function clampMerge(
    candidate: CellMerge,
    totalRows: number,
    totalCols: number,
): CellMerge {
    return {
        row: Math.max(0, candidate.row),
        col: Math.max(0, candidate.col),
        rowspan: Math.min(candidate.rowspan, totalRows - candidate.row),
        colspan: Math.min(candidate.colspan, totalCols - candidate.col),
    }
}

/**
 * Build a merge from a rectangular cell selection.
 * The top-left cell's text and style survive.
 *
 * @returns The new CellMerge and the updated rows with non-top-left cells cleared.
 */
export function createMergeFromSelection(
    selection: { startRow: number; startCol: number; endRow: number; endCol: number },
    existingMerges: CellMerge[],
    rows: Record<string, TableCellContent>[],
    columns: { key: string }[],
): { merge: CellMerge; mergedRows: Record<string, TableCellContent>[] } | { error: string } {
    const minRow = Math.min(selection.startRow, selection.endRow)
    const maxRow = Math.max(selection.startRow, selection.endRow)
    const minCol = Math.min(selection.startCol, selection.endCol)
    const maxCol = Math.max(selection.startCol, selection.endCol)

    // Validate: must be at least 2 cells
    if (minRow === maxRow && minCol === maxCol) {
        return { error: 'Select at least two cells to merge.' }
    }

    const totalRows = rows.length
    const totalCols = columns.length

    const candidate: CellMerge = clampMerge(
        { row: minRow, col: minCol, rowspan: maxRow - minRow + 1, colspan: maxCol - minCol + 1 },
        totalRows,
        totalCols,
    )

    // Validate: no overlap with existing merges
    if (wouldOverlap(candidate, existingMerges)) {
        return { error: 'Selection overlaps an existing merged cell.' }
    }

    // Clone and clear non-top-left cells
    const mergedRows = rows.map((row, rowIdx) => {
        const newRow = { ...row }
        for (let c = minCol; c <= maxCol; c++) {
            const colKey = columns[c]?.key
            if (!colKey) continue
            if (rowIdx === minRow && c === minCol) continue // keep top-left
            if (rowIdx >= minRow && rowIdx <= maxRow) {
                newRow[colKey] = { ...newRow[colKey], text: '' }
            }
        }
        return newRow
    })

    return { merge: candidate, mergedRows }
}

/**
 * Remove a specific merge and restore its cells.
 * Top-left keeps its value; all other cells become empty.
 */
export function removeMerge(
    merge: CellMerge,
    existingMerges: CellMerge[],
    rows: Record<string, TableCellContent>[],
    columns: { key: string }[],
): { merges: CellMerge[]; rows: Record<string, TableCellContent>[] } {
    const filteredMerges = existingMerges.filter(
        m => !(m.row === merge.row && m.col === merge.col && m.rowspan === merge.rowspan && m.colspan === merge.colspan),
    )

    // Restore cells: top-left keeps text, rest get empty text
    const updatedRows = rows.map((row, rowIdx) => {
        const newRow = { ...row }
        for (let c = merge.col; c < merge.col + merge.colspan; c++) {
            const colKey = columns[c]?.key
            if (!colKey) continue

            if (rowIdx === merge.row && c === merge.col) {
                // Keep top-left intact
                continue
            }

            if (rowIdx >= merge.row && rowIdx < merge.row + merge.rowspan) {
                const existing = newRow[colKey]
                newRow[colKey] = existing
                    ? { ...existing, text: '' }
                    : { text: '' }
            }
        }
        return newRow
    })

    return { merges: filteredMerges, rows: updatedRows }
}
