<template>
    <Teleport to="body">
        <div
            class="pdf-preview-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="$emit('close')"
            @keydown.escape="$emit('close')"
        >
            <div
                class="relative mx-4 flex w-full max-w-4xl flex-col rounded-lg bg-white shadow-2xl"
                style="max-height: 85vh;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">PDF Preview</h3>
                    <button
                        class="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                        @click="$emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-auto p-4">
                    <!-- Loading state -->
                    <div
                        v-if="isGenerating"
                        class="flex flex-col items-center justify-center py-20 text-gray-400"
                    >
                        <svg class="pdf-preview-spinner mb-4 h-10 w-10 animate-spin" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <p class="text-sm">Generating PDF…</p>
                    </div>

                    <!-- Error state -->
                    <div
                        v-else-if="error"
                        class="flex flex-col items-center justify-center py-20"
                    >
                        <p class="mb-2 text-lg">⚠️</p>
                        <p class="text-sm text-red-600">{{ error }}</p>
                        <button
                            class="mt-4 rounded border border-gray-300 px-4 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            @click="$emit('close')"
                        >
                            Close
                        </button>
                    </div>

                    <!-- Iframe preview -->
                    <iframe
                        v-else-if="downloadUrl"
                        :src="downloadUrl"
                        class="pdf-preview-iframe h-full w-full rounded border-0"
                        style="min-height: 70vh;"
                    />
                </div>

                <!-- Footer (only when loaded) -->
                <div
                    v-if="downloadUrl && !isGenerating"
                    class="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3"
                >
                    <a
                        :href="downloadUrl"
                        download
                        class="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                    >
                        Download PDF
                    </a>
                    <button
                        class="rounded border border-gray-300 px-4 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="$emit('close')"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
defineProps<{
    downloadUrl: string | null
    isGenerating: boolean
    error: string | null
}>()

defineEmits<{
    close: []
}>()
</script>

<style scoped>
@keyframes pdf-preview-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.pdf-preview-spinner {
    animation: pdf-preview-spin 1s linear infinite;
}
</style>
