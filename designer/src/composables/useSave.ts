// ──────────────────────────────────────────────
// Toolreport Designer — Save Composable
// ──────────────────────────────────────────────

import { ref, type Ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import { useApi } from './useApi'
import type { ApiError } from '@/api/types'

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error'

/**
 * Orchestrates saving the current template to the API.
 *
 * - If the store has a `templateId`, calls `updateTemplate`.
 * - Otherwise calls `createTemplate` and sets the returned id in the store.
 */
export function useSave() {
    const store = useDesignerStore()
    const { templateId, templateName } = storeToRefs(store)

    const status: Ref<SaveStatus> = ref('idle')
    const error: Ref<string | null> = ref(null)
    const validationErrors: Ref<Record<string, string[]> | null> = ref(null)

    async function save(): Promise<void> {
        const api = useApi()

        status.value = 'saving'
        error.value = null
        validationErrors.value = null

        try {
            const payload = store.saveTemplate()
            const id = templateId.value
            const isValidId = typeof id === 'number' && id > 0

            if (isValidId) {
                await api.updateTemplate(id, payload)
            } else {
                const created = await api.createTemplate(payload)
                store.templateId = created.id
                store.templateName = created.name
                store.templateSlug = created.slug
                store.templateDescription = created.description

                // Update URL so F5 refreshes on the same template
                const url = new URL(window.location.href)
                url.searchParams.set('template_id', String(created.id))
                window.history.replaceState(null, '', url)
            }

            status.value = 'saved'
        } catch (e: unknown) {
            status.value = 'error'
            if (isApiError(e)) {
                error.value = e.message
                validationErrors.value = e.errors ?? null
            } else {
                error.value = e instanceof Error ? e.message : 'Failed to save template'
            }
        }
    }

    function resetStatus(): void {
        status.value = 'idle'
        error.value = null
        validationErrors.value = null
    }

    return {
        status,
        error,
        validationErrors,
        save,
        resetStatus,
    }
}

function isApiError(e: unknown): e is ApiError {
    return (
        typeof e === 'object' &&
        e !== null &&
        'status' in e &&
        'message' in e
    )
}
