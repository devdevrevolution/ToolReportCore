// ──────────────────────────────────────────────
// Toolreport Designer — Store Wrapper Composable
// ──────────────────────────────────────────────

import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useDesignerStore } from '@/stores/designer'

/**
 * Convenience wrapper around the designer Pinia store.
 *
 * Returns reactive refs (via `storeToRefs`) for state, computed getters,
 * and bound action functions — no need to call the store directly.
 */
export function useDesigner() {
    const store = useDesignerStore()
    const {
        selectedElementIds,
        page,
        templateId,
        templateName,
        isDirty,
        isLoading,
    } = storeToRefs(store)

    const selectedElementId = computed(() => store.selectedElementId)
    const selectedElement = computed(() => store.selectedElement)
    const selectedElements = computed(() => store.selectedElements)
    const visibleElements = computed(() => store.visibleElements)
    const elementCount = computed(() => store.elementCount)
    const canUndo = computed(() => store.canUndo)
    const canRedo = computed(() => store.canRedo)

    return {
        // State (reactive refs)
        selectedElementIds,
        page,
        templateId,
        templateName,
        isDirty,
        isLoading,

        // Getters
        selectedElementId,
        selectedElement,
        selectedElements,
        visibleElements,
        elementCount,
        canUndo,
        canRedo,

        // Actions (bound)
        addElement: store.addElement,
        removeElement: store.removeElement,
        removeElements: store.removeElements,
        updateElement: store.updateElement,
        selectElement: store.selectElement,
        toggleElementSelection: store.toggleElementSelection,
        selectElements: store.selectElements,
        clearSelection: store.clearSelection,
        moveElement: store.moveElement,
        moveElements: store.moveElements,
        resizeElement: store.resizeElement,
        reorderElement: store.reorderElement,
        duplicateElement: store.duplicateElement,
        loadTemplate: store.loadTemplate,
        saveTemplate: store.saveTemplate,
        reset: store.reset,
        undo: store.undo,
        redo: store.redo,
        pushHistory: store.pushHistory,
    }
}
