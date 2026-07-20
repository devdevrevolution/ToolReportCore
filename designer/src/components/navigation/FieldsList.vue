<template>
    <div data-testid="fields-list">
        <!-- Empty state -->
        <div
            v-if="groups.length === 0"
            data-testid="fields-empty-state"
            class="py-4 text-center text-xs text-gray-400"
        >
            No fields discovered yet. Configure a datasource and test the connection.
        </div>

        <!-- Datasource groups -->
        <div v-for="group in groups" :key="group.datasource.id" class="mb-2">
            <!-- Group header -->
            <div
                data-testid="ds-group-header"
                class="mb-1 flex items-center justify-between rounded bg-gray-50 px-2 py-1"
            >
                <span class="text-xs font-medium text-gray-700">{{ group.datasource.name }}</span>
                <span class="text-[10px] text-gray-400">{{ group.fieldCount }} fields</span>
            </div>

            <!-- Tree view of fields -->
            <TreeView
                :nodes="group.treeNodes"
                :depth="0"
                :type-badge-class="typeBadgeClass"
                :on-field-drag-start="onFieldDragStart"
                :on-field-drag-end="onFieldDragEnd"
                :collection-path="activeCollectionPath"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type { DiscoveredField, DatasourceConfig } from '@/types/designer'
import { buildFieldTree } from '@/utils/buildFieldTree'
import type { TreeNode } from '@/utils/buildFieldTree'
import TreeView from './TreeView.vue'

const store = useDesignerStore()

// ── Active band collection context ─────────────

/**
 * The collectionPath of the currently selected band (if it's a detail band).
 * Used to highlight matching fields in the tree.
 */
const activeCollectionPath = computed<string | null>(() => {
    const band = store.selectedBand
    if (!band || band.type !== 'detail' || !band.collectionPath) return null
    return band.collectionPath
})

// ── Helpers ────────────────────────────────────

/**
 * Map field types to Tailwind badge colours.
 */
function typeBadgeClass(type: string): string {
    const map: Record<string, string> = {
        string: 'bg-blue-100 text-blue-700',
        number: 'bg-green-100 text-green-700',
        boolean: 'bg-amber-100 text-amber-700',
        array: 'bg-purple-100 text-purple-700',
        object: 'bg-pink-100 text-pink-700',
        null: 'bg-gray-100 text-gray-500',
    }
    return map[type] ?? 'bg-gray-100 text-gray-600'
}

// ── Grouped tree ───────────────────────────────

interface FieldGroup {
    datasource: DatasourceConfig
    treeNodes: TreeNode[]
    fieldCount: number
}

const groups = computed<FieldGroup[]>(() => {
    const map = new Map<string, FieldGroup>()
    const dsLookup = new Map(store.datasources.map(d => [d.id, d]))

    // Group fields by datasourceId
    const fieldsByDs = new Map<string, DiscoveredField[]>()
    for (const field of store.discoveredFields) {
        const ds = dsLookup.get(field.datasourceId)
        if (!ds) continue

        if (!fieldsByDs.has(field.datasourceId)) {
            fieldsByDs.set(field.datasourceId, [])
        }
        fieldsByDs.get(field.datasourceId)!.push(field)
    }

    // Build tree per datasource, preserving insertion order
    for (const ds of store.datasources) {
        const fields = fieldsByDs.get(ds.id)
        if (!fields || fields.length === 0) continue

        map.set(ds.id, {
            datasource: ds,
            treeNodes: buildFieldTree(fields),
            fieldCount: fields.length,
        })
    }

    return Array.from(map.values())
})

// ── Drag handlers ──────────────────────────────

function onFieldDragStart(event: DragEvent, field: DiscoveredField): void {
    if (!event.dataTransfer) return
    event.dataTransfer.setData(
        'field-path',
        JSON.stringify({
            path: field.path,
            name: field.name,
            type: field.type,
            datasourceId: field.datasourceId,
        }),
    )
    event.dataTransfer.effectAllowed = 'copyLink'
    if (event.target instanceof HTMLElement) {
        event.target.classList.add('opacity-50')
    }
}

function onFieldDragEnd(event: DragEvent): void {
    if (event.target instanceof HTMLElement) {
        event.target.classList.remove('opacity-50')
    }
}
</script>
