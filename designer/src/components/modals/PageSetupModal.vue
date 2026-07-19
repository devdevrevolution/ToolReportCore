<template>
    <Teleport to="body">
        <div
            class="page-setup-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="emit('close')"
            @keydown.escape="emit('close')"
        >
            <div
                class="relative mx-4 flex w-full max-w-lg flex-col rounded-lg bg-white shadow-2xl"
                style="max-height: 85vh;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Page Setup</h3>
                    <button
                        class="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-auto p-4">
                    <!-- Paper size -->
                    <div class="mb-4">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Paper Size</label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                v-for="name in PAPER_SIZE_NAMES"
                                :key="name"
                                class="rounded border px-2 py-1.5 text-xs transition-colors"
                                :class="paperSize === name
                                    ? 'border-blue-400 bg-blue-50 text-blue-700 font-medium'
                                    : 'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'"
                                @click="selectPreset(name)"
                            >
                                {{ name }}
                            </button>
                            <button
                                class="rounded border px-2 py-1.5 text-xs transition-colors"
                                :class="paperSize === null || !PAPER_SIZES[paperSize]
                                    ? 'border-blue-400 bg-blue-50 text-blue-700 font-medium'
                                    : 'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'"
                                @click="selectCustom"
                            >
                                Custom
                            </button>
                        </div>
                    </div>

                    <!-- Dimensions and orientation -->
                    <div class="mb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-medium text-gray-700">Dimensions</label>
                            <div class="flex gap-1">
                                <button
                                    class="rounded border px-2 py-1 text-[10px] transition-colors"
                                    :class="orientation === 'portrait'
                                        ? 'border-blue-400 bg-blue-50 text-blue-700 font-medium'
                                        : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                                    @click="orientation = 'portrait'"
                                >
                                    Portrait
                                </button>
                                <button
                                    class="rounded border px-2 py-1 text-[10px] transition-colors"
                                    :class="orientation === 'landscape'
                                        ? 'border-blue-400 bg-blue-50 text-blue-700 font-medium'
                                        : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                                    @click="orientation = 'landscape'"
                                >
                                    Landscape
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-xs text-gray-500">
                                Width (mm)
                                <input
                                    type="number"
                                    :value="width"
                                    :disabled="isPreset"
                                    min="50"
                                    step="0.1"
                                    class="input mt-0.5"
                                    :class="{ 'opacity-50': isPreset }"
                                    @input="onWidthInput"
                                />
                            </label>
                            <label class="block text-xs text-gray-500">
                                Height (mm)
                                <input
                                    type="number"
                                    :value="height"
                                    :disabled="isPreset"
                                    min="50"
                                    step="0.1"
                                    class="input mt-0.5"
                                    :class="{ 'opacity-50': isPreset }"
                                    @input="onHeightInput"
                                />
                            </label>
                        </div>
                        <p class="mt-1 text-[10px] text-gray-400">
                            {{ width }} × {{ height }} mm
                        </p>
                    </div>

                    <!-- Mini preview -->
                    <div class="mb-4 flex justify-center">
                        <div
                            class="rounded border border-gray-300 bg-white"
                            :style="miniPreviewStyle"
                        />
                        <span class="sr-only">Page preview: {{ width }} × {{ height }} mm</span>
                    </div>

                    <!-- Margins -->
                    <div class="mb-2">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Margins (mm)</label>
                        <div class="grid grid-cols-4 gap-2">
                            <label class="block text-xs text-gray-500">
                                Top
                                <input
                                    type="number"
                                    :value="margins.top"
                                    min="0"
                                    step="0.5"
                                    class="input mt-0.5"
                                    @input="setMargin('top', $event)"
                                />
                            </label>
                            <label class="block text-xs text-gray-500">
                                Right
                                <input
                                    type="number"
                                    :value="margins.right"
                                    min="0"
                                    step="0.5"
                                    class="input mt-0.5"
                                    @input="setMargin('right', $event)"
                                />
                            </label>
                            <label class="block text-xs text-gray-500">
                                Bottom
                                <input
                                    type="number"
                                    :value="margins.bottom"
                                    min="0"
                                    step="0.5"
                                    class="input mt-0.5"
                                    @input="setMargin('bottom', $event)"
                                />
                            </label>
                            <label class="block text-xs text-gray-500">
                                Left
                                <input
                                    type="number"
                                    :value="margins.left"
                                    min="0"
                                    step="0.5"
                                    class="input mt-0.5"
                                    @input="setMargin('left', $event)"
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3">
                    <button
                        class="rounded border border-gray-300 px-4 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        class="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                        @click="onApply"
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import { PAPER_SIZES, PAPER_SIZE_NAMES } from '@/stores/designer'

const store = useDesignerStore()

const emit = defineEmits<{
    close: []
}>()

// ── Local state (initialised from store) ────

const paperSize = ref<string | null>(store.page.paperSize)
const width = ref(store.page.width)
const height = ref(store.page.height)
const orientation = ref(store.page.orientation)
const margins = ref({ ...store.page.margin })

// ── Derived ─────────────────────────────────

const isPreset = computed(() => paperSize.value !== null && !!PAPER_SIZES[paperSize.value])

/** Preview box: at most 120px wide, proportional height */
const miniPreviewStyle = computed(() => {
    const maxW = 120
    const ratio = height.value / width.value
    const w = Math.min(maxW, width.value)
    const h = w * ratio
    return {
        width: `${w}px`,
        height: `${h}px`,
    }
})

// ── Paper size handlers ─────────────────────

function selectPreset(name: string): void {
    paperSize.value = name
    const preset = PAPER_SIZES[name]
    let w = preset.width
    let h = preset.height
    if (orientation.value === 'landscape') {
        ;[w, h] = [h, w]
    }
    width.value = w
    height.value = h
}

function selectCustom(): void {
    paperSize.value = null
}

// ── Dimension handlers ──────────────────────

function onWidthInput(e: Event): void {
    const val = parseFloat((e.target as HTMLInputElement).value)
    if (!isNaN(val) && val >= 50) {
        width.value = val
        paperSize.value = null
    }
}

function onHeightInput(e: Event): void {
    const val = parseFloat((e.target as HTMLInputElement).value)
    if (!isNaN(val) && val >= 50) {
        height.value = val
        paperSize.value = null
    }
}

// ── Margin handler ──────────────────────────

function setMargin(side: 'top' | 'right' | 'bottom' | 'left', e: Event): void {
    const val = parseFloat((e.target as HTMLInputElement).value)
    if (!isNaN(val) && val >= 0) {
        margins.value = { ...margins.value, [side]: val }
    }
}

// ── Apply ───────────────────────────────────

function onApply(): void {
    // Apply margins
    const sides: Array<'top' | 'right' | 'bottom' | 'left'> = ['top', 'right', 'bottom', 'left']
    for (const side of sides) {
        store.setMargin(side, margins.value[side])
    }

    // Apply page dimensions
    store.setPageDimensions({
        width: width.value,
        height: height.value,
        orientation: orientation.value,
        paperSize: paperSize.value,
    })

    emit('close')
}
</script>

<style scoped>
.input {
    display: block;
    width: 100%;
    border-radius: 0.25rem;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1rem;
}
.input:focus {
    border-color: #3b82f6;
    outline: 1px solid #3b82f6;
    outline-offset: -1px;
}
</style>
