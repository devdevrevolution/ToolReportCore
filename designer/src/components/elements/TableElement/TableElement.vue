<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { DesignerElement, TableCellContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'
import { useTableElement } from './composables/useTableElement'
import { isCellCovered as isCoveredByMerge, getMergeAt, getMergesContaining, createMergeFromSelection, removeMerge } from './helpers/merge'
import TableContextMenu from './modals/TableContextMenu.vue'
import TableCellStylePopover from './modals/TableCellStylePopover.vue'
import TableColumnEditor from './modals/TableColumnEditor.vue'
import TableDataEditor from './modals/TableDataEditor.vue'

const props = defineProps<{ element: DesignerElement; scale?: number }>()
const store = useDesignerStore()

const scaleRef = computed(() => props.scale ?? 3.78)

// ── Edit mode ──────────────────────────────────

const isEditing = computed(() => store.editingElementId === props.element.id)

function enterEditMode(): void {
  store.selectElement(props.element.id)
  store.enterEditMode(props.element.id)
}

function exitEditMode(): void {
  store.exitEditMode()
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && isEditing.value) {
    e.stopPropagation()
    exitEditMode()
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  if (store.editingElementId === props.element.id) store.exitEditMode()
})

const {
  columns,
  rows,
  merges,
  hasColumns,
  showHeader,
  hasVariable,
  variable,
  tablePreviewStyle,
  getEffectiveStyle,
  getCellText,
  updateContent,
} = useTableElement(props.element, scaleRef)

// ── Selection ────────────────────────────────

interface CellCoord { row: number; col: number }

const anchorCell = ref<CellCoord | null>(null)
const selectionStart = ref<CellCoord | null>(null)
const selectionEnd = ref<CellCoord | null>(null)
const lastClickedCell = ref<CellCoord | null>(null)

const hasSelection = computed(() => selectionStart.value !== null && selectionEnd.value !== null)

const selectionRect = computed(() => {
  if (!selectionStart.value || !selectionEnd.value) return null
  return {
    startRow: Math.min(selectionStart.value.row, selectionEnd.value.row),
    endRow: Math.max(selectionStart.value.row, selectionEnd.value.row),
    startCol: Math.min(selectionStart.value.col, selectionEnd.value.col),
    endCol: Math.max(selectionStart.value.col, selectionEnd.value.col),
  }
})

const selectedCellCount = computed(() => {
  const r = selectionRect.value
  if (!r) return 0
  return (r.endRow - r.startRow + 1) * (r.endCol - r.startCol + 1)
})

function isSelected(rowIdx: number, colIdx: number): boolean {
  const r = selectionRect.value
  if (!r) return false
  return rowIdx >= r.startRow && rowIdx <= r.endRow && colIdx >= r.startCol && colIdx <= r.endCol
}

function selectCell(rowIdx: number, colIdx: number, event: MouseEvent): void {
  if (event.shiftKey && anchorCell.value) {
    selectionStart.value = { row: anchorCell.value.row, col: anchorCell.value.col }
    selectionEnd.value = { row: rowIdx, col: colIdx }
  } else {
    anchorCell.value = { row: rowIdx, col: colIdx }
    selectionStart.value = { row: rowIdx, col: colIdx }
    selectionEnd.value = { row: rowIdx, col: colIdx }
  }
  lastClickedCell.value = { row: rowIdx, col: colIdx }
}

function clearSelection(): void {
  selectionStart.value = null
  selectionEnd.value = null
  anchorCell.value = null
  lastClickedCell.value = null
}

// ── Visible rows ────────────────────────────

/**
 * Static rows if any, otherwise 2 fallback preview rows.
 */
const visibleRows = computed(() => {
  if (rows.value.length > 0) return rows.value
  return [
    {} as Record<string, any>,
    {} as Record<string, any>,
  ]
})

const hasStaticRows = computed(() => rows.value.length > 0)

/**
 * Build the list of visible cells for each row, skipping merge-covered cells.
 */
const mergedVisibleRows = computed(() => {
  const cols = renderColumns.value
  const rowData = rows.value
  const mergeDefs = merges.value

  return rowData.map((row, rowIdx) => {
    return cols.map((col, colIdx) => {
      const merge = getMergeAt(rowIdx, colIdx, mergeDefs)
      const covered = isCoveredByMerge(rowIdx, colIdx, mergeDefs)
      const cell = row[col.key] ?? { text: '' }
      return {
        col,
        colIdx,
        cell,
        isOrigin: !!merge,
        isCovered: covered,
        colspan: merge?.colspan ?? 1,
        rowspan: merge?.rowspan ?? 1,
      }
    }).filter(c => !c.isCovered)
  })
})

/**
 * With table-layout:fixed, each column gets its explicit width.
 * The column NEXT to the resized one absorbs the delta so the right edge
 * stays at the container edge.
 *
 * Uses percentage so the designer matches the backend's
 * computeColWidths() formula: col.width / element.width * 100%.
 */
const elWidth = computed(() => props.element.width)

const cellWidthStyle = (col: { width?: number }): Record<string, string> => {
  const totalW = elWidth.value
  if (col.width != null && col.width > 0 && totalW > 0) {
    const pct = (col.width / totalW * 100).toFixed(2)
    return { width: `${pct}%` }
  }
  return {}
}

/**
 * Returns the percentage string for a column (e.g. "25.00%") matching the
 * backend's computeColWidths(). Used on <col> elements.
 */
const colWidthPct = (col: { width?: number }): string => {
  const totalW = elWidth.value
  if (col.width != null && col.width > 0 && totalW > 0) {
    return (col.width / totalW * 100).toFixed(2) + '%'
  }
  return ''
}

// ── Column resize from canvas ──────────────

const tableEl = ref<HTMLTableElement | null>(null)

interface ColumnResizeState {
  colIdx: number
  startX: number
  startWidth: number // px
}

const columnResizeState = ref<ColumnResizeState | null>(null)

/**
 * Local column snapshot used exclusively during drag.
 * Avoids store writes on every mousemove for smooth feedback.
 */
const dragColumns = ref<typeof columns.value | null>(null)

/** Columns used for rendering — use drag preview when active, real data otherwise */
const renderColumns = computed(() => dragColumns.value ?? columns.value)

function startColumnResize(colIdx: number, event: MouseEvent): void {
  event.preventDefault()
  event.stopPropagation()
  const col = columns.value[colIdx]
  if (!col) return
  const currentPx = col.width ? col.width * scaleRef.value : 30 * scaleRef.value

  // Lock EVERY column to its current rendered width (including the last).
  // The column NEXT to the resized one will absorb width changes, while
  // all others stay fixed. This keeps the right edge at the container edge.
  const cols = columns.value
  const ths = tableEl.value?.querySelectorAll<HTMLElement>('thead th')
  const locked = cols.map((c, i) => {
    if (c.width != null) return { ...c }
    const renderedPx = ths?.[i]?.getBoundingClientRect().width ?? 30
    return { ...c, width: Math.round(renderedPx / scaleRef.value) }
  })

  columnResizeState.value = { colIdx, startX: event.clientX, startWidth: currentPx }
  dragColumns.value = locked
  document.addEventListener('mousemove', onColumnResizeMove)
  document.addEventListener('mouseup', onColumnResizeUp)
  document.body.style.userSelect = 'none'
  document.body.style.cursor = 'col-resize'
}

function onColumnResizeMove(event: MouseEvent): void {
  const rs = columnResizeState.value
  const dc = dragColumns.value
  if (!rs || !dc) return
  const dx = event.clientX - rs.startX
  const newPx = Math.max(8, rs.startWidth + dx)
  const newMm = Math.round(newPx / scaleRef.value)
  const deltaMm = newMm - (dc[rs.colIdx]?.width ?? 0)

  // Update the resized column
  dc[rs.colIdx] = { ...dc[rs.colIdx], width: newMm }

  // The NEXT column absorbs the delta (opposite direction)
  // This keeps sum = table width, so right edge stays at container edge
  const nextIdx = rs.colIdx + 1
  if (nextIdx < dc.length) {
    const nextCol = dc[nextIdx]
    const nextMm = Math.max(9, (nextCol.width ?? 30) - deltaMm)
    dc[nextIdx] = { ...nextCol, width: Math.round(nextMm) }
  }

  dragColumns.value = [...dc]
}

function onColumnResizeUp(): void {
  const dc = dragColumns.value
  if (dc) {
    // Flush final state to store
    updateContent('columns', dc)
  }
  columnResizeState.value = null
  dragColumns.value = null
  document.removeEventListener('mousemove', onColumnResizeMove)
  document.removeEventListener('mouseup', onColumnResizeUp)
  document.body.style.userSelect = ''
  document.body.style.cursor = ''
}

// ── Context menu (shared) ───────────────────

type ContextMode = 'cell' | 'column' | 'table'

const contextMenu = ref<{ x: number; y: number; mode: ContextMode } | null>(null)
const showStylePopover = ref(false)
const showHeaderStylePopover = ref(false)
const contextColumnIdx = ref<number>(0) // which column was right-clicked

const contextCell = computed(() => lastClickedCell.value ?? { row: 0, col: 0 })

// ── Body context menu (cell mode) ──────────

const canMerge = computed(() => selectedCellCount.value >= 2)

const canUnmerge = computed(() => {
  const r = selectionRect.value
  if (!r) return false
  for (let row = r.startRow; row <= r.endRow; row++) {
    for (let col = r.startCol; col <= r.endCol; col++) {
      if (getMergesContaining(row, col, merges.value).length > 0) return true
    }
  }
  return false
})

const canDeleteRow = computed(() => rows.value.length > 0 && contextCell.value.row < rows.value.length)

// ── Header context menu (column mode) ──────

const showColumnEditorLocal = ref(false)
const showDataEditor = ref(false)

const canDeleteColumn = computed(() => columns.value.length > 1)
const contextColumn = computed(() => columns.value[contextColumnIdx.value])

// ── Handlers ───────────────────────────────

function onCellRightClick(event: MouseEvent): void {
  event.preventDefault()
  contextMenu.value = { x: event.clientX, y: event.clientY, mode: 'cell' }
}

function onHeaderRightClick(event: MouseEvent, colIdx: number): void {
  event.preventDefault()
  contextColumnIdx.value = colIdx
  contextMenu.value = { x: event.clientX, y: event.clientY, mode: 'column' }
}

function onContextAction(action: string): void {
  const mode = contextMenu.value?.mode ?? 'cell'
  contextMenu.value = null

  switch (action) {
    case 'style': showStylePopover.value = true; break
    case 'styleHeader': showHeaderStylePopover.value = true; break
    case 'merge': doMerge(); break
    case 'unmerge': doUnmerge(); break
    case 'deleteRow': doDeleteRow(); break
    case 'clear': doClear(); break
    case 'insertRowAbove': doInsertRow(contextCell.value.row); break
    case 'insertRowBelow': doInsertRow(contextCell.value.row + 1); break
    case 'configureColumns': showColumnEditorLocal.value = true; break
    case 'editColumn': showColumnEditorLocal.value = true; break
    case 'insertColumnLeft': doInsertColumn(contextColumnIdx.value); break
    case 'insertColumnRight': doInsertColumn(contextColumnIdx.value + 1); break
    case 'deleteColumn': doDeleteColumn(contextColumnIdx.value); break
  }
}

// ── Column actions ─────────────────────────

function doInsertColumn(atIdx: number): void {
  const newId = 'col-' + crypto.randomUUID().slice(0, 8)
  const newCol = { id: newId, key: 'field' + (atIdx + 1), header: 'Field ' + (atIdx + 1), align: 'left' as const }
  const newColumns = [...columns.value]
  newColumns.splice(atIdx, 0, newCol)
  updateContent('columns', newColumns)
}

function doDeleteColumn(idx: number): void {
  if (columns.value.length <= 1) return
  const colKey = columns.value[idx].key
  const newColumns = columns.value.filter((_, i) => i !== idx)
  const newRows = rows.value.map(row => {
    const { [colKey]: _, ...rest } = row
    return rest
  })
  updateContent('columns', newColumns)
  updateContent('rows', newRows)
}

// ── Style popover ──────────────────────────

function getCurrentCellForStyle(): any {
  const { row, col } = contextCell.value
  const k = columns.value[col]?.key
  return k ? (rows.value[row]?.[k] ?? { text: '' }) : { text: '' }
}

function onDataEditorSave(cols: any[], newRows: any[], newMerges: any[], showHeader: boolean): void {
  updateContent('columns', cols)
  updateContent('rows', newRows)
  updateContent('merges', newMerges)
  updateContent('showHeader', showHeader)
  showDataEditor.value = false
}

function onStyleSave(cell: any): void {
  const { row, col } = contextCell.value
  const k = columns.value[col]?.key
  if (!k) return
  const newRows = rows.value.map((r, i) => (i !== row ? r : { ...r, [k]: { ...cell } }))
  updateContent('rows', newRows)
  showStylePopover.value = false
}

function getCurrentHeaderForStyle(): any {
  const col = columns.value[contextColumnIdx.value]
  if (!col) return { text: col?.header ?? '' }
  return { text: col.header, ...(col.headerStyle ?? {}) }
}

function onHeaderStyleSave(style: any): void {
  const idx = contextColumnIdx.value
  const col = columns.value[idx]
  if (!col) return
  const { text: _, ...headerStyle } = style
  const newCol = { ...col, headerStyle: headerStyle }
  const newColumns = columns.value.map((c, i) => (i !== idx ? c : newCol))
  updateContent('columns', newColumns)
  showHeaderStylePopover.value = false
}

// ── Helper for fallback text ───────────────

function fallbackText(colKey: string): string {
  return colKey || '\u2014'
}

function headerStyle(col: { headerStyle?: Record<string, unknown> }): Record<string, string> {
  if (!col.headerStyle || Object.keys(col.headerStyle).length === 0) return {}
  return getEffectiveStyle(col.headerStyle as any, props.element.styles)
}

// ── Merge/unmerge actions ───────────────────

function doMerge(): void {
  const r = selectionRect.value
  if (!r) return
  const result = createMergeFromSelection(
    { startRow: r.startRow, startCol: r.startCol, endRow: r.endRow, endCol: r.endCol },
    merges.value,
    rows.value,
    columns.value.map(c => ({ key: c.key })),
  )
  if ('error' in result) {
    console.warn(result.error)
    return
  }
  updateContent('rows', result.mergedRows)
  updateContent('merges', [...merges.value, result.merge])
  clearSelection()
}

function doUnmerge(): void {
  const r = selectionRect.value
  if (!r) return
  let target: any = null
  for (let row = r.startRow; row <= r.endRow && !target; row++) {
    for (let col = r.startCol; col <= r.endCol && !target; col++) {
      const ms = getMergesContaining(row, col, merges.value)
      if (ms.length > 0) target = ms[0]
    }
  }
  if (!target) return
  const result = removeMerge(target, merges.value, rows.value, columns.value.map(c => ({ key: c.key })))
  updateContent('merges', result.merges)
  updateContent('rows', result.rows)
  clearSelection()
}

function doDeleteRow(): void {
  const idx = contextCell.value.row
  const newRows = rows.value.filter((_, i) => i !== idx)
  updateContent('rows', newRows)
  clearSelection()
}

function doInsertRow(atIdx: number): void {
  const emptyRow: Record<string, TableCellContent> = {}
  for (const col of columns.value) {
    emptyRow[col.key] = { text: '' }
  }
  const newRows = [...rows.value]
  newRows.splice(atIdx, 0, emptyRow)
  updateContent('rows', newRows)
}

function doClear(): void {
  const r = selectionRect.value
  if (!r) return
  const newRows = rows.value.map((row, i) => {
    if (i < r.startRow || i > r.endRow) return row
    const nr = { ...row }
    for (let c = r.startCol; c <= r.endCol; c++) {
      const k = columns.value[c]?.key
      if (k && nr[k]) nr[k] = { ...nr[k], text: '' }
    }
    return nr
  })
  updateContent('rows', newRows)
  clearSelection()
}

// ── Debug overlay ──────────────────────────────

const pxPerMm = 96 / 25.4

function pxToMm(px: number): string {
  return (px / pxPerMm).toFixed(2)
}

interface DimCell {
  idx: number
  label: string
  w: number
  h: number
  mmW: string
  mmH: string
}

interface DimCol {
  idx: number
  label: string
  w: number
  mmW: string
  modelW: string
}

interface DimRow {
  idx: number
  h: number
  mmH: string
}

interface DimData {
  tableW: number
  tableH: number
  mmW: string
  mmH: string
  columns: DimCol[]
  rows: DimRow[]
  cells: DimCell[]
}

const showDimOverlay = ref(false)
const dimData = ref<DimData | null>(null)

function measureDimensions(): DimData | null {
  const table = tableEl.value
  if (!table) return null

  const tableRect = table.getBoundingClientRect()

  // Columns
  const ths = table.querySelectorAll<HTMLElement>('thead th')
  const firstTds = table.querySelectorAll<HTMLElement>('tbody tr:first-child td')
  const colEls = ths.length > 0 ? ths : firstTds

  const columnsD: DimCol[] = colEls.length > 0
    ? Array.from(colEls).map((el, i) => {
        const r = el.getBoundingClientRect()
        const col = columns.value[i]
        return {
          idx: i,
          label: col?.header ?? col?.key ?? `#${i}`,
          w: r.width,
          mmW: pxToMm(r.width),
          modelW: col?.width != null ? `${col.width}mm` : 'auto',
        }
      })
    : []

  // Rows
  const trs = table.querySelectorAll('tbody tr')
  const rowsD: DimRow[] = Array.from(trs).map((tr, i) => {
    const r = tr.getBoundingClientRect()
    return { idx: i, h: r.height, mmH: pxToMm(r.height) }
  })

  // Cells
  const allCells = table.querySelectorAll('td, th')
  const cellsD: DimCell[] = Array.from(allCells).map((el, i) => {
    const r = el.getBoundingClientRect()
    const thEl = el as HTMLTableCellElement
    const colIdx = thEl.cellIndex ?? -1
    const col = columns.value[colIdx]
    const label = col?.header ?? col?.key ?? `#${colIdx}`
    return {
      idx: i,
      label: colIdx >= 0 ? `[c${colIdx}] ${label}` : `#${i}`,
      w: r.width,
      h: r.height,
      mmW: pxToMm(r.width),
      mmH: pxToMm(r.height),
    }
  })

  return {
    tableW: tableRect.width,
    tableH: tableRect.height,
    mmW: pxToMm(tableRect.width),
    mmH: pxToMm(tableRect.height),
    columns: columnsD,
    rows: rowsD,
    cells: cellsD,
  }
}

let dimObserver: ResizeObserver | null = null

function refreshDimensions(): void {
  dimData.value = measureDimensions()
}

function toggleDimOverlay(): void {
  showDimOverlay.value = !showDimOverlay.value
  if (showDimOverlay.value) {
    refreshDimensions()
    // Auto-refresh on table resize
    dimObserver?.disconnect()
    const table = tableEl.value
    if (table && table.parentElement) {
      dimObserver = new ResizeObserver(refreshDimensions)
      dimObserver.observe(table.parentElement)
    }
  } else {
    dimObserver?.disconnect()
    dimObserver = null
  }
}

onUnmounted(() => {
  dimObserver?.disconnect()
})
</script>

<template>
  <div
    class="relative h-full w-full overflow-hidden text-[9px] leading-tight transition-shadow"
    :class="{ 'ring-2 ring-blue-500': isEditing }"
    @click.self="isEditing ? exitEditMode() : clearSelection()"
    @dblclick="enterEditMode"
  >
    <!-- Edit mode toolbar -->
    <div
      v-if="isEditing"
      class="absolute right-0 top-0 z-30 flex items-center gap-1 bg-blue-500/90 px-1.5 py-0.5 rounded-bl text-white text-[8px]"
    >
      <button
        class="hover:bg-blue-600 px-1 rounded"
        @click.stop="showDataEditor = true"
      >
        Edit Data
      </button>
      <button
        class="hover:bg-blue-600 px-1 rounded"
        :class="{ 'bg-blue-700': showDimOverlay }"
        title="Toggle dimension overlay"
        @click.stop="toggleDimOverlay()"
      >
        📐
      </button>
      <button
        class="hover:bg-blue-600 px-1 rounded font-bold"
        @click.stop="exitEditMode"
      >
        ✕
      </button>
    </div>
    <!-- table-layout:fixed + explicit widths on ALL columns keeps the table
         at container width. The column NEXT to the resized one absorbs changes,
         so the right edge stays at the container edge. -->
    <!-- Column widths: use <colgroup> to keep them fixed even with merges.
         This matches the backend (DomPDF) which also uses <colgroup>
         percentage widths from col.width / element.width. -->
    <table
      ref="tableEl"
      class="w-full border-collapse"
      :style="{ ...tablePreviewStyle, tableLayout: 'fixed' }"
    >
      <!-- colgroup — sets definitive column widths for table-layout:fixed
           regardless of colspan/rowspan in thead/tbody -->
      <colgroup v-if="hasColumns">
        <col
          v-for="(col, colIdx) in renderColumns"
          :key="col.id"
          :style="{ width: colWidthPct(col) }"
        >
      </colgroup>
      <thead v-if="hasColumns && showHeader">
        <tr>
          <th
            v-for="(col, colIdx) in renderColumns"
            :key="col.id"
            class="group relative border border-gray-300 bg-gray-100 px-1 py-0.5 font-medium text-gray-700"
            :class="{ 'cursor-context-menu': true }"
            :style="{ ...cellWidthStyle(col), ...headerStyle(col) }"
            @contextmenu.prevent="onHeaderRightClick($event, colIdx)"
          >
            <span class="truncate">{{ col.header || col.key || '\u00A0' }}</span>

            <!-- Column resize handle — faint by default, bright on hover or when editing -->
            <div
              v-if="colIdx < renderColumns.length - 1"
              class="absolute inset-y-0 right-0 z-20 w-5 cursor-col-resize transition-all duration-150"
              :class="isEditing
                ? 'opacity-100 group-hover:w-6'
                : 'opacity-40 group-hover:opacity-100 group-hover:w-6'"
              @mousedown.stop="startColumnResize(colIdx, $event)"
            >
              <div class="mx-auto h-full w-1 rounded bg-blue-500" />
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <!-- Static rows with merge-aware rendering -->
        <template v-if="hasStaticRows && mergedVisibleRows.length > 0">
          <tr v-for="(cells, rowIdx) in mergedVisibleRows" :key="'sr-' + rowIdx">
            <td
              v-for="cell in cells"
              :key="'sc-' + cell.col.id + '-' + rowIdx"
              class="border border-gray-200 px-1 py-0.5 select-none"
              :class="{
                'bg-blue-100/50 ring-2 ring-blue-500 cursor-pointer': isSelected(rowIdx, cell.colIdx),
                'bg-blue-50/30': cell.isOrigin,
              }"
              :style="{
                ...cellWidthStyle(cell.col),
                ...getEffectiveStyle(cell.cell, element.styles),
              }"
              :colspan="cell.isOrigin ? cell.colspan : undefined"
              :rowspan="cell.isOrigin ? cell.rowspan : undefined"
              @click="selectCell(rowIdx, cell.colIdx, $event)"
              @contextmenu.prevent="onCellRightClick"
            >
              {{ cell.cell.text || fallbackText(cell.col.key) }}
            </td>
          </tr>
        </template>

        <!-- Fallback preview rows (no static data) -->
        <template v-else-if="hasColumns">
          <tr v-for="(row, rowIdx) in visibleRows" :key="'pr-' + rowIdx">
            <td
              v-for="(col, colIdx) in renderColumns"
              :key="'pc-' + col.id + '-' + rowIdx"
              class="border border-gray-200 px-1 py-0.5"
              :style="cellWidthStyle(col)"
              @contextmenu.prevent="onCellRightClick"
            >
              <span
                v-if="hasStaticRows && row[col.key]?.text"
                class="text-gray-800"
              >
                {{ row[col.key].text }}
              </span>
              <span v-else-if="col.key" class="text-gray-400">
                {{ col.key }}
              </span>
              <span v-else class="italic">&mdash;</span>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <!-- Dimension overlay -->
    <div
      v-if="showDimOverlay && dimData"
      class="pointer-events-none absolute inset-0 z-30"
    >
      <!-- Floating summary badge -->
      <div
        class="pointer-events-auto absolute bottom-1 left-1 z-40 max-h-[80%] max-w-[90%] overflow-auto rounded border border-blue-300 bg-white/95 px-2 py-1.5 shadow-lg backdrop-blur text-[8px] leading-tight text-gray-700"
      >
        <div class="mb-1 flex items-center gap-2 border-b border-blue-100 pb-1 font-semibold text-blue-700">
          <span>📐</span>
          <span>Table {{ dimData.mmW }}×{{ dimData.mmH }}mm</span>
          <span class="font-normal text-gray-400">({{ dimData.tableW.toFixed(0) }}×{{ dimData.tableH.toFixed(0) }}px)</span>
        </div>

        <!-- Columns -->
        <div v-if="dimData.columns.length" class="mb-1">
          <div class="text-[7px] font-medium uppercase tracking-wider text-gray-400">Columns</div>
          <div class="flex flex-wrap gap-x-2">
            <div
              v-for="col in dimData.columns"
              :key="col.idx"
              class="flex items-baseline gap-0.5"
            >
              <span class="max-w-[60px] truncate text-gray-500">{{ col.label }}</span>
              <span class="font-medium text-gray-800">{{ col.mmW }}mm</span>
              <span class="text-gray-400">({{ col.w.toFixed(0) }}px)</span>
              <span v-if="col.modelW !== 'auto'" class="text-[7px] text-blue-400">model:{{ col.modelW }}</span>
            </div>
          </div>
        </div>

        <!-- Rows -->
        <div v-if="dimData.rows.length" class="mb-1">
          <div class="text-[7px] font-medium uppercase tracking-wider text-gray-400">Rows</div>
          <div class="flex flex-wrap gap-x-2">
            <div
              v-for="row in dimData.rows"
              :key="row.idx"
              class="flex items-baseline gap-0.5"
            >
              <span class="text-gray-500">#{{ row.idx }}</span>
              <span class="font-medium text-gray-800">{{ row.mmH }}mm</span>
              <span class="text-gray-400">({{ row.h.toFixed(0) }}px)</span>
            </div>
          </div>
        </div>

        <!-- Cells -->
        <div v-if="dimData.cells.length">
          <div class="text-[7px] font-medium uppercase tracking-wider text-gray-400">Cells</div>
          <div class="max-h-[120px] overflow-y-auto">
            <div
              v-for="cell in dimData.cells"
              :key="cell.idx"
              class="flex items-baseline gap-0.5"
            >
              <span class="w-12 truncate text-gray-500">{{ cell.label }}</span>
              <span class="font-medium text-gray-800">{{ cell.mmW }}×{{ cell.mmH }}mm</span>
              <span class="text-gray-400">({{ cell.w.toFixed(0) }}×{{ cell.h.toFixed(0) }}px)</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="!hasColumns"
      class="flex h-full w-full items-center justify-center text-gray-400"
    >
      <span class="text-base">⊞</span>
      <span class="ml-1 text-[10px]">Configure columns</span>
    </div>

    <!-- Data binding indicator -->
    <div
      v-if="hasVariable"
      class="absolute bottom-0.5 right-1 rounded bg-blue-100 px-1 text-[8px] text-blue-600"
    >
      ↳ {{ variable }}
    </div>

    <!-- Context menu (mode-aware) -->
    <TableContextMenu
      v-if="contextMenu"
      :x="contextMenu.x"
      :y="contextMenu.y"
      :mode="contextMenu.mode"
      :can-style="hasSelection"
      :can-merge="canMerge"
      :can-unmerge="canUnmerge"
      :can-delete-row="canDeleteRow"
      :can-clear="hasSelection"
      :can-delete-column="canDeleteColumn"
      :column-name="contextColumn?.header ?? contextColumn?.key ?? ''"
      @action="onContextAction"
      @close="contextMenu = null"
    />

    <!-- Cell style popover -->
    <TableCellStylePopover
      v-if="showStylePopover"
      :cell="getCurrentCellForStyle()"
      :element-styles="element.styles"
      title="Cell Style"
      @save="onStyleSave"
      @close="showStylePopover = false"
    />

    <!-- Header style popover -->
    <TableCellStylePopover
      v-if="showHeaderStylePopover"
      :cell="getCurrentHeaderForStyle()"
      :element-styles="element.styles"
      title="Header Style"
      @save="onHeaderStyleSave"
      @close="showHeaderStylePopover = false"
    />

    <!-- Column editor (triggered from context menu) -->
    <TableColumnEditor
      v-if="showColumnEditorLocal"
      :columns="(element.content as any).columns ?? []"
      :variable="(element.content as any).variable ?? ''"
      @save="(cols: any) => { updateContent('columns', cols); showColumnEditorLocal = false }"
      @close="showColumnEditorLocal = false"
    />

    <!-- Data editor (double-click) -->
    <TableDataEditor
      v-if="showDataEditor"
      :columns="(element.content as any).columns ?? []"
      :rows="(element.content as any).rows ?? []"
      :merges="(element.content as any).merges ?? []"
      :show-header="(element.content as any).showHeader !== false"
      @save="onDataEditorSave"
      @close="showDataEditor = false"
    />
  </div>
</template>
