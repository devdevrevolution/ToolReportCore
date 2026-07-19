<template>
    <div>
        <!-- Node row -->
        <div
            class="flex items-center gap-1 rounded px-2 py-1 text-xs transition-colors"
            :class="rowClasses"
            :style="{ paddingLeft: (24 + 8 + depth * 16) + 'px' }"
            @click.stop="handleSelect"
        >
            <!-- Expand/collapse toggle (container nodes only) -->
            <button
                v-if="isContainer"
                class="inline-flex w-3.5 shrink-0 items-center justify-center text-[10px] text-gray-400 hover:text-gray-600 focus:outline-none"
                @click.stop="toggleCollapse"
            >
                {{ isCollapsed ? '▶' : '▼' }}
            </button>
            <span v-else class="w-3.5" />

            <!-- Type icon -->
            <span class="w-4 text-center text-xs">{{ nodeIcon }}</span>

            <!-- Preview -->
            <span class="flex-1 truncate text-gray-700">
                {{ nodePreview }}
            </span>

            <!-- Type badge -->
            <span
                class="rounded px-1 py-0.5 text-[10px] font-medium"
                :class="badgeClass"
            >
                {{ node.type }}
            </span>

            <!-- Children count (container nodes only) -->
            <span
                v-if="isContainer && nodeChildren.length > 0"
                class="rounded bg-gray-100 px-1 py-0.5 text-[10px] text-gray-500"
            >
                {{ nodeChildren.length }}
            </span>
        </div>

        <!-- Recursive children -->
        <div v-if="showChildren">
            <CompositeTreeItem
                v-for="child in nodeChildren"
                :key="child.id"
                :node="child"
                :depth="depth + 1"
            />
        </div>

        <!-- Table rows (Table node only) -->
        <div v-if="showTableChildren">
            <div
                v-for="row in tableRows"
                :key="row.id"
                class="flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-500"
                :style="{ paddingLeft: (24 + 8 + (depth + 1) * 16) + 'px' }"
            >
                <span class="w-3.5" />
                <span class="w-4 text-center text-[10px]">↕</span>
                <span class="flex-1 truncate">
                    Row {{ tableRows.indexOf(row) + 1 }} ({{ row.cells.length }} cells)
                </span>
                <span class="rounded bg-gray-100 px-1 py-0.5 text-[10px] font-medium text-gray-500">
                    TableRow
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type { CompositeNode, VBoxNode, HBoxNode, TableNode } from '@/types/designer'

const props = defineProps<{
    node: CompositeNode
    depth?: number
}>()

const depth = computed(() => props.depth ?? 0)
const store = useDesignerStore()

// ── Expand / Collapse ──────────────────────────

const collapsed = ref(new Set<string>())

const isCollapsed = computed(() => collapsed.value.has(props.node.id))

function toggleCollapse(): void {
    const next = new Set(collapsed.value)
    if (next.has(props.node.id)) {
        next.delete(props.node.id)
    } else {
        next.add(props.node.id)
    }
    collapsed.value = next
}

// ── Node type helpers ──────────────────────────

const isContainer = computed(() =>
    props.node.type === 'VBox' || props.node.type === 'HBox',
)

const nodeChildren = computed<CompositeNode[]>(() => {
    if ('children' in props.node) {
        return (props.node as VBoxNode | HBoxNode).children
    }
    return []
})

const showChildren = computed(() =>
    isContainer.value
    && nodeChildren.value.length > 0
    && !isCollapsed.value,
)

const isTable = computed(() => props.node.type === 'Table')

const tableRows = computed(() => {
    if (props.node.type === 'Table') {
        return (props.node as TableNode).rows
    }
    return []
})

const showTableChildren = computed(() =>
    isTable.value
    && tableRows.value.length > 0
    && !isCollapsed.value,
)

// ── Selection ──────────────────────────────────

function handleSelect(): void {
    store.selectCompositeNode(props.node.id)
}

const isSelected = computed(() =>
    store.selectedCompositeNodeId === props.node.id,
)

// ── Row styling ────────────────────────────────

const rowClasses = computed(() => {
    const classes: string[] = []
    if (isSelected.value) {
        classes.push('bg-blue-50', 'text-blue-700', 'cursor-pointer')
    } else {
        classes.push('hover:bg-gray-50', 'cursor-pointer')
    }
    return classes.join(' ')
})

// ── Node display helpers ───────────────────────

const NODE_ICONS: Record<string, string> = {
    VBox: '▦',
    HBox: '▥',
    Label: 'T',
    Shape: '◇',
    Table: '⊞',
}

const nodeIcon = computed(() => NODE_ICONS[props.node.type] ?? '?')

const nodePreview = computed(() => {
    switch (props.node.type) {
        case 'VBox': {
            const n = props.node as VBoxNode
            return `${n.children.length} ${n.children.length === 1 ? 'child' : 'children'}`
        }
        case 'HBox': {
            const n = props.node as HBoxNode
            return `${n.children.length} ${n.children.length === 1 ? 'child' : 'children'}${n.width ? ` — ${n.width}mm` : ''}`
        }
        case 'Label': {
            const n = props.node as import('@/types/designer').LabelNode
            return n.text?.substring(0, 30) || '(empty)'
        }
        case 'Shape': {
            const n = props.node as import('@/types/designer').ShapeNode
            const dims = n.w ? ` ${n.w}×${n.h}` : ''
            const labels: Record<string, string> = { line: 'Line', circle: `Circle${dims}`, ellipse: `Ellipse${dims}` }
            return labels[n.shapeType] ?? `Rect${dims}`
        }
        case 'Table': {
            const n = props.node as TableNode
            return `Table (${n.rows.length} rows, ${n.columnWidths.length} cols)`
        }
        default:
            return ''
    }
})

const NODE_BADGE_COLORS: Record<string, string> = {
    VBox: 'bg-indigo-100 text-indigo-700',
    HBox: 'bg-purple-100 text-purple-700',
    Label: 'bg-blue-100 text-blue-700',
    Shape: 'bg-amber-100 text-amber-700',
    Table: 'bg-green-100 text-green-700',
}

const badgeClass = computed(() =>
    NODE_BADGE_COLORS[props.node.type] ?? 'bg-gray-100 text-gray-600',
)
</script>
