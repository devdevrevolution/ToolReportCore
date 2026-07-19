<script setup lang="ts">
import { inject, computed, type Ref } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type { CompositeNode } from '../../types/designer'
import { useCompositeScale } from '../../composables/useCompositeScale'
import VBoxNode from '../composite/VBoxNode.vue'
import HBoxNode from '../composite/HBoxNode.vue'
import LabelNode from '../composite/LabelNode.vue'
import ShapeNode from '../composite/ShapeNode.vue'
import TableNode from '../composite/TableNode.vue'
import ImageNode from '../composite/ImageNode.vue'

const props = defineProps<{
    node: CompositeNode
}>()

const store = useDesignerStore()
const { mmToPxStr } = useCompositeScale()

const componentMap: Record<string, any> = {
    VBox: VBoxNode,
    HBox: HBoxNode,
    Label: LabelNode,
    Shape: ShapeNode,
    Table: TableNode,
    Image: ImageNode,
}

export type CompositeParentLayout = 'HBox' | 'VBox' | null

// ── Layout context injection (provide/inject chain) ─────
// HBoxNode provides 'HBox', VBoxNode provides 'VBox', and the canvas
// provides null for root-level nodes. This lets each child know which
// dimension it is allowed to resize and how it should stretch.
const parentLayout = inject<CompositeParentLayout>('compositeParentLayout', null)

// ── Drag-drop handler injection (anidados en canvas) ─────
// Si el renderer está dentro del CompositeCanvas subtree, el canvas
// provee handlers para que los contenedores VBox/HBox sean drop targets.
// Fuera del canvas (árbol, preview) el inject devuelve undefined y
// no se attachan handlers → render directo del componente.
export interface CompositeDropHandlers {
    dragOver: (e: DragEvent, node: CompositeNode) => void
    dragLeave: (node: CompositeNode) => void
    drop: (e: DragEvent, node: CompositeNode) => void
    hoverId: Ref<string | null>
}

export interface CompositeResizeHandle {
    corner: 'n' | 's' | 'e' | 'w'
    dimension: 'width' | 'height'
    direction: 1 | -1
}

export interface CompositeResizeHandlers {
    startResize: (
        e: MouseEvent,
        payload: { nodeId: string; dimension: 'width' | 'height'; direction: 1 | -1 },
    ) => void
}

const dropHandlers = inject<CompositeDropHandlers | undefined>(
    'compositeDropHandlers',
    undefined,
)

const resizeHandlers = inject<CompositeResizeHandlers | undefined>(
    'compositeResizeHandlers',
    undefined,
)

const isContainer = computed(() =>
    props.node.type === 'VBox' || props.node.type === 'HBox'
)

const isHover = computed(() =>
    dropHandlers?.hoverId.value === props.node.id
)

const isNested = computed(() => parentLayout !== null)

const isSelected = computed(() =>
    store.selectedCompositeNodeId === props.node.id
)

function onMouseDown(e: MouseEvent): void {
    // Only nested children are selectable via the renderer. Root-level nodes
    // are selected by the canvas root wrapper, so we let the event bubble up.
    if (!isNested.value) return
    e.stopPropagation()
    store.selectCompositeNode(props.node.id)
}

function startResize(e: MouseEvent, handle: CompositeResizeHandle): void {
    e.stopPropagation()
    store.selectCompositeNode(props.node.id)
    resizeHandlers?.startResize(e, {
        nodeId: props.node.id,
        dimension: handle.dimension,
        direction: handle.direction,
    })
}

const handles = computed<CompositeResizeHandle[]>(() => {
    if (!isNested.value) return []
    if (props.node.type === 'Table') return []
    // When a child has a conflict (overflows its parent), expose all
    // directional handles so the user can manually fix the overflow.
    if (hasConflict.value) {
        return [
            { corner: 'n', dimension: 'height', direction: -1 },
            { corner: 's', dimension: 'height', direction: 1 },
            { corner: 'e', dimension: 'width', direction: 1 },
            { corner: 'w', dimension: 'width', direction: -1 },
        ]
    }

    if (parentLayout === 'VBox') {
        // VBox children: only height (main axis) — width is controlled by VBox stretch
        return [
            { corner: 'n', dimension: 'height', direction: -1 },
            { corner: 's', dimension: 'height', direction: 1 },
        ]
    }

    if (parentLayout === 'HBox') {
        return [
            { corner: 'n', dimension: 'height', direction: -1 },
            { corner: 's', dimension: 'height', direction: 1 },
            { corner: 'e', dimension: 'width', direction: 1 },
            { corner: 'w', dimension: 'width', direction: -1 },
        ]
    }

    return []
})

function handleStyle(corner: CompositeResizeHandle['corner']): Record<string, string> {
    const positions: Record<CompositeResizeHandle['corner'], Record<string, string>> = {
        e: { right: '-4px', top: '50%', marginTop: '-4px', cursor: 'e-resize' },
        w: { left: '-4px', top: '50%', marginTop: '-4px', cursor: 'w-resize' },
        n: { left: '50%', top: '-4px', marginLeft: '-4px', cursor: 'n-resize' },
        s: { left: '50%', bottom: '-4px', marginLeft: '-4px', cursor: 's-resize' },
    }
    return positions[corner]
}

const selectedRingClass = computed(() =>
    isNested.value && isSelected.value ? 'ring-2 ring-indigo-500' : ''
)

const containerHoverClass = computed(() =>
    isHover.value ? 'ring-2 ring-indigo-500 bg-indigo-50/40' : 'ring-1 ring-transparent'
)

const hasConflict = computed(() => isNested.value && store.compositeConflicts.has(props.node.id))

const marginStyle = computed(() => {
    const m = props.node.margin
    if (!m) return undefined
    return {
        marginTop: mmToPxStr(m.top),
        marginRight: mmToPxStr(m.right),
        marginBottom: mmToPxStr(m.bottom),
        marginLeft: mmToPxStr(m.left),
    }
})
</script>

<template>
    <div
        v-if="isContainer && dropHandlers"
        class="relative rounded transition-shadow"
        :class="containerHoverClass"
        :style="marginStyle"
        @dragover.prevent.stop="dropHandlers!.dragOver($event, node)"
        @dragleave.stop="dropHandlers!.dragLeave(node)"
        @drop.prevent.stop="dropHandlers!.drop($event, node)"
    >
        <!-- Drop indicator badge -->
        <div
            v-if="isHover"
            class="pointer-events-none absolute left-1/2 top-0 z-50 -translate-x-1/2 -translate-y-full rounded-md bg-indigo-600 px-2 py-0.5 text-[10px] font-medium text-white whitespace-nowrap shadow-lg"
        >
            Drop into {{ node.type }}
        </div>

        <!-- Content wrapper: selection ring, click selection, resize handles -->
        <div
            class="relative h-full w-full"
            :class="[selectedRingClass, hasConflict ? 'ring-2 ring-red-500 bg-red-50/30' : '']"
            @mousedown="onMouseDown"
        >
            <component :is="componentMap[node.type]" :node="node" :parent-layout="parentLayout" />

            <template v-if="handles.length > 0 && (isSelected || hasConflict)">
                <div
                    v-for="handle in handles"
                    :key="handle.corner"
                    class="absolute z-10 h-2 w-2 border border-indigo-500 bg-white"
                    :style="handleStyle(handle.corner)"
                    @mousedown="startResize($event, handle)"
                />
            </template>
        </div>
    </div>

    <div
        v-else
        class="relative"
        :class="[selectedRingClass, hasConflict ? 'ring-2 ring-red-500 bg-red-50/30' : '']"
        :style="marginStyle"
        @mousedown="onMouseDown"
    >
        <component :is="componentMap[node.type]" :node="node" :parent-layout="parentLayout" />

        <template v-if="handles.length > 0 && (isSelected || hasConflict)">
                <div
                    v-for="handle in handles"
                    :key="handle.corner"
                    class="absolute z-10 h-2 w-2 border border-indigo-500 bg-white"
                    :style="handleStyle(handle.corner)"
                    @mousedown="startResize($event, handle)"
                />
        </template>
    </div>
</template>
