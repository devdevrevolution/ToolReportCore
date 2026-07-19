<template>
    <div data-testid="tree-view" class="tree-view">
        <div
            v-for="node in nodes"
            :key="node.id"
            class="tree-node"
            :class="{ 'tree-node--draggable': isDraggable(node), 'tree-node--object': !isDraggable(node) }"
            :data-testid="isDraggable(node) ? 'field-entry' : 'tree-node'"
            :draggable="isDraggable(node)"
            @dragstart="onNodeDragStart($event, node)"
            @dragend="onNodeDragEnd"
        >
            <!-- Row: indent + toggle + label + type badge + path -->
            <div
                class="tree-node__row group flex items-center gap-1 rounded px-2 py-1 text-xs transition-colors"
                :class="{
                    'hover:bg-blue-50 cursor-grab': isDraggable(node) && !onNodeClick,
                    'cursor-pointer hover:bg-blue-50': isDraggable(node) && !!onNodeClick,
                    'cursor-default': !isDraggable(node),
                }"
                :style="{ paddingLeft: depth * 16 + 8 + 'px' }"
                @click.stop="onNodeClick?.(node)"
            >
                <!-- Spacer / Toggle -->
                <span v-if="!hasVisibleChildren(node)" class="inline-block w-3.5 flex-shrink-0" />
                <button
                    v-else
                    data-testid="tree-node-toggle"
                    class="inline-flex w-3.5 flex-shrink-0 items-center justify-center text-[10px] text-gray-400 hover:text-gray-600 focus:outline-none"
                    draggable="false"
                    @click.stop="toggleNode(node.id)"
                    @dragstart.stop
                >
                    {{ expandedIds.has(node.id) ? '▼' : '▶' }}
                </button>

                <!-- Label -->
                <span class="flex-1 truncate text-gray-800">{{ node.label }}</span>

                <!-- Path hint (monospace, shown on hover) -->
                <span
                    class="hidden max-w-[100px] truncate font-mono text-[10px] text-gray-400 group-hover:block"
                >
                    {{ node.path }}
                </span>

                <!-- Type badge -->
                <span
                    data-testid="field-type-badge"
                    class="rounded px-1 py-0.5 text-[10px] font-medium"
                    :class="typeBadgeClass(node.type)"
                >
                    {{ node.type }}
                </span>
            </div>

            <!-- Children (recursive) -->
            <TreeView
                v-if="hasVisibleChildren(node) && expandedIds.has(node.id)"
                :nodes="node.children"
                :depth="depth + 1"
                :type-badge-class="typeBadgeClass"
                :on-field-drag-start="onFieldDragStart ?? undefined"
                :on-field-drag-end="onFieldDragEnd ?? undefined"
                :on-node-click="onNodeClick ?? undefined"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { DiscoveredField } from '@/types/designer'
import type { TreeNode } from '@/utils/buildFieldTree'

defineOptions({ name: 'TreeView' })

// ── Props ─────────────────────────────────────

const props = defineProps<{
    nodes: TreeNode[]
    /** Current visual depth (0 = root level) */
    depth?: number
    /** Function to map field type → Tailwind badge class */
    typeBadgeClass: (type: string) => string
    /** Called when a draggable node is picked up */
    onFieldDragStart?: (event: DragEvent, field: DiscoveredField) => void
    /** Called when a drag ends */
    onFieldDragEnd?: (event: DragEvent) => void
    /** Called when any selectable (non-object) node is clicked */
    onNodeClick?: (node: TreeNode) => void
}>()

// ── Expand / Collapse state ───────────────────

const expandedIds = ref(new Set<string>())
// Note: all nodes start collapsed. The user expands interactively.

function toggleNode(id: string): void {
    const next = new Set(expandedIds.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    expandedIds.value = next
}

// ── Node predicates ──────────────────────────

function hasVisibleChildren(node: TreeNode): boolean {
    return node.children.length > 0
}

/**
 * Determines whether a node can be dragged onto the canvas.
 * - Primitives (string, number, boolean, null) → draggable
 * - Arrays → draggable
 * - Objects → NOT draggable (only expandable)
 */
function isDraggable(node: TreeNode): boolean {
    return node.type !== 'object'
}

// ── Drag handlers ────────────────────────────

function onNodeDragStart(event: DragEvent, node: TreeNode): void {
    if (!isDraggable(node) || !props.onFieldDragStart) {
        event.preventDefault()
        return
    }
    // Stop propagation so parent nodes don't overwrite the drag data
    event.stopPropagation()
    props.onFieldDragStart(event, node.originalField)
}

function onNodeDragEnd(event: DragEvent): void {
    props.onFieldDragEnd?.(event)
}
</script>
