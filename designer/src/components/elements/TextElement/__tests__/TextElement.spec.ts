import { ref } from 'vue'
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'
import { useTextElement } from '../composables/useTextElement'
import TextElement from '../TextElement.vue'
import TextProperty from '../TextProperty.vue'

function createTextElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'text-1',
    type: 'text',
    x: 0,
    y: 0,
    width: 80,
    height: 20,
    rotation: 0,
    styles: {
      fontFamily: 'Helvetica',
      fontSize: 12,
      fontWeight: 'normal',
      fontStyle: 'normal',
      color: '#000000',
      textAlign: 'left',
      verticalAlign: 'top',
      lineHeight: 1.2,
      backgroundColor: null,
      border: null,
      borderRadius: 0,
      padding: { top: 0, right: 0, bottom: 0, left: 0 },
    },
    content: {
      type: 'text',
      text: '',
      variable: undefined,
    },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('useTextElement composable — textPreviewHtml', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns HTML with <br> for newlines', () => {
    const el = createTextElement({
      content: { type: 'text', text: 'Hello\nWorld', variable: undefined },
    })
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value).toBe('Hello<br>World')
  })

  it('escapes HTML entities (&, <, >)', () => {
    const el = createTextElement({
      content: { type: 'text', text: 'Tom & Jerry <div>test</div>', variable: undefined },
    })
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value).toBe('Tom &amp; Jerry &lt;div&gt;test&lt;/div&gt;')
  })

  it('shows {{ variable }} when text is empty and variable is set', () => {
    const el = createTextElement({
      content: { type: 'text', text: '', variable: 'price | currency("$")' },
    })
    const { textPreviewHtml } = useTextElement(el)
    // Note: the escaping only handles &, <, >, \n — not double quotes
    expect(textPreviewHtml.value).toBe('{{ price | currency("$") }}')
  })

  it('shows text when both text and variable are present (text priority)', () => {
    const el = createTextElement({
      content: { type: 'text', text: 'Hello', variable: 'name' },
    })
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value).toBe('Hello')
  })

  it('returns Text fallback when both text and variable are empty', () => {
    const el = createTextElement()
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value).toBe('Text')
  })

  it('returns empty string for non-text elements', () => {
    const el = createTextElement({ type: 'line' as any })
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value).toBe('')
  })

  it('truncates text to 200 characters', () => {
    const longText = 'a'.repeat(300)
    const el = createTextElement({
      content: { type: 'text', text: longText, variable: undefined },
    })
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value.length).toBe(200)
  })

  it('handles empty string text correctly', () => {
    const el = createTextElement({
      content: { type: 'text', text: '', variable: undefined },
    })
    const { textPreviewHtml } = useTextElement(el)
    expect(textPreviewHtml.value).toBe('Text')
  })
})

describe('useTextElement composable — textPreviewStyle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns scaled font size', () => {
    const el = createTextElement({
      styles: {
        fontFamily: 'Helvetica',
        fontSize: 12,
        fontWeight: 'normal',
        fontStyle: 'normal',
        color: '#333333',
        textAlign: 'left',
        verticalAlign: 'top',
        lineHeight: 1.5,
        backgroundColor: null,
        border: null,
        borderRadius: 0,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
      },
    })
    const scale = ref(3.779)
    const { textPreviewStyle } = useTextElement(el, scale)
    const expectedFontSize = (12 * 25.4 * 3.779) / 72
    expect(textPreviewStyle.value.fontSize).toBe(`${expectedFontSize}px`)
    expect(textPreviewStyle.value.fontFamily).toBe('Helvetica')
    expect(textPreviewStyle.value.fontWeight).toBe('normal')
    expect(textPreviewStyle.value.fontStyle).toBe('normal')
    expect(textPreviewStyle.value.color).toBe('#333333')
    expect(textPreviewStyle.value.lineHeight).toBe('1.5')
    expect(textPreviewStyle.value.textAlign).toBe('left')
  })

  it('applies padding scaled to zoom', () => {
    const el = createTextElement({
      styles: {
        fontFamily: 'Helvetica',
        fontSize: 12,
        fontWeight: 'normal',
        fontStyle: 'normal',
        color: '#000000',
        textAlign: 'left',
        verticalAlign: 'top',
        lineHeight: 1.2,
        backgroundColor: null,
        border: null,
        borderRadius: 0,
        padding: { top: 2, right: 3, bottom: 1, left: 4 },
      },
    })
    const scale = ref(3.779)
    const { textPreviewStyle } = useTextElement(el, scale)
    expect(textPreviewStyle.value.padding).toBe('7.558px 11.337px 3.779px 15.116px')
  })

  it('omits padding when all values are zero', () => {
    const el = createTextElement({
      styles: {
        fontFamily: 'Helvetica',
        fontSize: 12,
        fontWeight: 'normal',
        fontStyle: 'normal',
        color: '#000000',
        textAlign: 'left',
        verticalAlign: 'top',
        lineHeight: 1.2,
        backgroundColor: null,
        border: null,
        borderRadius: 0,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
      },
    })
    const { textPreviewStyle } = useTextElement(el)
    expect(textPreviewStyle.value.padding).toBeUndefined()
  })

  it('returns empty object for non-text elements', () => {
    const el = createTextElement({ type: 'line' as any })
    const { textPreviewStyle } = useTextElement(el)
    expect(textPreviewStyle.value).toEqual({})
  })

  it('uses default scale of 1 when no scale provided', () => {
    const el = createTextElement({
      styles: {
        fontFamily: 'Helvetica',
        fontSize: 12,
        fontWeight: 'normal',
        fontStyle: 'normal',
        color: '#000000',
        textAlign: 'left',
        verticalAlign: 'top',
        lineHeight: 1.2,
        backgroundColor: null,
        border: null,
        borderRadius: 0,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
      },
    })
    const { textPreviewStyle } = useTextElement(el)
    const expectedFontSize = (12 * 25.4 * 1) / 72
    expect(textPreviewStyle.value.fontSize).toBe(`${expectedFontSize}px`)
  })
})

describe('useTextElement composable — updateContent', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('updateContent calls store.updateElement', () => {
    const store = useDesignerStore()
    const elId = store.addElement('text')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useTextElement(el)

    updateContent('text', 'New text content')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).text).toBe('New text content')
  })

  it('updateContent sets variable to undefined when empty string', () => {
    const store = useDesignerStore()
    const elId = store.addElement('text')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useTextElement(el)

    updateContent('variable', undefined)

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).variable).toBeUndefined()
  })
})

describe('useTextElement composable — expressionPreview', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns {{ variable }} format', () => {
    const el = createTextElement()
    const { expressionPreview } = useTextElement(el)
    expect(expressionPreview('price | currency("$")')).toBe('{{ price | currency("$") }}')
    expect(expressionPreview('name')).toBe('{{ name }}')
  })
})

describe('TextElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders v-html with text content', () => {
    const el = createTextElement({
      content: { type: 'text', text: 'Hello<br>World', variable: undefined },
    })
    const wrapper = mount(TextElement, { props: { element: el } })
    const innerDiv = wrapper.find('.min-w-0')
    expect(innerDiv.exists()).toBe(true)
  })

  it('applies textPreviewStyle to outer div', () => {
    const el = createTextElement()
    const wrapper = mount(TextElement, { props: { element: el } })
    const outerDiv = wrapper.find('.h-full.w-full')
    expect(outerDiv.exists()).toBe(true)
    expect(outerDiv.attributes('style')).toBeDefined()
  })

  it('outer div has correct classes', () => {
    const el = createTextElement()
    const wrapper = mount(TextElement, { props: { element: el } })
    const outerDiv = wrapper.find('div:first-child')
    expect(outerDiv.classes()).toContain('h-full')
    expect(outerDiv.classes()).toContain('w-full')
  })

  it('inner div has correct classes', () => {
    const el = createTextElement()
    const wrapper = mount(TextElement, { props: { element: el } })
    const innerDiv = wrapper.find('.min-w-0')
    expect(innerDiv.classes()).toContain('w-full')
    expect(innerDiv.classes()).toContain('min-w-0')
    expect(innerDiv.classes()).toContain('whitespace-pre-line')
    expect(innerDiv.classes()).toContain('break-words')
    expect(innerDiv.classes()).toContain('overflow-hidden')
  })
})

describe('TextProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders textarea for text content', () => {
    const el = createTextElement()
    const wrapper = mount(TextProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, ExpressionBuilder: true },
      },
    })
    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)
    expect(textarea.classes()).toContain('input')
  })

  it('textarea updates store on input', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('text')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(TextProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, ExpressionBuilder: true },
      },
    })

    const textarea = wrapper.find('textarea')
    await textarea.setValue('New text')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).text).toBe('New text')
  })

  it('renders Expression input with fx and ↳ buttons', () => {
    const el = createTextElement()
    const wrapper = mount(TextProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, ExpressionBuilder: true },
      },
    })
    const buttons = wrapper.findAll('button')
    const fxBtn = buttons.find(b => b.text().includes('fx'))
    const fieldBtn = buttons.find(b => b.text().includes('↳'))
    expect(fxBtn).toBeDefined()
    expect(fieldBtn).toBeDefined()
  })

  it('shows expression preview when variable is set', () => {
    const el = createTextElement({
      content: { type: 'text', text: '', variable: 'price | currency("$")' },
    })
    const wrapper = mount(TextProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, ExpressionBuilder: true },
      },
    })
    const preview = wrapper.find('.bg-gray-900')
    expect(preview.exists()).toBe(true)
    expect(preview.text()).toContain('price')
  })

  it('FieldSelector opens on ↳ click', async () => {
    const el = createTextElement()
    const wrapper = mount(TextProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, ExpressionBuilder: true },
      },
    })
    const buttons = wrapper.findAll('button')
    const fieldBtn = buttons.find(b => b.text().includes('↳'))
    await fieldBtn!.trigger('click')
    const fieldSelector = wrapper.findComponent({ name: 'FieldSelector' })
    expect(fieldSelector.exists()).toBe(true)
  })

  it('ExpressionBuilder opens on fx click', async () => {
    const el = createTextElement({
      content: { type: 'text', text: 'Hello {{ name }}', variable: 'name' },
    })
    const wrapper = mount(TextProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, ExpressionBuilder: true },
      },
    })
    const buttons = wrapper.findAll('button')
    const fxBtn = buttons.find(b => b.text().includes('fx'))
    await fxBtn!.trigger('click')
    const eb = wrapper.findComponent({ name: 'ExpressionBuilder' })
    expect(eb.exists()).toBe(true)
  })
})
