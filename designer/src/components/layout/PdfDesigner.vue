<template>
    <div class="pdf-designer flex h-full min-h-[600px] flex-col bg-gray-50">
        <!-- Top action bar -->
        <div class="flex items-center gap-3 border-b border-gray-200 bg-white px-4 py-2">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-900">PDF Designer</h2>
                <span
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500"
                >
                    v0.1.0
                </span>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <!-- Save indicator -->
                <span
                    v-if="saveStatus === 'saving'"
                    class="text-xs text-gray-400"
                >
                    Saving…
                </span>
                <span
                    v-else-if="saveStatus === 'saved'"
                    class="text-xs text-green-600"
                >
                    Saved
                </span>
                <span
                    v-else-if="saveStatus === 'error'"
                    class="max-w-[200px] truncate text-xs text-red-600"
                    :title="saveError ?? ''"
                >
                    {{ saveError }}
                </span>
                <span
                    v-else-if="store.isDirty"
                    class="text-xs text-amber-600"
                >
                    Unsaved changes
                </span>

                <!-- Save button -->
                <button
                    data-testid="save-button"
                    class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                    :disabled="saveStatus === 'saving' || !store.isDirty"
                    @click="onSave"
                >
                    Save
                </button>

                <!-- Page Setup button -->
                <button
                    data-testid="page-setup-button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="showPageSetup = true"
                >
                    Page Setup
                </button>

                <!-- Preview button -->
                <button
                    data-testid="preview-button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="onPreview"
                >
                    Preview
                </button>

            </div>
        </div>

        <!-- Designer body (3 columns) -->
        <div class="flex flex-1 overflow-hidden">
            <DesignerToolbar class="flex-shrink-0" />
            <DesignerCanvas class="flex-1" />
            <PropertiesPanel class="flex-shrink-0" />
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
import DesignerToolbar from './DesignerToolbar.vue'
import DesignerCanvas from './DesignerCanvas.vue'
import PropertiesPanel from './PropertiesPanel.vue'
import PreviewModal from '../modals/PreviewModal.vue'
import PageSetupModal from '../modals/PageSetupModal.vue'
import { useApi } from '@/composables/useApi'
import type { ApiConfig } from '@/api/types'

// ── Props ──────────────────────────────────────

const props = withDefaults(defineProps<{
    /** Base URL for the core API (e.g. /api/pdf-designer) */
    apiBaseUrl?: string
    /** Optional auth token for API requests */
    authToken?: string
    /** Optional existing template ID to load on mount */
    templateId?: number | null
    /** Axios request timeout */
    timeout?: number
}>(), {
    apiBaseUrl: document.querySelector('meta[name="pdf-designer-api-prefix"]')?.getAttribute('content') || '/api/pdf-designer',
    authToken: undefined,
    templateId: null,
    timeout: 30_000,
})

// ── Store ──────────────────────────────────────

const store = useDesignerStore()

// ── Save composable ────────────────────────────

const {
    status: saveStatus,
    error: saveError,
    save: doSave,
    resetStatus: resetSaveStatus,
} = useSave()

// ── Preview composable ─────────────────────────

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
    // Auto-clear saved status after 3 s
    if (saveStatus.value === 'saved') {
        setTimeout(() => resetSaveStatus(), 3000)
    }
}

async function onPreview(): Promise<void> {
    // Save first, then preview
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
    // 1. Initialize API client
    const apiConfig: ApiConfig = {
        baseURL: props.apiBaseUrl,
        authToken: props.authToken,
        timeout: props.timeout,
    }
    provideApiConfig(apiConfig)

    // 2. Load template if id provided
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
            console.error('[PdfDesigner] Failed to load template:', e)
            store.isLoading = false
        }
    }
})
</script>

<style scoped>
.pdf-designer {
    font-family: system-ui, -apple-system, sans-serif;
}
</style>
