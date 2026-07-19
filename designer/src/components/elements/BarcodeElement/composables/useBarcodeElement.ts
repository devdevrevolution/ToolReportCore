import { computed } from 'vue'
import type { DesignerElement, BarcodeContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function useBarcodeElement(element: DesignerElement) {
  const store = useDesignerStore()

  const content = computed(() => element.content as BarcodeContent)
  const symbology = computed<BarcodeContent['symbology']>(() => content.value.symbology)
  const value = computed<string>(() => content.value.value)
  const variable = computed<string | undefined>(() => content.value.variable)
  const showLabel = computed<boolean>(() => content.value.showLabel)

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  return {
    content,
    symbology,
    value,
    variable,
    showLabel,
    updateContent,
  }
}
