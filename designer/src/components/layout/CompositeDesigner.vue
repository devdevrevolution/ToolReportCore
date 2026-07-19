<template>
    <div class="composite-designer flex h-full min-h-[600px] flex-col bg-gray-50">
        <!-- Top action bar -->
        <div class="flex items-center gap-3 border-b border-indigo-100 bg-white px-4 py-2">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-900">PDF Designer</h2>
                <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">
                    PDF Engine
                </span>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <!-- Save status -->
                <span v-if="saveStatus === 'saving'" class="text-xs text-gray-400">Saving…</span>
                <span v-else-if="saveStatus === 'saved'" class="text-xs text-green-600">Saved</span>
                <span
                    v-else-if="saveStatus === 'error'"
                    class="max-w-[200px] truncate text-xs text-red-600"
                    :title="saveError ?? ''"
                >
                    {{ saveError }}
                </span>
                <span v-else-if="store.isDirty" class="text-xs text-amber-600">Unsaved changes</span>

                <!-- Save button -->
                <button
                    data-testid="composite-save-button"
                    class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="saveStatus === 'saving' || !store.isDirty"
                    @click="onSave"
                >
                    Save
                </button>

                <!-- Page Setup button -->
                <button
                    data-testid="composite-page-setup-button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="showPageSetup = true"
                >
                    Page Setup
                </button>

                <!-- Preview button -->
                <button
                    data-testid="composite-preview-button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="onPreview"
                >
                    Preview
                </button>
            </div>
        </div>

        <!-- Body: 3 columns -->
        <div class="flex flex-1 overflow-hidden">
            <CompositeToolbar class="flex-shrink-0" />
            <CompositeCanvas class="flex-1" />
            <CompositePropertiesPanel class="flex-shrink-0" />
        </div>

        <!-- Preview modal -->
        <PreviewModal
            v-if="showPreview"
            :download-url="previewUrl"
            :is-generating="isGenerating"
            :error="previewError"
            @close="onClosePreview"
        />

        <!-- Page Setup modal -->
        <PageSetupModal
            v-if="showPageSetup"
            @close="showPageSetup = false"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import { provideApiConfig } from '@/composables/useApi'
import { useSave } from '@/composables/useSave'
import { usePreview } from '@/composables/usePreview'
import { useApi } from '@/composables/useApi'
import type { ApiConfig } from '@/api/types'
import CompositeToolbar from './CompositeToolbar.vue'
import CompositeCanvas from '../canvas/CompositeCanvas.vue'
import CompositePropertiesPanel from './CompositePropertiesPanel.vue'
import PreviewModal from '../modals/PreviewModal.vue'
import PageSetupModal from '../modals/PageSetupModal.vue'

// ── Props ──────────────────────────────────────

const props = withDefaults(defineProps<{
    apiBaseUrl?: string
    authToken?: string
    templateId?: number | null
    timeout?: number
}>(), {
    apiBaseUrl: document.querySelector('meta[name="pdf-designer-api-prefix"]')?.getAttribute('content') || '/api/pdf-designer',
    authToken: undefined,
    templateId: null,
    timeout: 30_000,
})

// ── Store ──────────────────────────────────────

const store = useDesignerStore()

// ── Save ───────────────────────────────────────

const {
    status: saveStatus,
    error: saveError,
    save: doSave,
    resetStatus: resetSaveStatus,
} = useSave()

// ── Preview ────────────────────────────────────

const {
    isGenerating,
    error: previewError,
    downloadUrl: previewUrl,
    generate: doPreview,
    reset: resetPreview,
} = usePreview()

const showPreview = ref(false)
const showPageSetup = ref(false)

// ── Actions ────────────────────────────────────

async function onSave(): Promise<void> {
    await doSave()
    if (saveStatus.value === 'saved') {
        setTimeout(() => resetSaveStatus(), 3000)
    }
}

async function onPreview(): Promise<void> {
    await doSave()
    if (saveStatus.value === 'error') return
    showPreview.value = true
    await doPreview()
}

function onClosePreview(): void {
    showPreview.value = false
    resetPreview()
}

// ── Lifecycle ──────────────────────────────────

onMounted(async () => {
    const apiConfig: ApiConfig = {
        baseURL: props.apiBaseUrl,
        authToken: props.authToken,
        timeout: props.timeout,
    }
    provideApiConfig(apiConfig)

    // Ensure engine is set to pdf-engine
    store.setEngine('pdf-engine')

    if (props.templateId) {
        // Skip fetch if DesignerShell already loaded it
        if (store.templateId === props.templateId && store.page.bands) {
            return
        }
        store.isLoading = true
        try {
            const api = useApi()
            const template = await api.getTemplate(props.templateId)
            store.loadTemplate(template)
        } catch (e) {
            console.error('[CompositeDesigner] Failed to load template:', e)
            store.isLoading = false
        }
    }
})
</script>

<style scoped>
.composite-designer {
    font-family: system-ui, -apple-system, sans-serif;
}
</style>
