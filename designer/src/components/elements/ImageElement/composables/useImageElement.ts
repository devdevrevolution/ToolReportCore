import { computed } from 'vue'
import type { DesignerElement, ImageContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function useImageElement(element: DesignerElement) {
  const store = useDesignerStore()

  const content = computed(() => element.content as ImageContent)
  const imageUrl = computed(() => content.value.imageUrl)
  const altText = computed<string | undefined>(() => content.value.altText)
  const variable = computed<string | undefined>(() => content.value.variable)

  const hasValidUrl = computed(() => !!imageUrl.value && !imageUrl.value.includes('{{'))

  const imagePreviewContainerStyle = computed(() => {
    const r = element.styles.borderRadius
    if (!r) return {}
    return {
      borderRadius: `${r}px`,
      overflow: 'hidden',
    }
  })

  const imagePreviewImgStyle = computed(() => {
    const r = element.styles.borderRadius
    if (!r) return {}
    return {
      borderRadius: `${r}px`,
    }
  })

  const onImageError = (event: Event) => {
    ;(event.target as HTMLImageElement).style.display = 'none'
  }

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  return {
    content,
    imageUrl,
    altText,
    variable,
    hasValidUrl,
    imagePreviewContainerStyle,
    imagePreviewImgStyle,
    onImageError,
    updateContent,
  }
}
