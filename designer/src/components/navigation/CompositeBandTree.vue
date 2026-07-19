<template>
    <div data-testid="composite-band-tree">
        <div
            v-for="band in store.page.bands"
            :key="band.id"
            class="composite-band-tree__group"
        >
            <!-- Band header row -->
            <div
                data-testid="band-header"
                class="composite-band-tree__header flex cursor-pointer items-center gap-1 rounded px-2 py-1 text-xs transition-colors"
                :class="bandHeaderClass(band)"
                @click="selectBand(band.id)"
            >
                <!-- Collapse toggle -->
                <button
                    data-testid="band-collapse-toggle"
                    class="inline-flex w-3.5 flex-shrink-0 items-center justify-center text-[10px] text-gray-400 hover:text-gray-600 focus:outline-none"
                    draggable="false"
                    @click.stop="toggleBand(band.id)"
                >
                    {{ collapsed.has(band.id) ? '▶' : '▼' }}
                </button>

                <!-- Band icon -->
                <span :class="band.enabled ? 'text-gray-400' : 'text-gray-300'">⊞</span>

                <!-- Label -->
                <span
                    class="flex-1 truncate font-medium"
                    :class="band.enabled ? 'text-gray-800' : 'text-gray-400 line-through'"
                >
                    {{ band.label }}
                </span>

                <!-- Height -->
                <span :class="band.enabled ? 'text-gray-400' : 'text-gray-300'">
                    <span class="text-[10px]">{{ band.height }}mm</span>
                </span>

                <!-- Enable/disable toggle -->
                <button
                    data-testid="band-enable-toggle"
                    class="rounded px-1 py-0.5 text-xs transition-colors focus:outline-none"
                    :class="band.enabled
                        ? 'text-gray-400 hover:text-green-600 hover:bg-green-50'
                        : 'text-gray-300 hover:text-green-500 hover:bg-green-50'"
                    draggable="false"
                    :title="band.enabled ? 'Disable band' : 'Enable band'"
                    @click.stop="toggleEnabled(band.id)"
                >
                    {{ band.enabled ? '●' : '○' }}
                </button>

                <!-- Root count indicator -->
                <span
                    v-if="(band.children ?? []).length > 0"
                    class="rounded bg-gray-100 px-1 py-0.5 text-[10px] text-gray-500"
                >
                    {{ (band.children ?? []).length }} roots
                </span>

                <span
                    v-else
                    class="text-[10px] text-gray-300 italic"
                >
                    empty
                </span>
            </div>

            <!-- Children: composite roots -->
            <div v-if="!collapsed.has(band.id) && (band.children ?? []).filter(isCompositeRoot).length > 0">
                <template
                    v-for="root in (band.children ?? []).filter(isCompositeRoot)"
                    :key="root.id"
                >
                    <!-- Root row (clickable → selects root) -->
                    <div
                        class="composite-root-row flex items-center gap-1 rounded px-2 py-1 text-xs transition-colors cursor-pointer hover:bg-gray-50"
                        :class="store.selectedCompositeNodeId === root.id ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700'"
                        :style="{ paddingLeft: (24 + 8) + 'px' }"
                        @click.stop="selectRoot(root.id)"
                    >
                        <span class="w-3.5" />
                        <span class="w-4 text-center text-xs">{{ nodeIcon(root.node) }}</span>
                        <span class="flex-1 truncate">{{ nodePreview(root) }}</span>
                        <span class="rounded px-1 py-0.5 text-[10px] font-medium"
                            :class="badgeClass(root.node)"
                        >
                            {{ root.node.type }}
                        </span>
                        <span class="text-[9px] text-gray-400">{{ Math.round(root.x) }},{{ Math.round(root.y) }}</span>
                    </div>

                    <!-- Nested children (VBox/HBox tree) -->
                    <CompositeTreeItem
                        v-for="child in getChildren(root.node)"
                        :key="child.id"
                        :node="child"
                        :depth="1"
                    />
                </template>
            </div>

            <!-- Empty band hint -->
            <div
                v-if="!collapsed.has(band.id) && (band.children ?? []).length === 0"
                class="py-1 text-[10px] italic"
                :class="band.enabled ? 'text-gray-300' : 'text-gray-200'"
                :style="{ paddingLeft: 24 + 8 + 'px' }"
            >
                No content
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useDesignerStore, isCompositeRoot } from '@/stores/designer'
import type { ReportBand, CompositeRoot, CompositeNode, VBoxNode, HBoxNode } from '@/types/designer'
import CompositeTreeItem from './CompositeTreeItem.vue'

const store = useDesignerStore()

// ── Expand / Collapse state ───────────────────
const collapsed = ref(new Set<string>())

function toggleBand(id: string): void {
    const next = new Set(collapsed.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    collapsed.value = next
}

// ── Enable / Disable ──────────────────────────

function toggleEnabled(bandId: string): void {
    const band = store.page.bands?.find(b => b.id === bandId)
    if (!band) return
    store.updateBand(bandId, { enabled: !band.enabled })
}

// ── Selection ─────────────────────────────────
function selectBand(id: string): void {
    store.selectBand(id)
    store.selectCompositeNode(null)
}

function selectRoot(rootId: string): void {
    store.selectCompositeNode(rootId)
}

function bandHeaderClass(band: { id: string; enabled: boolean }): string {
    const base = store.selectedBandId === band.id
        ? 'bg-indigo-100 text-indigo-800'
        : 'hover:bg-gray-100'
    const state = band.enabled ? 'text-gray-700' : 'text-gray-400'
    return `${base} ${state}`
}

// ── Node display helpers ──────────────────────

const NODE_ICONS: Record<string, string> = {
    VBox: '▦',
    HBox: '▥',
    Label: 'T',
    Shape: '◇',
    Table: '⊞',
}

function nodeIcon(node: CompositeNode): string {
    return NODE_ICONS[node.type] ?? '?'
}

function nodePreview(root: CompositeRoot): string {
    const node = root.node
    switch (node.type) {
        case 'VBox':
            return `${node.children.length} children`
        case 'HBox':
            return `${node.children.length} children`
        case 'Label':
            return node.text?.substring(0, 30) || '(empty)'
        case 'Shape': {
            const dims = `${node.w ?? ''}×${node.h ?? ''}`
            const labels: Record<string, string> = { line: 'Line', circle: `Circle ${dims}`, ellipse: `Ellipse ${dims}` }
            return labels[node.shapeType] ?? `Rect ${dims}`
        }
        case 'Table':
            return `Table (${node.rows.length} rows, ${node.columnWidths.length} cols)`
        default:
            return ''
    }
}

const NODE_BADGE_COLORS: Record<string, string> = {
    VBox: 'bg-indigo-100 text-indigo-700',
    HBox: 'bg-purple-100 text-purple-700',
    Label: 'bg-blue-100 text-blue-700',
    Shape: 'bg-amber-100 text-amber-700',
    Table: 'bg-green-100 text-green-700',
}

function badgeClass(node: CompositeNode): string {
    return NODE_BADGE_COLORS[node.type] ?? 'bg-gray-100 text-gray-600'
}

function getChildren(node: CompositeNode): CompositeNode[] {
    if ('children' in node) {
        return (node as VBoxNode | HBoxNode).children
    }
    return []
}
</script>