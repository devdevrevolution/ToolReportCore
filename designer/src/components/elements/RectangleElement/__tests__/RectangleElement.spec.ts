import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'
import { useRectangleElement } from '../composables/useRectangleElement'
import RectangleElement from '../RectangleElement.vue'
import RectangleProperty from '../RectangleProperty.vue'

function createRectangleElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'rect-1',
    type: 'rectangle',
    x: 0,
    y: 0,
    width: 80,
    height: 40,
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
    content: { type: 'rectangle' },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('useRectangleElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns hasColorVariable=true when colorVariable is set', () => {
    const el = createRectangleElement({
      content: { type: 'rectangle', colorVariable: 'status.color' },
    })
    const { hasColorVariable, colorVariable } = useRectangleElement(el)
    expect(hasColorVariable.value).toBe(true)
    expect(colorVariable.value).toBe('status.color')
  })

  it('returns hasColorVariable=false when colorVariable is undefined', () => {
    const el = createRectangleElement()
    const { hasColorVariable, colorVariable } = useRectangleElement(el)
    expect(hasColorVariable.value).toBe(false)
    expect(colorVariable.value).toBeUndefined()
  })

  it('returns hasColorVariable=false when colorVariable is empty string', () => {
    const el = createRectangleElement({
      content: { type: 'rectangle', colorVariable: '' },
    })
    const { hasColorVariable } = useRectangleElement(el)
    expect(hasColorVariable.value).toBe(false)
  })

  it('updateContent calls store.updateElement', () => {
    const store = useDesignerStore()
    const elId = store.addElement('rectangle')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useRectangleElement(el)

    updateContent('colorVariable', 'data.color')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).colorVariable).toBe('data.color')
  })
})

describe('RectangleElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders badge when colorVariable is set', () => {
    const el = createRectangleElement({
      content: { type: 'rectangle', colorVariable: 'status.color' },
    })
    const wrapper = mount(RectangleElement, { props: { element: el } })
    expect(wrapper.text()).toContain('🎨')
    expect(wrapper.text()).toContain('status.color')
  })

  it('renders empty when colorVariable is undefined', () => {
    const el = createRectangleElement()
    const wrapper = mount(RectangleElement, { props: { element: el } })
    expect(wrapper.text()).toBe('')
  })

  it('renders empty when colorVariable is empty string', () => {
    const el = createRectangleElement({
      content: { type: 'rectangle', colorVariable: '' },
    })
    const wrapper = mount(RectangleElement, { props: { element: el } })
    expect(wrapper.text()).toBe('')
  })
})

describe('RectangleProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders color variable input with current value', () => {
    const el = createRectangleElement({
      content: { type: 'rectangle', colorVariable: 'data.color' },
    })
    const wrapper = mount(RectangleProperty, { props: { element: el } })
    const input = wrapper.find('input[type="text"]')
    expect(input.exists()).toBe(true)
    expect((input.element as HTMLInputElement).value).toBe('data.color')
  })

  it('renders color variable input with empty when no value', () => {
    const el = createRectangleElement()
    const wrapper = mount(RectangleProperty, { props: { element: el } })
    const input = wrapper.find('input[type="text"]')
    expect((input.element as HTMLInputElement).value).toBe('')
  })

  it('renders field selector button', () => {
    const el = createRectangleElement()
    const wrapper = mount(RectangleProperty, { props: { element: el } })
    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)
    expect(button.text()).toContain('↳')
  })

  it('shows FieldSelector when button is clicked', async () => {
    const el = createRectangleElement()
    const wrapper = mount(RectangleProperty, { props: { element: el } })
    const button = wrapper.find('button')
    await button.trigger('click')
    const fieldSelector = wrapper.findComponent({ name: 'FieldSelector' })
    expect(fieldSelector.exists()).toBe(true)
  })

  it('updateContent on input change', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('rectangle')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(RectangleProperty, { props: { element: el } })

    const input = wrapper.find('input[type="text"]')
    await input.setValue('new.color')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).colorVariable).toBe('new.color')
  })
})
