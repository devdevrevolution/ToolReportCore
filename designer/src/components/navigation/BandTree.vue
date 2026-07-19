<template>
    <div data-testid="band-tree">
        <div
            v-for="band in store.page.bands"
            :key="band.id"
            class="band-tree__group"
        >
            <!-- Band header row -->
            <div
                data-testid="band-header"
                class="band-tree__header flex cursor-pointer items-center gap-1 rounded px-2 py-1 text-xs transition-colors"
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

                <!-- Element count badge -->
                <span
                    v-if="(band.children ?? []).length > 0"
                    class="rounded px-1 py-0.5 text-[10px]"
                    :class="band.enabled
                        ? 'bg-gray-100 text-gray-500'
                        : 'bg-gray-50 text-gray-300'"
                >
                    {{ (band.children ?? []).length }}
                </span>

                <span
                    v-else
                    class="text-[10px] text-gray-300 italic"
                >
                    empty
                </span>
            </div>

            <!-- Children: elements (recursive via ElementTreeItem) -->
            <div v-if="!collapsed.has(band.id) && (band.children ?? []).length > 0">
                <ElementTreeItem
                    v-for="el in (band.children ?? []).filter(isDesignerChild)"
                    :key="el.id"
                    :element="el"
                    :band-id="band.id"
                    :depth="0"
                />
            </div>

            <!-- Empty band hint -->
            <div
                v-if="!collapsed.has(band.id) && (band.children ?? []).length === 0"
                class="py-1 text-[10px] italic"
                :class="band.enabled ? 'text-gray-300' : 'text-gray-200'"
                :style="{ paddingLeft: 24 + 8 + 'px' }"
            >
                No elements
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useDesignerStore, isDesignerChild } from '@/stores/designer'
import ElementTreeItem from './ElementTreeItem.vue'

const store = useDesignerStore()

// ── Expand / Collapse state ───────────────────
// All bands start expanded so elements are visible by default
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
    store.selectElement(null)
}

function bandHeaderClass(band: { id: string; enabled: boolean }): string {
    const base = store.selectedBandId === band.id
        ? 'bg-blue-100 text-blue-800'
        : 'hover:bg-gray-100'
    const state = band.enabled ? 'text-gray-700' : 'text-gray-400'
    return `${base} ${state}`
}
</script>