<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-start justify-center pt-20">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/30" @mousedown="$emit('close')" />

            <!-- Panel -->
            <div
                class="relative z-10 flex max-h-[60vh] w-[340px] flex-col rounded-lg border border-gray-200 bg-white shadow-xl"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="text-sm font-medium text-gray-900">Select a field</span>
                    <button
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        @click="$emit('close')"
                    >
                        <span class="text-xs">✕</span>
                    </button>
                </div>

                <!-- Empty state -->
                <div
                    v-if="groups.length === 0"
                    class="flex flex-1 items-center justify-center px-4 py-8 text-center text-xs text-gray-400"
                >
                    No fields discovered yet.<br />
                    Configure a datasource and test the connection.
                </div>

                <!-- Field tree grouped by datasource -->
                <div v-else class="flex-1 overflow-y-auto px-1 py-2">
                    <div v-for="group in groups" :key="group.datasource.id" class="mb-2">
                        <!-- Datasource header -->
                        <div class="mx-2 mb-1 flex items-center gap-2 rounded bg-gray-50 px-2 py-1.5">
                            <span class="flex-1 text-xs font-medium text-gray-700">{{ group.datasource.name }}</span>
                            <span class="text-[10px] text-gray-400">{{ group.fieldCount }} fields</span>
                        </div>

                        <!-- TreeView -->
                        <TreeView
                            :nodes="group.treeNodes"
                            :depth="0"
                            :type-badge-class="typeBadgeClass"
                            :on-node-click="onNodeClick"
                        />
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import { buildFieldTree } from '@/utils/buildFieldTree'
import type { TreeNode } from '@/utils/buildFieldTree'
import type { DatasourceConfig, DiscoveredField } from '@/types/designer'
import TreeView from '../navigation/TreeView.vue'

const props = withDefaults(defineProps<{
    fields?: DiscoveredField[]
}>(), {
    fields: undefined,
})

const emit = defineEmits<{
    select: [path: string]
    close: []
}>()

const store = useDesignerStore()

// ── Type badge colors ─────────────────────────

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

// ── Group fields by datasource (tree structure) ─

interface FieldGroup {
    datasource: DatasourceConfig
    treeNodes: TreeNode[]
    fieldCount: number
}

const groups = computed<FieldGroup[]>(() => {
    const sourceFields = props.fields ?? store.discoveredFields
    const dsLookup = new Map(store.datasources.map(d => [d.id, d]))
    const fieldsByDs = new Map<string, DiscoveredField[]>()

    for (const field of sourceFields) {
        const ds = dsLookup.get(field.datasourceId)
        if (!ds) continue
        if (!fieldsByDs.has(field.datasourceId)) {
            fieldsByDs.set(field.datasourceId, [])
        }
        fieldsByDs.get(field.datasourceId)!.push(field)
    }

    const result: FieldGroup[] = []
    for (const ds of store.datasources) {
        const fields = fieldsByDs.get(ds.id)
        if (!fields || fields.length === 0) continue
        result.push({
            datasource: ds,
            treeNodes: buildFieldTree(fields),
            fieldCount: fields.length,
        })
    }
    return result
})

// ── Node click handler ────────────────────────

function onNodeClick(node: TreeNode): void {
    if (node.type === 'object') return
    emit('select', node.originalField.path)
}
</script>
