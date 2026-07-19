<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="emit('close')"
            @keydown.escape="emit('close')"
        >
            <div
                class="relative mx-4 flex w-full max-w-5xl flex-col rounded-lg bg-white shadow-2xl"
                style="max-height: 90vh;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Table Data Editor</h3>
                    <button
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Toolbar -->
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2">
                    <button
                        class="rounded border border-gray-300 px-2 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50"
                        @click="addColumn"
                    >
                        + Column
                    </button>
                    <button
                        class="rounded border border-gray-300 px-2 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50"
                        @click="addRow"
                    >
                        + Row
                    </button>

                    <span class="mx-1 text-[10px] text-gray-300">|</span>

                    <button
                        class="rounded border px-2 py-1 text-[10px] font-medium disabled:opacity-40"
                        :class="canMerge
                            ? 'border-blue-300 bg-blue-50 text-blue-600 hover:bg-blue-100'
                            : 'border-gray-200 text-gray-400'"
                        :disabled="!canMerge"
                        title="Merge selected cells"
                        @click="doMerge"
                    >
                        ⊞ Merge
                    </button>
                    <button
                        class="rounded border px-2 py-1 text-[10px] font-medium disabled:opacity-40"
                        :class="canUnmerge
                            ? 'border-blue-300 bg-blue-50 text-blue-600 hover:bg-blue-100'
                            : 'border-gray-200 text-gray-400'"
                        :disabled="!canUnmerge"
                        title="Unmerge selected cells"
                        @click="doUnmerge"
                    >
                        ⊟ Unmerge
                    </button>

                    <button
                        class="rounded border border-gray-300 px-2 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50"
                        :disabled="localColumns.length < 2"
                        title="Make all columns the same width"
                        @click="distributeColumns"
                    >
                        ⇔ Distribute
                    </button>

                    <span class="mx-1 text-[10px] text-gray-300">|</span>

                    <label class="flex cursor-pointer items-center gap-1.5 rounded border border-gray-200 px-2 py-1 text-[10px] text-gray-500 hover:bg-gray-50">
                        <input
                            type="checkbox"
                            class="h-3 w-3 rounded border-gray-300 text-blue-600"
                            :checked="localShowHeader"
                            @change="localShowHeader = ($event.target as HTMLInputElement).checked"
                        />
                        Headers
                    </label>

                    <span class="ml-auto text-[10px] text-gray-400">
                        {{ localColumns.length }} col{{ localColumns.length !== 1 ? 's' : '' }},
                        {{ localRows.length }} row{{ localRows.length !== 1 ? 's' : '' }}
                        <span v-if="localMerges.length"> · {{ localMerges.length }} merge{{ localMerges.length !== 1 ? 's' : '' }}</span>
                    </span>
                </div>

                <!-- Body: editable grid -->
                <div class="flex-1 overflow-auto p-4">
                    <!-- Empty state -->
                    <div
                        v-if="localColumns.length === 0"
                        class="rounded border-2 border-dashed border-gray-200 py-12 text-center text-sm text-gray-400"
                    >
                        No columns yet. Click <strong>+ Column</strong> to add one.
                    </div>

                    <!-- Grid table -->
                    <table v-else class="w-full border-collapse" style="table-layout: fixed">
                        <!-- Column headers -->
                        <thead>
                            <tr>
                                <th class="w-5 border border-gray-200 bg-gray-100 px-0 py-0.5 text-center text-[9px] font-medium text-gray-500">#</th>
                                <template v-if="localShowHeader">
                                <th
                                    v-for="(col, colIdx) in localColumns"
                                    :key="col.id"
                                    class="group relative border border-gray-200 bg-gray-50 px-1 py-1"
                                    :style="colWidthStyle(col)"
                                >
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="text"
                                            class="min-w-0 flex-1 rounded border border-transparent bg-transparent px-1 py-0.5 text-[10px] font-medium text-gray-700 outline-none hover:border-gray-200 focus:border-blue-400 focus:bg-white"
                                            :value="col.header || col.key"
                                            :placeholder="'col-' + (colIdx + 1)"
                                            @input="updateColumnHeader(colIdx, ($event.target as HTMLInputElement).value)"
                                            @click.stop
                                        />
                                        <button
                                            class="shrink-0 rounded px-1 py-0.5 text-[9px] text-gray-300 opacity-0 hover:text-red-500 group-hover:opacity-100"
                                            title="Delete column"
                                            @click.stop="deleteColumn(colIdx)"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, rowIdx) in localRows"
                                :key="rowIdx"
                                :class="rowIdx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'"
                            >
                                <!-- Row number (Excel-style) -->
                                <td
                                    class="border border-gray-200 bg-gray-100 px-1 py-1 text-center align-middle text-[10px] text-gray-500 select-none"
                                    @contextmenu.prevent="deleteRow(rowIdx)"
                                >
                                    {{ rowIdx + 1 }}
                                </td>

                                <!-- Cells (merge-aware) -->
                                <template v-for="(col, colIdx) in localColumns" :key="col.id + '-' + rowIdx">
                                    <td
                                        v-if="isCellOrigin(rowIdx, colIdx)"
                                        :colspan="getColspan(rowIdx, colIdx)"
                                        :rowspan="getRowspan(rowIdx, colIdx)"
                                        class="border px-1 py-0.5"
                                        :class="cellClass(rowIdx, colIdx)"
                                        :style="colWidthStyle(col)"
                                        @click.stop="selectCell(rowIdx, colIdx, $event)"
                                    >
                                        <input
                                            type="text"
                                            class="w-full rounded border border-transparent bg-transparent px-1 py-0.5 text-[11px] text-gray-800 outline-none hover:border-gray-200 focus:border-blue-400 focus:bg-white"
                                            :value="getCellText(row, col.key)"
                                            :placeholder="'...'"
                                            @input="updateCellText(rowIdx, col.key, ($event.target as HTMLInputElement).value)"
                                            @click.stop
                                        />
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3">
                    <button
                        class="rounded border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        @click="addRow"
                    >
                        + Add Row
                    </button>
                    <div class="flex gap-1">
                        <button
                            class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <button
                            class="rounded bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-gray-800"
                            @click="save"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { TableColumn, TableCellContent, CellMerge } from '@/types/designer'
import { isCellCovered, getMergeAt, getMergesContaining, createMergeFromSelection, removeMerge } from '../helpers/merge'

const props = defineProps<{
    columns: TableColumn[]
    rows: Record<string, TableCellContent>[]
    merges: CellMerge[]
    showHeader: boolean
}>()

const emit = defineEmits<{
    save: [columns: TableColumn[], rows: Record<string, TableCellContent>[], merges: CellMerge[], showHeader: boolean]
    close: []
}>()

// ── Local copies ──────────────────────────────

const localColumns = ref<TableColumn[]>(
    props.columns.map(c => ({ ...c })),
)

const localRows = ref<Record<string, TableCellContent>[]>(
    props.rows.map(r => {
        const cloned: Record<string, TableCellContent> = {}
        for (const [k, v] of Object.entries(r)) {
            cloned[k] = { ...v }
        }
        return cloned
    }),
)

const localMerges = ref<CellMerge[]>(props.merges.map(m => ({ ...m })))

const localShowHeader = ref(props.showHeader)

// ── Selection (for merge) ────────────────────

interface CellCoord { row: number; col: number }

const anchorCell = ref<CellCoord | null>(null)
const selectionStart = ref<CellCoord | null>(null)
const selectionEnd = ref<CellCoord | null>(null)

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
}

function clearSelection(): void {
    anchorCell.value = null
    selectionStart.value = null
    selectionEnd.value = null
}

// ── Merge helpers ────────────────────────────

const canMerge = computed(() => {
    const r = selectionRect.value
    if (!r) return false
    return (r.endRow - r.startRow + 1) * (r.endCol - r.startCol + 1) >= 2
})

const canUnmerge = computed(() => {
    const r = selectionRect.value
    if (!r) return false
    for (let row = r.startRow; row <= r.endRow; row++) {
        for (let col = r.startCol; col <= r.endCol; col++) {
            if (getMergesContaining(row, col, localMerges.value).length > 0) return true
        }
    }
    return false
})

function isCellOrigin(rowIdx: number, colIdx: number): boolean {
    // Returns true if this cell should be rendered (not covered by a merge)
    // Always true for non-merged cells
    return !isCellCovered(rowIdx, colIdx, localMerges.value)
}

function getColspan(rowIdx: number, colIdx: number): number | undefined {
    const merge = getMergeAt(rowIdx, colIdx, localMerges.value)
    return merge?.colspan
}

function getRowspan(rowIdx: number, colIdx: number): number | undefined {
    const merge = getMergeAt(rowIdx, colIdx, localMerges.value)
    return merge?.rowspan
}

function cellClass(rowIdx: number, colIdx: number): string {
    const classes = ['border-gray-200']
    if (isSelected(rowIdx, colIdx)) {
        classes.push('bg-blue-100/60')
    } else {
        classes.push(rowIdx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50')
    }
    const merge = getMergeAt(rowIdx, colIdx, localMerges.value)
    if (merge) classes.push('bg-blue-50/30')
    return classes.join(' ')
}

function doMerge(): void {
    const r = selectionRect.value
    if (!r) return

    const result = createMergeFromSelection(
        { startRow: r.startRow, startCol: r.startCol, endRow: r.endRow, endCol: r.endCol },
        localMerges.value,
        localRows.value,
        localColumns.value,
    )

    if ('error' in result) {
        // Could show a toast — for now just ignore
        console.warn('Merge rejected:', result.error)
        return
    }

    localMerges.value.push(result.merge)
    localRows.value = result.mergedRows
    clearSelection()
}

function doUnmerge(): void {
    const r = selectionRect.value
    if (!r) return

    // Find all merges that intersect the selection
    const mergesToRemove = new Set<CellMerge>()
    for (let row = r.startRow; row <= r.endRow; row++) {
        for (let col = r.startCol; col <= r.endCol; col++) {
            for (const m of getMergesContaining(row, col, localMerges.value)) {
                mergesToRemove.add(m)
            }
        }
    }

    for (const merge of mergesToRemove) {
        const result = removeMerge(merge, localMerges.value, localRows.value, localColumns.value)
        localMerges.value = result.merges
        localRows.value = result.rows
    }
    clearSelection()
}

// ── Column width (for resize preview) ─────────

function colWidthStyle(col: TableColumn): Record<string, string> {
    if (col.width != null && col.width > 0) {
        return { width: col.width + 'px' }
    }
    return {}
}

// ── Column resize (drag to resize) ──────────

const resizeState = ref<{ colIdx: number; startX: number; startWidth: number } | null>(null)

function startResize(colIdx: number, e: MouseEvent): void {
    e.preventDefault()
    e.stopPropagation()

    // Lock ALL columns to their current rendered width on first resize.
    // The column NEXT to the resized one absorbs the delta, keeping the
    // total table width unchanged — same behavior as the canvas designer.
    const ths = document.querySelectorAll<HTMLElement>('thead th')
    const locked = localColumns.value.map((c, i) => {
        if (c.width != null && c.width > 0) return { ...c }
        const renderedPx = ths?.[i + 1]?.getBoundingClientRect().width ?? 80
        return { ...c, width: Math.round(renderedPx) }
    })
    localColumns.value = locked

    const col = localColumns.value[colIdx]
    resizeState.value = { colIdx, startX: e.clientX, startWidth: col.width ?? 80 }

    document.addEventListener('mousemove', onResizeMouseMove)
    document.addEventListener('mouseup', onResizeMouseUp)
    document.body.style.userSelect = 'none'
    document.body.style.cursor = 'col-resize'
}

function onResizeMouseMove(e: MouseEvent): void {
    const rs = resizeState.value
    if (!rs) return

    const cols = [...localColumns.value]
    const dx = e.clientX - rs.startX
    const newWidth = Math.max(20, rs.startWidth + dx)
    const delta = newWidth - (cols[rs.colIdx]?.width ?? 0)

    cols[rs.colIdx] = { ...cols[rs.colIdx], width: Math.round(newWidth) }

    // Next column absorbs the delta (opposite direction)
    const nextIdx = rs.colIdx + 1
    if (nextIdx < cols.length) {
        const nextWidth = Math.max(20, (cols[nextIdx].width ?? 80) - delta)
        cols[nextIdx] = { ...cols[nextIdx], width: Math.round(nextWidth) }
    }

    localColumns.value = cols
}

function onResizeMouseUp(): void {
    resizeState.value = null
    document.removeEventListener('mousemove', onResizeMouseMove)
    document.removeEventListener('mouseup', onResizeMouseUp)
    document.body.style.userSelect = ''
    document.body.style.cursor = ''
}

// ── Cell helpers ──────────────────────────────

function getCellText(row: Record<string, TableCellContent>, colKey: string): string {
    return row[colKey]?.text ?? ''
}

function updateCellText(rowIdx: number, colKey: string, text: string): void {
    const row = localRows.value[rowIdx]
    if (!row) return
    const existing = row[colKey]
    row[colKey] = existing ? { ...existing, text } : { text }
}

// ── Column operations ─────────────────────────

function addColumn(): void {
    const idx = localColumns.value.length
    localColumns.value.push({
        id: 'col-' + crypto.randomUUID().slice(0, 8),
        key: 'field' + (idx + 1),
        header: 'Field ' + (idx + 1),
        width: undefined,
        align: 'left',
    })
    const newKey = localColumns.value[idx].key
    for (const row of localRows.value) {
        row[newKey] = { text: '' }
    }
}

function deleteColumn(colIdx: number): void {
    const colKey = localColumns.value[colIdx].key
    localColumns.value.splice(colIdx, 1)
    for (const row of localRows.value) {
        delete row[colKey]
    }
}

function updateColumnHeader(colIdx: number, header: string): void {
    localColumns.value[colIdx] = { ...localColumns.value[colIdx], header }
}

function updateColumnWidth(colIdx: number, value: string): void {
    const w = value === '' ? undefined : Math.max(0, Number(value))
    localColumns.value[colIdx] = { ...localColumns.value[colIdx], width: w }
}

function distributeColumns(): void {
    if (localColumns.value.length < 2) return
    // Clear all explicit widths so the table auto-distributes columns evenly
    localColumns.value = localColumns.value.map(c => ({ ...c, width: undefined }))
}

// ── Row operations ────────────────────────────

function addRow(): void {
    const newRow: Record<string, TableCellContent> = {}
    for (const col of localColumns.value) {
        newRow[col.key] = { text: '' }
    }
    localRows.value.push(newRow)
}

function deleteRow(index: number): void {
    localRows.value.splice(index, 1)
    // Remove any merges that reference this row or beyond
    localMerges.value = localMerges.value
        .filter(m => m.row !== index)
        .map(m => {
            if (m.row > index) return { ...m, row: m.row - 1 }
            return m
        })
}

// ── Save ──────────────────────────────────────

function save(): void {
    emit('save',
        localColumns.value.map(c => ({ ...c })),
        localRows.value.map(r => {
            const cloned: Record<string, TableCellContent> = {}
            for (const [k, v] of Object.entries(r)) {
                cloned[k] = { ...v }
            }
            return cloned
        }),
        localMerges.value.map(m => ({ ...m })),
        localShowHeader.value,
    )
}
</script>
