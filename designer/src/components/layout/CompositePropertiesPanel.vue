<template>
    <div
        data-testid="composite-properties-panel"
        class="w-[240px] flex-shrink-0 overflow-y-auto border-l border-gray-200 bg-white"
    >
        <!-- Root container selected (VBox/HBox at band root) -->
        <template v-if="store.selectedCompositeRoot">
            <!-- Header -->
            <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                <span class="text-base">{{ rootIcon }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ rootType }}</span>
                <span class="ml-auto rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">Root</span>
                <span class="font-mono text-[10px] text-gray-400">
                    {{ store.selectedCompositeRoot!.id.slice(0, 8) }}
                </span>
            </div>

            <!-- Position (disabled for VBox/HBox) -->
            <PropertyGroup title="Position" collapsible>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">X (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
                            :value="store.selectedCompositeRoot!.x"
                            min="0"
                            :disabled="isContainerRoot"
                            @change="updateRoot({ x: Number(($event.target as HTMLInputElement).value) })"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Y (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
                            :value="store.selectedCompositeRoot!.y"
                            min="0"
                            :disabled="isContainerRoot"
                            @change="updateRoot({ y: Number(($event.target as HTMLInputElement).value) })"
                        />
                    </div>
                </div>
            </PropertyGroup>

            <!-- Margin (root inner node) -->
            <PropertyGroup title="Margin" collapsible>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Top (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            :value="rootMargin.top"
                            min="0"
                            @change="updateRootMargin('top', Number(($event.target as HTMLInputElement).value))"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Right (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            :value="rootMargin.right"
                            min="0"
                            @change="updateRootMargin('right', Number(($event.target as HTMLInputElement).value))"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Bottom (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            :value="rootMargin.bottom"
                            min="0"
                            @change="updateRootMargin('bottom', Number(($event.target as HTMLInputElement).value))"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Left (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            :value="rootMargin.left"
                            min="0"
                            @change="updateRootMargin('left', Number(($event.target as HTMLInputElement).value))"
                        />
                    </div>
                </div>
            </PropertyGroup>

            <!-- Dimensions (root wrapper width/height — controls layout space) -->
            <PropertyGroup
                v-if="rootType !== 'Label'"
                title="Dimensions"
                collapsible
            >
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Width (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            :value="store.selectedCompositeRoot!.width ?? ''"
                            min="0"
                            @change="updateRoot({ width: parseDimension(($event.target as HTMLInputElement).value) })"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Height (mm)</label>
                        <input
                            type="number"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            :value="store.selectedCompositeRoot!.height ?? ''"
                            min="0"
                            @change="updateRoot({ height: parseDimension(($event.target as HTMLInputElement).value) })"
                        />
                    </div>
                </div>
            </PropertyGroup>

            <!-- Type-specific properties (dynamic component) -->
            <component
                :is="propertyComponent"
                v-if="propertyComponent"
                :key="store.selectedCompositeInnerNode!.id"
                :node="store.selectedCompositeInnerNode!"
                :parent="imageParent"
                @update="onPropertyUpdate"
            />

            <!-- Conflict warning + Fit children -->
            <PropertyGroup
                v-if="isContainerRoot && hasRootConflicts"
                title="Conflict"
                collapsible
            >
                <div class="flex items-start gap-2 rounded-md bg-amber-50 px-3 py-2">
                    <span class="mt-0.5 text-sm text-amber-500">⚠</span>
                    <div class="flex-1">
                        <div class="text-xs font-medium text-amber-800">Dimension conflict</div>
                        <div class="mt-0.5 text-[11px] text-amber-600">
                            Some children exceed the container dimensions.
                        </div>
                    </div>
                </div>
                <button
                    class="w-full rounded bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-200"
                    @click="store.fitCompositeChildren(store.selectedCompositeInnerNode!.id)"
                >
                    Fit children
                </button>
            </PropertyGroup>

            <!-- Remove button -->
            <div class="border-t border-gray-100 px-4 py-3">
                <button
                    class="w-full rounded bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                    @click="store.removeCompositeNode(store.selectedCompositeRoot!.id)"
                >
                    Remove
                </button>
            </div>
        </template>

        <!-- Inner node selected (nested child) -->
        <template v-else-if="store.selectedCompositeInnerNode">
            <!-- Header -->
            <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                <span class="text-base">{{ innerIcon }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ store.selectedCompositeInnerNode.type }}</span>
                <span class="ml-auto font-mono text-[10px] text-gray-400">
                    {{ store.selectedCompositeInnerNode.id.slice(0, 8) }}
                </span>
            </div>

            <!-- Type-specific properties (dynamic component) -->
            <component
                :is="propertyComponent"
                v-if="propertyComponent"
                :key="store.selectedCompositeInnerNode.id"
                :node="store.selectedCompositeInnerNode"
                :parent="imageParent"
                @update="onPropertyUpdate"
            />

            <!-- Remove button -->
            <div class="border-t border-gray-100 px-4 py-3">
                <button
                    class="w-full rounded bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                    @click="store.removeCompositeNode(store.selectedCompositeInnerNode!.id)"
                >
                    Remove
                </button>
            </div>
        </template>

        <!-- Band selected -->
        <template v-else-if="store.selectedBand">
            <div class="border-b border-gray-200 px-4 py-3">
                <span class="text-xs font-semibold text-gray-700">{{ store.selectedBand.label }}</span>
            </div>
            <PropertyGroup title="Band" collapsible>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Height (mm)</label>
                    <input
                        type="number"
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                        :value="store.selectedBand.height"
                        min="0"
                        @change="store.setBandHeight(store.selectedBand!.id, Number(($event.target as HTMLInputElement).value))"
                    />
                </div>
                <!-- Collection Path (detail bands only) -->
                <div v-if="store.selectedBand.type === 'detail'">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Collection Path</label>
                    <input
                        type="text"
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                        placeholder="e.g. results, items, data.orders"
                        :value="store.selectedBand.collectionPath ?? ''"
                        @change="store.updateBand(store.selectedBand!.id, { collectionPath: ($event.target as HTMLInputElement).value || null })"
                    />
                    <p class="mt-1 text-[10px] text-gray-400">
                        Array field to iterate. Leave empty for static content.
                    </p>
                </div>
                <!-- Summary Position (summary bands only) -->
                <div v-if="store.selectedBand.type === 'summary'">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Summary Position</label>
                    <select
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                        :value="store.selectedBand.summaryPosition ?? 'afterDetail'"
                        @change="store.updateBand(store.selectedBand!.id, { summaryPosition: ($event.target as HTMLSelectElement).value as 'afterDetail' | 'pageBottom' })"
                    >
                        <option value="afterDetail">After Detail</option>
                        <option value="pageBottom">Page Bottom (iReport)</option>
                    </select>
                    <p class="mt-1 text-[10px] text-gray-400">
                        Where to render the summary on the last page.
                    </p>
                </div>
            </PropertyGroup>
        </template>

        <!-- Nothing selected -->
        <div v-else class="flex items-center justify-center p-6 text-center text-xs text-gray-400">
            Select a band or node
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type { CompositeNode } from '@/types/designer'
import { usePropertyHelpers } from '@/composables/usePropertyHelpers'
import PropertyGroup from './PropertyGroup.vue'
import LabelProperty from './properties/LabelProperty.vue'
import ShapeProperty from './properties/ShapeProperty.vue'
import ImageProperty from './properties/ImageProperty.vue'
import VBoxProperty from './properties/VBoxProperty.vue'
import HBoxProperty from './properties/HBoxProperty.vue'
import TableProperty from './properties/TableProperty.vue'

const store = useDesignerStore()
const { parseDimension } = usePropertyHelpers()

const NODE_ICONS: Record<string, string> = {
    VBox: '▦',
    HBox: '▥',
    Label: 'T',
    Shape: '◇',
    Table: '⊞',
    Image: '🖼',
}

// ── Type-specific property component resolver ──

const PROPERTY_COMPONENTS: Record<string, any> = {
    Label: LabelProperty,
    Shape: ShapeProperty,
    Image: ImageProperty,
    VBox: VBoxProperty,
    HBox: HBoxProperty,
    Table: TableProperty,
}

const propertyComponent = computed(() => {
    const type = store.selectedCompositeInnerNode?.type
    return type ? PROPERTY_COMPONENTS[type] ?? null : null
})

// ── Root-level helpers ──

const rootType = computed(() => store.selectedCompositeInnerNode?.type ?? '?')
const rootIcon = computed(() => NODE_ICONS[rootType.value] ?? '?')
const isContainerRoot = computed(() => rootType.value === 'VBox' || rootType.value === 'HBox')

const hasRootConflicts = computed(() => {
    if (!store.selectedCompositeInnerNode) return false
    const id = store.selectedCompositeInnerNode.id
    const conflicts = store.compositeConflicts
    if (conflicts.has(id)) return true
    const hasChildren = 'children' in store.selectedCompositeInnerNode && store.selectedCompositeInnerNode.children?.length
    return hasChildren && conflicts.size > 0
})

// ── Inner node helpers ──

const innerIcon = computed(() =>
    store.selectedCompositeInnerNode ? (NODE_ICONS[store.selectedCompositeInnerNode.type] ?? '?') : '',
)

interface CompositeParent {
    layout: 'VBox' | 'HBox'
    width: number
    height: number
}

/** Detect parent container of the selected Image node (supports nested containers). */
const imageParent = computed<CompositeParent | null>(() => {
    if (store.selectedCompositeInnerNode?.type !== 'Image') return null
    const selectedId = store.selectedCompositeInnerNode.id

    const bands = (store.page as { bands?: Array<{ children?: Array<{ node?: CompositeNode }> }> }).bands ?? []
    for (const band of bands) {
        if (!band.children) continue
        for (const root of band.children) {
            const parent = findParentContainer(root.node, selectedId)
            if (parent && (parent.type === 'VBox' || parent.type === 'HBox')) {
                return {
                    layout: parent.type,
                    width: parent.width ?? 0,
                    height: parent.height ?? 0,
                }
            }
        }
    }
    return null
})

function findParentContainer(node: CompositeNode | undefined, targetId: string): CompositeNode | null {
    if (!node || !('children' in node) || !node.children) return null
    for (const child of node.children) {
        if (child.id === targetId) return node
        const found = findParentContainer(child, targetId)
        if (found) return found
    }
    return null
}

// ── Mutations ──

function updateRoot(patch: Record<string, number | undefined>): void {
    if (!store.selectedCompositeRoot) return
    store.updateCompositeNode(store.selectedCompositeRoot.id, patch as Partial<CompositeNode>)
}

const rootMargin = computed(() => {
    const n = store.selectedCompositeInnerNode as { margin?: { top: number; right: number; bottom: number; left: number } } | undefined
    return { top: n?.margin?.top ?? 0, right: n?.margin?.right ?? 0, bottom: n?.margin?.bottom ?? 0, left: n?.margin?.left ?? 0 }
})

function updateRootMargin(side: 'top' | 'right' | 'bottom' | 'left', value: number): void {
    if (!store.selectedCompositeInnerNode) return
    const current = rootMargin.value
    updateNode({ margin: { ...current, [side]: Math.max(0, value) } } as Partial<CompositeNode>)
}

function updateNode(patch: Partial<CompositeNode>): void {
    if (!store.selectedCompositeInnerNode) return
    store.updateCompositeNode(store.selectedCompositeInnerNode.id, patch)
}

function onPropertyUpdate(nodeId: string, patch: Partial<CompositeNode>): void {
    store.updateCompositeNode(nodeId, patch)
}
</script>
