<template>
    <div
        data-testid="composite-toolbar"
        class="relative flex flex-shrink-0 flex-col border-r border-gray-200 bg-white"
        :style="{ width: toolbarWidth + 'px' }"
    >
        <!-- Resize handle (right edge) -->
        <div
            class="absolute right-0 top-0 z-40 h-full w-1 cursor-col-resize transition-colors hover:bg-indigo-300"
            :class="{ 'bg-indigo-400': isResizing }"
            @mousedown.prevent="onResizeStart"
        />
        <!-- Template info -->
        <div class="flex-shrink-0 border-b border-gray-200 px-4 py-3">
            <h3 class="mb-1 text-sm font-medium text-gray-900">Template</h3>
            <input
                :value="store.templateName"
                class="mb-1 w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none"
                placeholder="Template name"
                @input="store.templateName = ($event.target as HTMLInputElement).value"
            />
            <input
                :value="store.templateSlug"
                class="mb-1 w-full rounded border border-gray-300 px-2 py-1 font-mono text-xs text-gray-500 focus:border-indigo-500 focus:outline-none"
                placeholder="url-slug"
                @input="store.templateSlug = ($event.target as HTMLInputElement).value"
            />
            <span
                class="text-xs"
                :class="store.isDirty ? 'text-amber-600' : 'text-green-600'"
            >
                {{ store.isDirty ? '● Unsaved' : '● Saved' }}
            </span>
        </div>

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto">

            <!-- Components palette -->
            <div data-testid="composite-palette" class="space-y-1 p-4">
                <h3 class="mb-2 text-sm font-medium text-gray-900">Components</h3>
                <button
                    v-for="item in COMPOSITE_ITEMS"
                    :key="item.type"
                    :data-testid="'add-' + item.type"
                    draggable="true"
                    class="flex w-full cursor-grab items-center gap-2 rounded border border-transparent px-3 py-2 text-sm transition-colors hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    @dragstart="onNodeDragStart($event, item.type)"
                    @click="addNode(item.type)"
                >
                    <span class="text-lg">{{ item.icon }}</span>
                    <span class="text-sm">{{ item.label }}</span>
                </button>
            </div>

            <!-- Outline: bands + composite tree -->
            <div class="border-t border-gray-200">
                <button
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="outlineOpen = !outlineOpen"
                >
                    <span>Outline</span>
                    <span
                        class="text-xs text-gray-400 transition-transform"
                        :class="{ 'rotate-90': outlineOpen }"
                    >
                        ▶
                    </span>
                </button>
                <div v-if="outlineOpen" class="px-4 pb-3">
                    <CompositeBandTree />
                </div>
            </div>

            <!-- Datasources -->
            <div class="border-t border-gray-200">
                <button
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="dsOpen = !dsOpen"
                >
                    <span>Datasources</span>
                    <span
                        class="text-xs text-gray-400 transition-transform"
                        :class="{ 'rotate-90': dsOpen }"
                    >
                        ▶
                    </span>
                </button>
                <div v-if="dsOpen" class="px-4 pb-3">
                    <button
                        data-testid="add-datasource-btn"
                        class="mb-2 flex w-full items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-xs text-gray-500 hover:border-indigo-300 hover:text-indigo-600"
                        @click="openAddDatasource"
                    >
                        + Add Datasource
                    </button>
                    <div v-if="store.datasources.length === 0" class="py-2 text-center text-xs text-gray-400">
                        No datasources configured
                    </div>
                    <div
                        v-for="ds in store.datasources"
                        :key="ds.id"
                        data-testid="ds-list-item"
                        class="mb-1 rounded border border-gray-100 bg-gray-50 px-2 py-1.5"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex-1 truncate text-xs font-medium text-gray-800">{{ ds.name }}</span>
                            <div class="flex items-center gap-0.5">
                                <button
                                    data-testid="ds-test-btn"
                                    class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-green-100 hover:text-green-700"
                                    :disabled="!store.templateId || testingId === ds.id"
                                    @click="testDatasource(ds)"
                                >
                                    {{ testingId === ds.id ? '...' : 'Test' }}
                                </button>
                                <button
                                    data-testid="ds-edit-btn"
                                    class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-blue-100 hover:text-blue-700"
                                    @click="openEditDatasource(ds)"
                                >
                                    Edit
                                </button>
                                <button
                                    data-testid="ds-remove-btn"
                                    class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-red-100 hover:text-red-700"
                                    @click="store.removeDatasource(ds.id)"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Variables -->
            <VariablesPanel />

            <!-- Fields -->
            <div class="border-t border-gray-200">
                <button
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="fieldsOpen = !fieldsOpen"
                >
                    <span>Fields</span>
                    <span
                        class="text-xs text-gray-400 transition-transform"
                        :class="{ 'rotate-90': fieldsOpen }"
                    >
                        ▶
                    </span>
                </button>
                <div v-if="fieldsOpen" class="px-4 pb-3">
                    <div v-if="store.datasources.length === 0" class="py-2 text-center text-xs text-gray-400">
                        Configure a datasource first
                    </div>
                    <FieldsList v-else />
                </div>
            </div>
        </div>

        <!-- Undo / Redo -->
        <div class="flex-shrink-0 border-t border-gray-200 px-4 py-2">
            <div class="flex gap-1">
                <button
                    class="flex-1 rounded px-2 py-1.5 text-xs transition-colors disabled:opacity-30"
                    :class="store.canUndo ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300'"
                    :disabled="!store.canUndo"
                    @click="store.undo()"
                >
                    ↩ Undo
                </button>
                <button
                    class="flex-1 rounded px-2 py-1.5 text-xs transition-colors disabled:opacity-30"
                    :class="store.canRedo ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300'"
                    :disabled="!store.canRedo"
                    @click="store.redo()"
                >
                    ↪ Redo
                </button>
            </div>
        </div>

        <!-- Datasource form modal -->
        <DatasourceForm
            v-if="showDsForm"
            :datasource="editingDatasource ?? undefined"
            @save="onFormSave"
            @cancel="onFormCancel"
        />
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import { useApi } from '@/composables/useApi'
import { useResize } from '@/composables/useResize'
import type { CompositeNodeType, DatasourceConfig } from '@/types/designer'
import CompositeBandTree from '../navigation/CompositeBandTree.vue'
import FieldsList from '../navigation/FieldsList.vue'
import VariablesPanel from '../navigation/VariablesPanel.vue'
import DatasourceForm from '../forms/DatasourceForm.vue'

const store = useDesignerStore()

// ── Toolbar resize ─────────────────────────────

const MIN_WIDTH = 160
const MAX_WIDTH = 400
const toolbarWidth = ref(220)

const { onResizeStart, isResizing } = useResize({
    direction: 'horizontal',
    apply: ({ dx }) => {
        toolbarWidth.value = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, toolbarWidth.value + dx))
    },
})

// ── Collapsible state ──────────────────────────

const outlineOpen = ref(true)
const dsOpen = ref(false)
const fieldsOpen = ref(false)

// ── Composite palette ──────────────────────────

interface CompositeItem {
    type: CompositeNodeType
    icon: string
    label: string
}

const COMPOSITE_ITEMS: CompositeItem[] = [
    { type: 'VBox', icon: '▦', label: 'VBox' },
    { type: 'HBox', icon: '▥', label: 'HBox' },
    { type: 'Label', icon: 'T', label: 'Label' },
    { type: 'Shape', icon: '◇', label: 'Shape' },
    { type: 'Table', icon: '⊞', label: 'Table' },
    { type: 'Image', icon: '🖼', label: 'Image' },
]

function addNode(type: CompositeNodeType): void {
    const bandId = store.selectedBandId ?? 'detail'
    store.addCompositeNode(bandId, type)
}

/**
 * Drag a composite component from the palette onto a band.
 * The drop target (CompositeCanvas) reads 'composite-node-type'
 * and calls store.addCompositeNode(bandId, type).
 */
function onNodeDragStart(event: DragEvent, type: CompositeNodeType): void {
    if (!event.dataTransfer) return
    event.dataTransfer.setData('composite-node-type', type)
    event.dataTransfer.effectAllowed = 'copy'
}

// ── Datasource form ────────────────────────────

const showDsForm = ref(false)
const editingDatasource = ref<DatasourceConfig | null>(null)
const testingId = ref<string | null>(null)

function openAddDatasource(): void {
    editingDatasource.value = null
    showDsForm.value = true
}

function openEditDatasource(ds: DatasourceConfig): void {
    editingDatasource.value = ds
    showDsForm.value = true
}

function onFormSave(config: Omit<DatasourceConfig, 'id' | 'lastError'>): void {
    if (editingDatasource.value) {
        store.updateDatasource(editingDatasource.value.id, config)
    } else {
        store.addDatasource(config)
    }
    showDsForm.value = false
    editingDatasource.value = null
}

function onFormCancel(): void {
    showDsForm.value = false
    editingDatasource.value = null
}

async function testDatasource(ds: DatasourceConfig): Promise<void> {
    if (!store.templateId) return
    testingId.value = ds.id
    try {
        const api = useApi()
        const result = await api.testDatasource(store.templateId, {
            datasource: {
                id: ds.id,
                url: ds.url,
                method: ds.method,
                headers: ds.headers,
                auth: ds.auth,
                timeout: ds.timeout,
            },
        })
        store.setDatasourceTestResult(ds.id, result)
    } catch (e) {
        store.setDatasourceTestResult(ds.id, {
            success: false,
            error: e instanceof Error ? e.message : 'Unknown error',
        })
    } finally {
        testingId.value = null
    }
}
</script>
