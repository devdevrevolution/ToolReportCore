// ──────────────────────────────────────────────
// Toolreport Designer — Preview Flow Composable
// ──────────────────────────────────────────────

import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import { useApi } from './useApi'

/**
 * Orchestrates the "generate preview" flow:
 * 1. Save the current template (create or update via API)
 * 2. Request a PDF generation
 * 3. Expose the download URL
 */
export function usePreview() {
    const store = useDesignerStore()
    const { templateId, templateName } = storeToRefs(store)

    const isGenerating = ref(false)
    const error = ref<string | null>(null)
    const downloadUrl = ref<string | null>(null)

    async function generate(): Promise<void> {
        const api = useApi()

        isGenerating.value = true
        error.value = null
        downloadUrl.value = null

        try {
            // Template MUST be saved first (caller is responsible for saving)
            const id = templateId.value
            if (id === null || id === undefined) {
                throw new Error('Save the template first before generating a preview')
            }

            // Request PDF generation
            const doc = await api.generatePdf(id, {
                title: `Preview - ${templateName.value}`,
                data: {},
            })

            // Expose the download URL
            downloadUrl.value = api.getDownloadUrl(doc.id)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : 'Preview generation failed'
        } finally {
            isGenerating.value = false
        }
    }

    function reset(): void {
        isGenerating.value = false
        error.value = null
        downloadUrl.value = null
    }

    return {
        isGenerating,
        error,
        downloadUrl,
        generate,
        reset,
    }
}
