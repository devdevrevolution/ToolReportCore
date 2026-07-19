import { computed } from 'vue'
import type { DesignerElement, ContainerContent, ElementType } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function useContainerElement(element: DesignerElement) {
  const store = useDesignerStore()

  const content = computed(() => element.content as ContainerContent)
  const children = computed(() => {
    if (element.type !== 'container') return []
    return (element.content as ContainerContent).children ?? []
  })
  const isContainer = computed(() => element.type === 'container')
  const isEmpty = computed(() => children.value.length === 0)
  const layout = computed(() => content.value.layout)
  const gap = computed(() => content.value.gap)
  const padding = computed(() => content.value.padding)

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  const addChild = (type: ElementType): string | null => {
    if (element.type !== 'container') return null
    return store.addChildElement(element.id, type)
  }

  const removeChild = (childId: string): void => {
    if (element.type !== 'container') return
    store.removeChildElement(element.id, childId)
  }

  return {
    content,
    children,
    isContainer,
    isEmpty,
    layout,
    gap,
    padding,
    updateContent,
    addChild,
    removeChild,
  }
}
