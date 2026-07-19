<template>
    <div
        class="engine-select-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        data-testid="engine-select-modal"
    >
        <div class="engine-select-modal w-full max-w-2xl rounded-2xl bg-white p-8 shadow-2xl">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="mb-2 text-3xl">📄</div>
                <h1 class="text-xl font-bold text-gray-900">Choose a design engine</h1>
                <p class="mt-1 text-sm text-gray-500">
                    The engine determines the layout model. This choice cannot be changed after creating the template.
                </p>
            </div>

            <!-- Engine cards -->
            <div class="grid grid-cols-2 gap-4">
                <!-- DomPDF card -->
                <button
                    data-testid="engine-card-dompdf"
                    class="engine-card rounded-xl border-2 p-6 text-left transition-all"
                    :class="selected === 'dompdf'
                        ? 'border-blue-500 bg-blue-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'"
                    @click="selected = 'dompdf'"
                >
                    <div class="mb-3 text-2xl">🖥</div>
                    <h2 class="mb-1 text-base font-semibold text-gray-900">DomPDF</h2>
                    <p class="text-xs text-gray-500">
                        Absolute positioning with HTML + CSS. Place elements freely using x, y, width, height coordinates.
                        Familiar for anyone used to visual design tools.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] text-gray-600">Drag &amp; Drop</span>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] text-gray-600">Absolute layout</span>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] text-gray-600">HTML + CSS</span>
                    </div>
                    <div
                        v-if="selected === 'dompdf'"
                        class="mt-3 flex items-center gap-1 text-xs font-medium text-blue-600"
                    >
                        ✓ Selected
                    </div>
                </button>

                <!-- PDF Engine card -->
                <button
                    data-testid="engine-card-pdf-engine"
                    class="engine-card rounded-xl border-2 p-6 text-left transition-all"
                    :class="selected === 'pdf-engine'
                        ? 'border-indigo-500 bg-indigo-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'"
                    @click="selected = 'pdf-engine'"
                >
                    <div class="mb-3 text-2xl">🌳</div>
                    <h2 class="mb-1 text-base font-semibold text-gray-900">PDF Engine</h2>
                    <p class="text-xs text-gray-500">
                        Composite tree layout with VBox, HBox, Label, Shape and Table nodes.
                        Flow-based — no manual coordinates needed.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] text-indigo-600">Flow layout</span>
                        <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] text-indigo-600">Composite tree</span>
                        <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] text-indigo-600">tc-lib-pdf</span>
                    </div>
                    <div
                        v-if="selected === 'pdf-engine'"
                        class="mt-3 flex items-center gap-1 text-xs font-medium text-indigo-600"
                    >
                        ✓ Selected
                    </div>
                </button>
            </div>

            <!-- Footer -->
            <div class="mt-8 flex items-center justify-end gap-3">
                <span v-if="!selected" class="text-xs text-gray-400">Select an engine to continue</span>
                <button
                    data-testid="engine-select-confirm"
                    class="rounded-lg px-6 py-2 text-sm font-semibold text-white transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                    :class="selected === 'dompdf' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                    :disabled="!selected"
                    @click="confirm"
                >
                    Open Designer →
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits<{
    (e: 'confirm', engine: 'dompdf' | 'pdf-engine'): void
}>()

const selected = ref<'dompdf' | 'pdf-engine' | null>(null)

function confirm(): void {
    if (!selected.value) return
    emit('confirm', selected.value)
}
</script>
