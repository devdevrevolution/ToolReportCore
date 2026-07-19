import { computed } from 'vue'
import type { DesignerElement, RectangleContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function useRectangleElement(element: DesignerElement) {
  const store = useDesignerStore()

  const content = computed(() => element.content as RectangleContent)
  const colorVariable = computed<string | undefined>(() => content.value.colorVariable)
  const hasColorVariable = computed(() => !!content.value.colorVariable)

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  return {
    content,
    colorVariable,
    hasColorVariable,
    updateContent,
  }
}
