import { computed } from 'vue'
import type { DesignerElement, LineContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function useLineElement(element: DesignerElement) {
  const store = useDesignerStore()

  const content = computed(() => element.content as LineContent)
  const orientation = computed<'horizontal' | 'vertical'>(() => content.value.orientation)
  const lineWidth = computed<number>(() => content.value.lineWidth)
  const lineStyle = computed<'solid' | 'dashed' | 'dotted'>(() => content.value.lineStyle)

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  return {
    content,
    orientation,
    lineWidth,
    lineStyle,
    updateContent,
  }
}
