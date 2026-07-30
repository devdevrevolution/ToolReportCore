<template>
    <div class="designer-shell h-screen w-screen overflow-hidden">
        <div
            v-if="phase === 'loading'"
            class="flex h-full w-full items-center justify-center bg-gray-50"
        >
            <div class="text-sm text-gray-400">Loading template…</div>
        </div>

        <div
            v-else-if="phase === 'error'"
            class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gray-50"
        >
            <div class="text-base font-semibold text-red-600">Failed to load template</div>
            <div class="text-xs text-gray-500">
                Template #{{ templateId }} could not be fetched.
            </div>
        </div>

        <CompositeDesigner
            v-else-if="phase === 'ready'"
            :api-base-url="apiBaseUrl"
            :template-id="templateId ? Number(templateId) : null"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi, provideApiConfig } from '@/composables/useApi'
import { useDesignerStore } from '@/stores/designer'
import CompositeDesigner from '@/components/layout/CompositeDesigner.vue'

const props = defineProps<{
    templateId: string | null
}>()

const store = useDesignerStore()

const apiBaseUrl = document.querySelector('meta[name="pdf-designer-api-prefix"]')?.getAttribute('content') || '/api/pdf-designer'

const phase = ref<'loading' | 'ready' | 'error'>(props.templateId ? 'loading' : 'ready')

onMounted(async () => {
    if (!props.templateId) return

    phase.value = 'loading'
    try {
        provideApiConfig({ baseURL: apiBaseUrl, timeout: 30_000 })
        const api = useApi()
        const template = await api.getTemplate(Number(props.templateId))
        store.loadTemplate(template)
        phase.value = 'ready'
    } catch (e) {
        console.error('[DesignerShell] Failed to load template:', e)
        phase.value = 'error'
    }
})
</script>
