import { computed, type Ref } from 'vue'
import type { DesignerElement, TableColumn, TableContent, TableCellContent, CellMerge, DesignerStyles } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'
import { isCellCovered, getMergeAt, getMergesContaining } from '../helpers/merge'

/** 1pt = 25.4/72 mm, then * scale (px/mm) = px on screen */
function ptToPx(pt: number, scale: number): number {
  return (pt * 25.4 * scale) / 72
}

export function useTableElement(element: DesignerElement, scale?: Ref<number | undefined>) {
  const store = useDesignerStore()

  /** Resolved scale value (px/mm), or 1 if no scale is provided (e.g. property panel) */
  const s = computed(() => scale?.value ?? 1)

  const content = computed(() => element.content as TableContent)
  const columns = computed(() => content.value.columns)
  const variable = computed<string | undefined>(() => content.value.variable)
  const hasColumns = computed(() => columns.value.length > 0)
  const hasVariable = computed(() => !!content.value.variable)
  const showHeader = computed(() => content.value.showHeader !== false)

  /** Static row data, keyed by column key */
  const rows = computed<Record<string, TableCellContent>[]>(() => content.value.rows ?? [])

  /** Cell merge definitions */
  const merges = computed<CellMerge[]>(() => content.value.merges ?? [])

  /** Number of static rows */
  const rowCount = computed(() => rows.value.length)

  /** Number of merge definitions */
  const mergeCount = computed(() => merges.value.length)

  /** Whether there are any static rows */
  const hasRows = computed(() => rows.value.length > 0)

  /** Whether to show static rows or the default preview rows */
  const hasStaticData = computed(() => hasRows.value || hasVariable.value)

  const tablePreviewStyle = computed(() => {
    const styles = element.styles ?? {}
    const fontSizePt = Math.max(6, (styles.fontSize as number) ?? 10)
    return {
      fontSize: `${ptToPx(fontSizePt, s.value)}px`,
      fontFamily: (styles.fontFamily as string) ?? 'inherit',
    }
  })

  const expressionPreview = (v: string): string => {
    return `{{ ${v} }}`
  }

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  const onColumnEditorSave = (columns: TableColumn[]) => {
    updateContent('columns', columns)
  }

  const onRowEditorSave = (rows: Record<string, TableCellContent>[]) => {
    updateContent('rows', rows)
  }

  const onMergeUpdate = (merges: CellMerge[]) => {
    updateContent('merges', merges)
  }

  // ── Cell style resolution ────────────────────

  /**
   * Resolve the effective style for a cell, merging element-level defaults
   * with cell-level overrides.
   */
  const getEffectiveStyle = (
    cell: TableCellContent | undefined,
    elementStyles: DesignerStyles | null | undefined,
  ): Record<string, string> => {
    const scaleValue = s.value
    const style: Record<string, string> = {}
    const c = cell ?? { text: '' }
    const es = elementStyles ?? ({} as DesignerStyles)

    style.fontFamily = c.fontFamily ?? es.fontFamily ?? 'inherit'
    const fontSizePt = c.fontSize ?? (es.fontSize as number) ?? 10
    style.fontSize = `${ptToPx(fontSizePt, scaleValue)}px`
    style.fontWeight = c.fontWeight ?? (es.fontWeight as string) ?? 'normal'
    style.fontStyle = c.fontStyle ?? (es.fontStyle as string) ?? 'normal'
    style.color = c.color ?? (es.color as string) ?? '#000000'
    style.textAlign = c.textAlign ?? (es.textAlign as string) ?? 'left'
    style.backgroundColor = c.backgroundColor ?? (es.backgroundColor as string) ?? 'transparent'
    style.verticalAlign = c.verticalAlign ?? (es.verticalAlign as string) ?? 'top'

    if (c.padding) {
      style.padding = `${(c.padding.top ?? 0) * scaleValue}px ${(c.padding.right ?? 0) * scaleValue}px ${(c.padding.bottom ?? 0) * scaleValue}px ${(c.padding.left ?? 0) * scaleValue}px`
    }

    // Nowrap / ellipsis
    if (c.nowrap) {
      style.whiteSpace = 'nowrap'
      style.overflow = 'hidden'
      style.textOverflow = 'ellipsis'
    }

    return style
  }

  // ── Merge-aware helpers ─────────────────────

  /**
   * Render-time helper: returns rows with merge-applied information.
   * Each row is an array of cell descriptors:
   * { col, cell, isOrigin, isCovered, merge, colspan, rowspan }
   */
  const getMergedRows = computed(() => {
    const cols = columns.value
    const rowData = rows.value
    const mergeDefs = merges.value

    if (rowData.length === 0) return []

    return rowData.map((row, rowIdx) => {
      const cells = cols.map((col, colIdx) => {
        const merge = getMergeAt(rowIdx, colIdx, mergeDefs)
        const covered = isCellCovered(rowIdx, colIdx, mergeDefs)

        return {
          col,
          cell: row[col.key] ?? { text: '' },
          isOrigin: !!merge,
          isCovered: covered,
          merge,
          colspan: merge?.colspan ?? 1,
          rowspan: merge?.rowspan ?? 1,
        }
      })

      // Filter out covered cells — they are consumed by a merge origin
      return cells.filter(c => !c.isCovered)
    })
  })

  /**
   * Get a cell's text for a given row and column key.
   */
  const getCellText = (row: Record<string, TableCellContent>, colKey: string): string => {
    return row[colKey]?.text ?? ''
  }

  /**
   * Check whether a specific cell is mergeable (can be an origin or covered).
   */
  const isInMerge = (rowIdx: number, colIdx: number): boolean => {
    return getMergesContaining(rowIdx, colIdx, merges.value).length > 0
  }

  return {
    content,
    columns,
    variable,
    hasColumns,
    showHeader,
    hasVariable,
    rows,
    merges,
    rowCount,
    mergeCount,
    hasRows,
    hasStaticData,
    tablePreviewStyle,
    expressionPreview,
    updateContent,
    onColumnEditorSave,
    onRowEditorSave,
    onMergeUpdate,
    getEffectiveStyle,
    getMergedRows,
    getCellText,
    isInMerge,
  }
}
