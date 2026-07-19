<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="emit('close')"
            @keydown.escape="emit('close')"
        >
            <div
                class="relative mx-4 flex w-full max-w-3xl flex-col rounded-lg bg-white shadow-2xl"
                style="max-height: 85vh;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Edit Rows</h3>
                    <button
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-auto p-4">
                    <!-- Empty state -->
                    <div
                        v-if="localRows.length === 0"
                        class="rounded border-2 border-dashed border-gray-200 py-12 text-center text-sm text-gray-400"
                    >
                        No rows yet. Add static data rows for your table.
                    </div>

                    <!-- Row table -->
                    <table v-else class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="w-10 border border-gray-200 bg-gray-50 px-1 py-1 text-[10px] text-gray-500">#</th>
                                <th
                                    v-for="col in columns"
                                    :key="col.id"
                                    class="border border-gray-200 bg-gray-50 px-2 py-1 text-[10px] font-medium text-gray-600"
                                >
                                    {{ col.header || col.key }}
                                </th>
                                <th class="w-24 border border-gray-200 bg-gray-50 px-1 py-1 text-[10px] text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, rowIdx) in localRows"
                                :key="rowIdx"
                                :class="[rowIdx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50', isRowCovered(rowIdx) ? 'opacity-50' : '']"
                            >
                                <!-- Row number -->
                                <td class="border border-gray-200 px-1 py-1 text-center text-[10px] text-gray-400">
                                    {{ rowIdx + 1 }}
                                </td>

                                <!-- Cell inputs -->
                                <td
                                    v-for="(col, colIdx) in columns"
                                    :key="col.id"
                                    class="border border-gray-200 px-1 py-0.5"
                                    :class="getCellClass(rowIdx, colIdx)"
                                    :colspan="getColspan(rowIdx, colIdx)"
                                    :rowspan="getRowspan(rowIdx, colIdx)"
                                >
                                                <template v-if="!isCellCovered(rowIdx, colIdx, localMerges)">
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="text"
                                                class="min-w-0 flex-1 rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                                :placeholder="col.key"
                                                :value="getCellText(row, col.key)"
                                                @input="updateCellText(rowIdx, col.key, ($event.target as HTMLInputElement).value)"
                                            />
                                            <button
                                                class="shrink-0 rounded px-1 py-1 text-[10px] text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                title="Style cell"
                                                @click="openStylePopover(rowIdx, col.key)"
                                            >
                                                🖊
                                            </button>
                                        </div>
                                    </template>
                                </td>

                                <!-- Row actions -->
                                <td class="border border-gray-200 px-1 py-1 text-center">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <button
                                            class="rounded px-1 py-0.5 text-[10px] text-gray-500 hover:bg-gray-200 disabled:opacity-30"
                                            title="Move up"
                                            :disabled="rowIdx === 0"
                                            @click="moveRow(rowIdx, rowIdx - 1)"
                                        >▲</button>
                                        <button
                                            class="rounded px-1 py-0.5 text-[10px] text-gray-500 hover:bg-gray-200 disabled:opacity-30"
                                            title="Move down"
                                            :disabled="rowIdx === localRows.length - 1"
                                            @click="moveRow(rowIdx, rowIdx + 1)"
                                        >▼</button>
                                        <button
                                            class="rounded px-1.5 py-0.5 text-[10px] text-red-500 hover:bg-red-50"
                                            title="Delete row"
                                            @click="removeRow(rowIdx)"
                                        >✕</button>
                                    </div>
                                </td>
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

        <!-- Cell style popover -->
        <TableCellStylePopover
            v-if="styleTarget"
            :cell="getCellObject(styleTarget.rowIdx, styleTarget.colKey)"
            :element-styles="elementStyles"
            @save="onStyleSave"
            @close="closeStylePopover"
        />
    </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import type { DesignerStyles, TableColumn, TableCellContent } from '@/types/designer'
import { isCellCovered, getMergeAt, getMergesContaining } from '../helpers/merge'
import TableCellStylePopover from './TableCellStylePopover.vue'

const props = defineProps<{
    columns: TableColumn[]
    rows: Record<string, TableCellContent>[]
    merges: { row: number; col: number; rowspan: number; colspan: number }[]
    elementStyles: DesignerStyles
}>()

const emit = defineEmits<{
    save: [rows: Record<string, TableCellContent>[]]
    close: []
}>()

// ── Local state ──────────────────────────────

const localRows = ref<Record<string, TableCellContent>[]>(
    props.rows.map(r => {
        const cloned: Record<string, TableCellContent> = {}
        for (const [k, v] of Object.entries(r)) {
            cloned[k] = { ...v }
        }
        return cloned
    }),
)

const localMerges = reactive(props.merges)

// Style popover state
const styleTarget = ref<{ rowIdx: number; colKey: string } | null>(null)

// ── Cell value helpers ────────────────────────

function getCellText(row: Record<string, TableCellContent>, colKey: string): string {
    return row[colKey]?.text ?? ''
}

function updateCellText(rowIdx: number, colKey: string, text: string): void {
    const row = localRows.value[rowIdx]
    if (!row) return
    const existing = row[colKey]
    row[colKey] = existing ? { ...existing, text } : { text }
}

function getCellObject(rowIdx: number, colKey: string): TableCellContent {
    return localRows.value[rowIdx]?.[colKey] ?? { text: '' }
}

// ── Merge-aware cell rendering ────────────────

function isRowCovered(rowIdx: number): boolean {
    return localMerges.some(m => m.row === rowIdx && m.rowspan > 1 && m.col !== 0)
}

function getCellClass(rowIdx: number, colIdx: number): string {
    const merge = getMergeAt(rowIdx, colIdx, localMerges)
    if (merge) return 'bg-blue-50/30'
    if (isCellCovered(rowIdx, colIdx, localMerges)) return 'hidden'
    return ''
}

function getColspan(rowIdx: number, colIdx: number): number | undefined {
    const merge = getMergeAt(rowIdx, colIdx, localMerges)
    return merge?.colspan
}

function getRowspan(rowIdx: number, colIdx: number): number | undefined {
    const merge = getMergeAt(rowIdx, colIdx, localMerges)
    return merge?.rowspan
}

// ── Row operations ────────────────────────────

function addRow(): void {
    const newRow: Record<string, TableCellContent> = {}
    for (const col of props.columns) {
        newRow[col.key] = { text: '' }
    }
    localRows.value.push(newRow)
}

function removeRow(index: number): void {
    localRows.value.splice(index, 1)
}

function moveRow(from: number, to: number): void {
    if (from < 0 || from >= localRows.value.length || to < 0 || to >= localRows.value.length || from === to) return
    const [moved] = localRows.value.splice(from, 1)
    localRows.value.splice(to, 0, moved)
}

// ── Style popover ─────────────────────────────

function openStylePopover(rowIdx: number, colKey: string): void {
    styleTarget.value = { rowIdx, colKey }
}

function onStyleSave(cell: TableCellContent): void {
    if (!styleTarget.value) return
    const { rowIdx, colKey } = styleTarget.value
    localRows.value[rowIdx][colKey] = { ...cell }
    closeStylePopover()
}

function closeStylePopover(): void {
    styleTarget.value = null
}

// ── Save ──────────────────────────────────────

function save(): void {
    emit('save', localRows.value.map(r => {
        const cloned: Record<string, TableCellContent> = {}
        for (const [k, v] of Object.entries(r)) {
            cloned[k] = { ...v }
        }
        return cloned
    }))
}
</script>
