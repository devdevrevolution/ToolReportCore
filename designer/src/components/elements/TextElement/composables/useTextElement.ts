import { computed, type Ref } from 'vue'
import type { DesignerElement, TextContent } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'

export function useTextElement(
  element: DesignerElement,
  scale?: Ref<number | undefined>,
) {
  const store = useDesignerStore()

  const content = computed(() => element.content as TextContent)
  const text = computed(() => content.value.text)
  const variable = computed<string | undefined>(() => content.value.variable)

  const textPreviewHtml = computed(() => {
    if (element.type !== 'text') return ''
    const c = content.value
    let rawText = ''
    if (c.text) {
      rawText = c.text.slice(0, 200)
    } else if (c.variable) {
      rawText = `{{ ${c.variable} }}`
    } else {
      rawText = 'Text'
    }
    // Escape HTML then convert newlines to <br>
    return rawText
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>')
  })

  const textPreviewStyle = computed(() => {
    if (element.type !== 'text') return {}

    const s = scale?.value ?? 1

    // Scale font-size (in pt) to canvas pixels: pt → mm → px at current zoom
    // 1pt = 25.4/72 mm, then * scale.value (px/mm) = px on screen
    const scaledFontSize = (element.styles.fontSize * 25.4 * s) / 72

    const style: Record<string, string> = {
      fontFamily: element.styles.fontFamily,
      fontSize: `${scaledFontSize}px`,
      fontWeight: element.styles.fontWeight,
      fontStyle: element.styles.fontStyle,
      color: element.styles.color,
      lineHeight: String(element.styles.lineHeight),
    }

    // Horizontal alignment via text-align
    style.textAlign = element.styles.textAlign ?? 'left'

    // Padding: scale from mm to canvas pixels at current zoom
    const pad = element.styles.padding ?? { top: 0, right: 0, bottom: 0, left: 0 }
    const pt = (pad.top ?? 0) * s
    const pr = (pad.right ?? 0) * s
    const pb = (pad.bottom ?? 0) * s
    const pl = (pad.left ?? 0) * s

    if (pt > 0 || pr > 0 || pb > 0 || pl > 0) {
      style.padding = `${pt}px ${pr}px ${pb}px ${pl}px`
    }

    return style
  })

  const updateContent = (field: string, value: unknown) => {
    const current = { ...element.content } as Record<string, unknown>
    current[field] = value
    store.updateElement(element.id, { content: current as never })
  }

  const expressionPreview = (variable: string): string => {
    return `{{ ${variable} }}`
  }

  return {
    content,
    text,
    variable,
    textPreviewHtml,
    textPreviewStyle,
    updateContent,
    expressionPreview,
  }
}
