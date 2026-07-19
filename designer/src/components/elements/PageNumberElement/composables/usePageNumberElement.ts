import { computed } from 'vue'
import type { DesignerElement, PageNumberContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function usePageNumberElement(element: DesignerElement) {
  const store = useDesignerStore()

  const content = computed(() => element.content as PageNumberContent)
  const format = computed<string>(() => content.value.format)
  const startAt = computed<number>(() => content.value.startAt)

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  return {
    content,
    format,
    startAt,
    updateContent,
  }
}
