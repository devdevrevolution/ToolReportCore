<template>
    <div class="designer-shell h-screen w-screen overflow-hidden">
        <!-- Loading: fetching template to detect engine -->
        <div
            v-if="phase === 'loading'"
            class="flex h-full w-full items-center justify-center bg-gray-50"
        >
            <div class="text-sm text-gray-400">Loading template…</div>
        </div>

        <!-- Error: template could not be loaded -->
        <div
            v-else-if="phase === 'error'"
            class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gray-50"
        >
            <div class="text-base font-semibold text-red-600">Failed to load template</div>
            <div class="text-xs text-gray-500">
                Template #{{ templateId }} could not be fetched.
            </div>
        </div>

        <!-- Edit mode: engine detected from backend → composite -->
        <CompositeDesigner
            v-else-if="phase === 'ready' && templateId && resolvedEngine === 'pdf-engine'"
            :api-base-url="apiBaseUrl"
            :template-id="Number(templateId)"
        />

        <!-- Edit mode: engine detected from backend → dompdf (or null default) -->
        <PdfDesigner
            v-else-if="phase === 'ready' && templateId"
            :api-base-url="apiBaseUrl"
            :template-id="Number(templateId)"
        />

        <!-- New mode: no engine chosen yet → modal -->
        <EngineSelectModal
            v-else-if="!chosenEngine"
            @confirm="onEngineConfirmed"
        />

        <!-- New mode: composite chosen -->
        <CompositeDesigner
            v-else-if="chosenEngine === 'pdf-engine'"
            :api-base-url="apiBaseUrl"
            :template-id="null"
        />

        <!-- New mode: dompdf chosen -->
        <PdfDesigner
            v-else
            :api-base-url="apiBaseUrl"
            :template-id="null"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi, provideApiConfig } from '@/composables/useApi'
import { useDesignerStore } from '@/stores/designer'
import PdfDesigner from '@/components/layout/PdfDesigner.vue'
import CompositeDesigner from '@/components/layout/CompositeDesigner.vue'
import EngineSelectModal from '@/components/modals/EngineSelectModal.vue'

const props = defineProps<{
    /** Route param — string because route params are strings. null for new mode. */
    templateId: string | null
}>()

const store = useDesignerStore()

const apiBaseUrl = document.querySelector('meta[name="pdf-designer-api-prefix"]')?.getAttribute('content') || '/api/pdf-designer'

// Start in 'loading' when a templateId is supplied (edit mode) so the
// first paint shows the Loading screen instead of a designer whose
// engine is still unknown. New mode starts 'ready' to show the modal.
const phase = ref<'loading' | 'ready' | 'error'>(props.templateId ? 'loading' : 'ready')
const resolvedEngine = ref<'dompdf' | 'pdf-engine' | null>(null)
const chosenEngine = ref<'dompdf' | 'pdf-engine' | null>(null)

onMounted(async () => {
    if (!props.templateId) return

    // Hold the loading state until the template engine is resolved,
    // so the first paint in edit mode shows the Loading screen rather
    // than a designer component with an undetermined engine.
    phase.value = 'loading'
    try {
        // Initialise the singleton API client before any useApi() call.
        provideApiConfig({ baseURL: apiBaseUrl, timeout: 30_000 })
        const api = useApi()
        const template = await api.getTemplate(Number(props.templateId))
        // null/undefined engine defaults to dompdf.
        resolvedEngine.value = template.engine === 'pdf-engine' ? 'pdf-engine' : 'dompdf'
        // Load into store so PdfDesigner/CompositeDesigner don't refetch
        store.loadTemplate(template)
        phase.value = 'ready'
    } catch (e) {
        console.error('[DesignerShell] Failed to load template:', e)
        phase.value = 'error'
    }
})

function onEngineConfirmed(engine: 'dompdf' | 'pdf-engine'): void {
    chosenEngine.value = engine
    store.setEngine(engine)
}
</script>