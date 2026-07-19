<template>
    <div data-testid="designer-toolbar" class="designer-toolbar flex w-[220px] flex-shrink-0 flex-col border-r border-gray-200 bg-white">
        <!-- Template info (sticky top) -->
        <div class="flex-shrink-0 border-b border-gray-200 px-4 py-3">
            <h3 class="mb-1 text-sm font-medium text-gray-900">Template</h3>

            <!-- Name -->
            <input
                :value="store.templateName"
                class="mb-1 w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                placeholder="Template name"
                @input="store.templateName = ($event.target as HTMLInputElement).value"
            />

            <!-- Slug -->
            <input
                :value="store.templateSlug"
                class="mb-1 w-full rounded border border-gray-300 px-2 py-1 text-xs font-mono text-gray-500 focus:border-blue-500 focus:outline-none"
                placeholder="url-slug"
                @input="store.templateSlug = ($event.target as HTMLInputElement).value"
            />

            <span
                class="text-xs"
                :class="store.isDirty ? 'text-amber-600' : 'text-green-600'"
            >
                {{ store.isDirty ? '\u25CF Unsaved' : '\u25CF Saved' }}
            </span>
        </div>

        <!-- Scrollable content area -->
        <div class="flex-1 overflow-y-auto">
            <!-- Element palette -->
            <div data-testid="element-palette" class="space-y-1 p-4">
                <h3 class="mb-2 text-sm font-medium text-gray-900">Elements</h3>
                <div
                    v-for="item in ELEMENT_ITEMS"
                    :key="item.type"
                    :data-testid="'add-' + item.type"
                    class="palette-item flex cursor-grab items-center gap-2 rounded border border-transparent px-3 py-2 text-sm transition-colors hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                    draggable="true"
                    @dragstart="onDragStart($event, item.type)"
                >
                    <span class="text-lg">{{ item.icon }}</span>
                    <span class="text-sm">{{ item.label }}</span>
                </div>
                <button
                    data-testid="draw-container-btn"
                    class="flex w-full items-center gap-2 rounded px-3 py-2 text-sm transition-colors"
                    :class="store.containerDrawMode
                        ? 'bg-blue-100 text-blue-700 border border-blue-300'
                        : 'border border-transparent text-gray-600 hover:border-gray-200 hover:bg-gray-50'"
                    @click="store.toggleContainerDrawMode()"
                >
                    <span class="text-base">⊞</span>
                    <span class="text-sm font-medium">Draw Container</span>
                    <span v-if="store.containerDrawMode" class="ml-auto text-[10px] text-blue-500">● Active</span>
                </button>
                <p class="mt-1 text-[10px] text-gray-400">
                    Click and drag on the canvas to define container size
                </p>
            </div>

            <!-- Outline section: bands + elements tree (collapsible) -->
            <div class="border-t border-gray-200">
                <button
                    data-testid="outline-section-toggle"
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="outlineSectionOpen = !outlineSectionOpen"
                >
                    <span>Outline</span>
                    <span
                        class="text-xs text-gray-400 transition-transform"
                        :class="{ 'rotate-90': outlineSectionOpen }"
                    >
                        ▶
                    </span>
                </button>

                <div v-if="outlineSectionOpen" class="px-4 pb-3">
                    <BandTree />
                </div>
            </div>

            <!-- Datasources section (collapsible) -->
            <div class="border-t border-gray-200">
                <button
                    data-testid="ds-section-toggle"
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="dsSectionOpen = !dsSectionOpen"
                >
                    <span>Datasources</span>
                    <span
                        class="text-xs text-gray-400 transition-transform"
                        :class="{ 'rotate-90': dsSectionOpen }"
                    >
                        ▶
                    </span>
                </button>

                <div v-if="dsSectionOpen" class="px-4 pb-3">
                    <!-- Add button -->
                    <button
                        data-testid="add-datasource-btn"
                        class="mb-2 flex w-full items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-xs text-gray-500 hover:border-blue-300 hover:text-blue-600"
                        @click="openAddDatasource"
                    >
                        + Add Datasource
                    </button>

                    <!-- Datasource list -->
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
                            <div class="flex-1 truncate pr-1">
                                <span class="text-xs font-medium text-gray-800">{{ ds.name }}</span>
                                <div class="flex items-center gap-1">
                                    <!-- Test status indicator -->
                                    <span
                                        v-if="ds.lastError === null && store.discoveredFields.some(f => f.datasourceId === ds.id)"
                                        class="inline-block h-1.5 w-1.5 rounded-full bg-green-500"
                                        title="Tested successfully"
                                    />
                                    <span
                                        v-else-if="ds.lastError !== null"
                                        class="inline-block h-1.5 w-1.5 rounded-full bg-red-500"
                                        :title="'Error: ' + ds.lastError"
                                    />
                                    <span
                                        v-else
                                        class="inline-block h-1.5 w-1.5 rounded-full bg-gray-300"
                                        title="Not tested"
                                    />
                                    <span class="text-[10px] text-gray-400">{{ ds.method }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-0.5">
                                <!-- Test button -->
                                <button
                                    data-testid="ds-test-btn"
                                    class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-green-100 hover:text-green-700"
                                    :disabled="!store.templateId || testingId === ds.id"
                                    @click="testDatasource(ds)"
                                >
                                    {{ testingId === ds.id ? '...' : 'Test' }}
                                </button>
                                <!-- Edit button -->
                                <button
                                    data-testid="ds-edit-btn"
                                    class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-blue-100 hover:text-blue-700"
                                    @click="openEditDatasource(ds)"
                                >
                                    Edit
                                </button>
                                <!-- Remove button -->
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

            <!-- Fields section (collapsible) -->
            <div class="border-t border-gray-200">
                <button
                    data-testid="fields-section-toggle"
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="fieldsSectionOpen = !fieldsSectionOpen"
                >
                    <span>Fields</span>
                    <span
                        class="text-xs text-gray-400 transition-transform"
                        :class="{ 'rotate-90': fieldsSectionOpen }"
                    >
                        ▶
                    </span>
                </button>

                <div v-if="fieldsSectionOpen" class="px-4 pb-3">
                    <div v-if="store.datasources.length === 0" class="py-2 text-center text-xs text-gray-400">
                        Configure a datasource first
                    </div>
                    <FieldsList v-else />
                </div>
            </div>
        </div>

        <!-- Undo / Redo (sticky bottom) -->
        <div class="flex-shrink-0 border-t border-gray-200 px-4 py-2">
            <div class="flex gap-1">
                <button
                    data-testid="undo-button"
                    class="flex-1 rounded px-2 py-1.5 text-xs transition-colors disabled:opacity-30"
                    :class="store.canUndo ? 'hover:bg-gray-100 text-gray-700' : 'text-gray-300'"
                    :disabled="!store.canUndo"
                    @click="store.undo()"
                >
                    ↩ Undo
                </button>
                <button
                    data-testid="redo-button"
                    class="flex-1 rounded px-2 py-1.5 text-xs transition-colors disabled:opacity-30"
                    :class="store.canRedo ? 'hover:bg-gray-100 text-gray-700' : 'text-gray-300'"
                    :disabled="!store.canRedo"
                    @click="store.redo()"
                >
                    ↪ Redo
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex-shrink-0 border-t border-gray-200 px-4 py-3">
            <span class="text-xs text-gray-400">{{ store.elementCount }} elements</span>
        </div>

        <!-- Datasource Form modal -->
        <DatasourceForm
            v-if="showForm"
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
import type { ElementType, DatasourceConfig } from '@/types/designer'
import DatasourceForm from '../forms/DatasourceForm.vue'
import FieldsList from '../navigation/FieldsList.vue'
import VariablesPanel from '../navigation/VariablesPanel.vue'
import BandTree from '../navigation/BandTree.vue'

const store = useDesignerStore()

// ── Collapsible section state ──────────────────

const outlineSectionOpen = ref(true) // visible by default
const dsSectionOpen = ref(false)
const fieldsSectionOpen = ref(false)

// ── Datasource form modal state ────────────────

const showForm = ref(false)
const editingDatasource = ref<DatasourceConfig | null>(null)
const testingId = ref<string | null>(null)

// ── Element palette items ─────────────────────

interface ElementItem {
    type: ElementType
    icon: string
    label: string
}

const ELEMENT_ITEMS: ElementItem[] = [
    { type: 'text', icon: 'T', label: 'Text' },
    { type: 'image', icon: '\uD83D\uDDBC', label: 'Image' },
    { type: 'table', icon: '\u229E', label: 'Table' },
    { type: 'line', icon: '\u2501', label: 'Line' },
    { type: 'rectangle', icon: '\u25A0', label: 'Rectangle' },
    { type: 'barcode', icon: '\u258C\u258C', label: 'Barcode' },
    { type: 'page_number', icon: '\u00B6', label: 'Page Number' },
]

// ── Drag from palette ─────────────────────────

function onDragStart(event: DragEvent, type: ElementType): void {
    if (!event.dataTransfer) return
    event.dataTransfer.setData('element-type', type)
    event.dataTransfer.effectAllowed = 'copy'
}

// ── Datasource form handlers ───────────────────

function openAddDatasource(): void {
    editingDatasource.value = null
    showForm.value = true
}

function openEditDatasource(ds: DatasourceConfig): void {
    editingDatasource.value = ds
    showForm.value = true
}

function onFormSave(config: Omit<DatasourceConfig, 'id' | 'lastError'>): void {
    if (editingDatasource.value) {
        store.updateDatasource(editingDatasource.value.id, config)
    } else {
        store.addDatasource(config)
    }
    showForm.value = false
    editingDatasource.value = null
}

function onFormCancel(): void {
    showForm.value = false
    editingDatasource.value = null
}

// ── Test datasource ────────────────────────────

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
