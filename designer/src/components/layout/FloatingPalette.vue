<template>
    <Teleport to="body">
        <div
            v-if="store.paletteDetached"
            ref="paletteEl"
            data-testid="floating-palette"
            class="floating-palette fixed z-50 flex flex-col rounded-lg border border-gray-200 bg-white shadow-lg"
            :style="{
                left: store.palettePosition.x + 'px',
                top: store.palettePosition.y + 'px',
            }"
            @keydown.escape="store.dockPalette()"
        >
            <!-- Title bar (draggable) -->
            <div
                data-testid="floating-palette-header"
                class="flex cursor-grab items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-50 px-2 py-1.5"
                :class="{ 'cursor-grabbing': isDragging }"
                @mousedown.prevent="onHeaderDragStart"
            >
                <span class="select-none text-[10px] font-medium text-gray-500">Components</span>
                <button
                    data-testid="floating-palette-dock"
                    class="rounded p-0.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600"
                    title="Dock back to sidebar"
                    @click="store.dockPalette()"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Icon-only palette -->
            <div class="flex flex-col gap-0.5 p-1.5">
                <button
                    v-for="item in COMPOSITE_ITEMS"
                    :key="item.type"
                    :data-testid="'float-add-' + item.type"
                    draggable="true"
                    class="flex h-8 w-8 items-center justify-center rounded text-sm transition-colors hover:bg-indigo-50 hover:text-indigo-700"
                    :title="item.label"
                    @dragstart="onNodeDragStart($event, item.type)"
                    @click="addNode(item.type)"
                >
                    <span class="text-base">{{ item.icon }}</span>
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import { useDragMove } from '@/composables/useDragMove'
import type { CompositeNodeType } from '@/types/designer'

const store = useDesignerStore()
const paletteEl = ref<HTMLElement | null>(null)

// ── Component items ──────────────────────────────

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

// ── Drag to move ────────────────────────────────

function clampToViewport(x: number, y: number): { x: number; y: number } {
    const el = paletteEl.value
    if (!el) return { x, y }

    const rect = el.getBoundingClientRect()
    const maxX = window.innerWidth - rect.width - 4
    const maxY = window.innerHeight - rect.height - 4

    return {
        x: Math.max(4, Math.min(maxX, x)),
        y: Math.max(4, Math.min(maxY, y)),
    }
}

const { onDragStart, isDragging } = useDragMove({
    apply: (position) => {
        const clamped = clampToViewport(position.x, position.y)
        store.setPalettePosition(clamped)
    },
})

function onHeaderDragStart(e: MouseEvent): void {
    const pos = store.palettePosition
    onDragStart(e, pos.x, pos.y)
}

// ── Palette actions ──────────────────────────────

function addNode(type: CompositeNodeType): void {
    const bandId = store.selectedBandId ?? 'detail'
    store.addCompositeNode(bandId, type)
}

function onNodeDragStart(event: DragEvent, type: CompositeNodeType): void {
    if (!event.dataTransfer) return
    event.dataTransfer.setData('composite-node-type', type)
    event.dataTransfer.effectAllowed = 'copy'
}

// ── Escape to dock ───────────────────────────────

function onKeyDown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && store.paletteDetached) {
        store.dockPalette()
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeyDown)
})

onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown)
})
</script>
