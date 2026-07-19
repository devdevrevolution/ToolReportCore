<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="emit('close')"
            @keydown.escape="emit('close')"
        >
            <div
                class="relative mx-4 flex w-full max-w-xl flex-col rounded-lg bg-white shadow-2xl"
                style="max-height: 85vh;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Configure Columns</h3>
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
                        v-if="localColumns.length === 0"
                        class="rounded border-2 border-dashed border-gray-200 py-12 text-center text-sm text-gray-400"
                    >
                        No columns yet. Define the columns for your table.
                    </div>

                    <!-- Column list -->
                    <div v-else class="space-y-2">
                        <div
                            v-for="(col, i) in localColumns"
                            :key="col.id"
                            class="rounded-lg border border-gray-200 bg-gray-50 p-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500">#{{ i + 1 }}</span>
                                <div class="flex items-center gap-1">
                                    <button
                                        class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-gray-200 disabled:opacity-30"
                                        title="Move up"
                                        :disabled="i === 0"
                                        @click="moveColumn(i, i - 1)"
                                    >
                                        ▲
                                    </button>
                                    <button
                                        class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-gray-200 disabled:opacity-30"
                                        title="Move down"
                                        :disabled="i === localColumns.length - 1"
                                        @click="moveColumn(i, i + 1)"
                                    >
                                        ▼
                                    </button>
                                    <button
                                        class="rounded px-1.5 py-0.5 text-[10px] text-red-500 hover:bg-red-50"
                                        title="Remove column"
                                        @click="removeColumn(i)"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-5 gap-2">
                                <!-- Key -->
                                <div class="col-span-2">
                                    <label class="mb-0.5 block text-[10px] font-medium text-gray-600">Key</label>
                                    <div class="flex gap-0.5">
                                        <input
                                            type="text"
                                            placeholder="field.path"
                                            class="min-w-0 flex-1 rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                            :value="col.key"
                                            @input="updateColumn(i, 'key', ($event.target as HTMLInputElement).value)"
                                        />
                                        <button
                                            class="shrink-0 rounded px-1.5 py-1 text-[10px] text-blue-600 hover:bg-blue-100"
                                            title="Select field key"
                                            @click="openFieldSelector(i)"
                                        >
                                            ↳
                                        </button>
                                    </div>
                                </div>

                                <!-- Header -->
                                <div class="col-span-2">
                                    <label class="mb-0.5 block text-[10px] font-medium text-gray-600">Header</label>
                                    <input
                                        type="text"
                                        placeholder="Column label"
                                        class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                        :value="col.header"
                                        @input="updateColumn(i, 'header', ($event.target as HTMLInputElement).value)"
                                    />
                                </div>

                                <!-- Width -->
                                <div>
                                    <label class="mb-0.5 block text-[10px] font-medium text-gray-600">W (mm)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        placeholder="auto"
                                        class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                        :value="col.width ?? ''"
                                        @input="updateColumn(i, 'width', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : undefined)"
                                    />
                                </div>
                            </div>

                            <!-- Align -->
                            <div class="mt-1.5">
                                <label class="mb-0.5 block text-[10px] font-medium text-gray-600">Align</label>
                                <div class="flex gap-1">
                                    <button
                                        v-for="opt in alignOptions"
                                        :key="opt"
                                        class="rounded border px-2 py-0.5 text-[10px] transition-colors"
                                        :class="(col.align ?? 'left') === opt
                                            ? 'border-blue-400 bg-blue-50 text-blue-700 font-medium'
                                            : 'border-gray-200 text-gray-500 hover:bg-gray-100'"
                                        @click="updateColumn(i, 'align', opt)"
                                    >
                                        {{ opt === 'left' ? '≡ Left' : opt === 'center' ? '≡ Center' : '≡ Right' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3">
                    <div class="flex gap-1">
                        <button
                            class="rounded border border-blue-300 bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-100"
                            @click="addFromField"
                        >
                            + From field
                        </button>
                        <button
                            class="rounded border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                            @click="addEmpty"
                        >
                            + Empty
                        </button>
                    </div>
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

        <!-- Field selector for column key -->
        <FieldSelector
            v-if="showFieldSelector"
            :fields="filteredFieldsForSelector"
            @select="onFieldSelected"
            @close="closeFieldSelector"
        />
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { TableColumn, DiscoveredField } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'
import FieldSelector from '@/components/modals/FieldSelector.vue'

const store = useDesignerStore()

const props = defineProps<{
    columns: TableColumn[]
    variable: string
}>()

const emit = defineEmits<{
    save: [columns: TableColumn[]]
    close: []
}>()

const alignOptions = ['left', 'center', 'right'] as const

// Local copy to work on
const localColumns = ref<TableColumn[]>(props.columns.map(c => ({ ...c })))

// Field selector state
const showFieldSelector = ref(false)
const pendingColumnIndex = ref<number | null>(null)

/**
 * Find the datasource field matching the table variable.
 */
function findMatchingField(variable: string): DiscoveredField | undefined {
    // Direct match (e.g. "orders[].items")
    let f = store.discoveredFields.find(f => f.path === variable)
    if (f) return f

    // With [] suffix
    f = store.discoveredFields.find(f => f.path === variable + '[]')
    if (f) return f

    // Short path: search for fields ending with [].{variable} or .{variable}
    f = store.discoveredFields.find(f =>
        f.path.endsWith(`[].${variable}`) ||
        f.path.endsWith(`.${variable}`) ||
        f.path.endsWith(`[].${variable}[]`) ||
        f.path.endsWith(`.${variable}[]`)
    )
    if (f) return f

    // Bracket notation [].variable → local data reference
    if (variable.startsWith('[].')) {
        const bareKey = variable.slice(3)
        f = store.discoveredFields.find(f =>
            f.path === bareKey ||
            f.path.endsWith(`[].${bareKey}`) ||
            f.path.endsWith(`.${bareKey}`)
        )
        if (f) return f
    }

    return undefined
}

/**
 * Fields filtered to show only the subtree under the table variable,
 * with paths shortened relative to the array item so the column key
 * resolves from each row (e.g. "product" not "orders[].items[].product").
 * Preserves the tree structure so nested fields are displayed hierarchically.
 */
const filteredFieldsForSelector = computed<DiscoveredField[]>(() => {
    if (!props.variable) return store.discoveredFields

    const parent = findMatchingField(props.variable)
    if (!parent || parent.type !== 'array') return store.discoveredFields

    // Array item prefix: e.g. "orders[].items" → "orders[].items[]"
    const arrayPrefix = parent.path.endsWith('[]') ? parent.path : parent.path + '[]'

    // Get ALL descendants from the same datasource under this array
    const descendants = store.discoveredFields.filter(f =>
        f.datasourceId === parent.datasourceId &&
        f.path.startsWith(arrayPrefix + '.')
    )

    if (descendants.length === 0) return store.discoveredFields

    // Strip the common prefix and adjust levels to build a proper tree
    // relative to the array item
    const prefixLen = arrayPrefix.length + 1 // include the trailing '.'
    const baseLevel = parent.level + 1

    return descendants.map(f => ({
        ...f,
        path: f.path.slice(prefixLen),
        level: f.level - baseLevel,
    }))
})

function addEmpty(): void {
    localColumns.value.push({
        id: crypto.randomUUID(),
        key: '',
        header: '',
        width: undefined,
        align: 'left',
    })
}

function addFromField(): void {
    pendingColumnIndex.value = null // signal: add new, not edit
    showFieldSelector.value = true
}

function removeColumn(index: number): void {
    localColumns.value.splice(index, 1)
}

function moveColumn(from: number, to: number): void {
    if (from < 0 || from >= localColumns.value.length || to < 0 || to >= localColumns.value.length || from === to) return
    const [moved] = localColumns.value.splice(from, 1)
    localColumns.value.splice(to, 0, moved)
}

function updateColumn(index: number, field: string, value: unknown): void {
    localColumns.value[index] = { ...localColumns.value[index], [field]: value }
}

function pathToHeader(path: string): string {
    const segments = path.replace(/\[.*?\]/g, '').split('.')
    const last = segments[segments.length - 1] || path
    return last.charAt(0).toUpperCase() + last.slice(1).replace(/_/g, ' ')
}

function openFieldSelector(index: number): void {
    pendingColumnIndex.value = index
    showFieldSelector.value = true
}

function onFieldSelected(path: string): void {
    if (pendingColumnIndex.value !== null) {
        // Edit existing column's key
        updateColumn(pendingColumnIndex.value, 'key', path)
        if (!localColumns.value[pendingColumnIndex.value].header) {
            updateColumn(pendingColumnIndex.value, 'header', pathToHeader(path))
        }
    } else {
        // Add new column from field
        localColumns.value.push({
            id: crypto.randomUUID(),
            key: path,
            header: pathToHeader(path),
            width: undefined,
            align: 'left',
        })
    }
    closeFieldSelector()
}

function closeFieldSelector(): void {
    showFieldSelector.value = false
    pendingColumnIndex.value = null
}

function save(): void {
    emit('save', localColumns.value.map(c => ({ ...c })))
}
</script>
