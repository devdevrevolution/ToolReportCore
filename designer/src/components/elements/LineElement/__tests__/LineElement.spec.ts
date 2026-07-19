import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'
import { useLineElement } from '../composables/useLineElement'
import LineElement from '../LineElement.vue'
import LineProperty from '../LineProperty.vue'

function createLineElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'line-1',
    type: 'line',
    x: 0,
    y: 0,
    width: 80,
    height: 2,
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
      type: 'line',
      orientation: 'horizontal',
      lineWidth: 1,
      lineStyle: 'solid',
    },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('useLineElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns correct orientation for horizontal input', () => {
    const el = createLineElement()
    const { orientation } = useLineElement(el)
    expect(orientation.value).toBe('horizontal')
  })

  it('returns correct orientation for vertical input', () => {
    const el = createLineElement({
      content: { type: 'line', orientation: 'vertical', lineWidth: 1, lineStyle: 'solid' },
    })
    const { orientation } = useLineElement(el)
    expect(orientation.value).toBe('vertical')
  })

  it('returns correct lineWidth and lineStyle', () => {
    const el = createLineElement({
      content: { type: 'line', orientation: 'horizontal', lineWidth: 3, lineStyle: 'dashed' },
    })
    const { lineWidth, lineStyle } = useLineElement(el)
    expect(lineWidth.value).toBe(3)
    expect(lineStyle.value).toBe('dashed')
  })

  it('updateContent calls store.updateElement with correct payload', () => {
    const store = useDesignerStore()
    const elId = store.addElement('line')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useLineElement(el)

    updateContent('orientation', 'vertical')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).orientation).toBe('vertical')
  })
})

describe('LineElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders ━━━ when orientation is horizontal', () => {
    const el = createLineElement({
      content: { type: 'line', orientation: 'horizontal', lineWidth: 1, lineStyle: 'solid' },
    })
    const wrapper = mount(LineElement, { props: { element: el } })
    expect(wrapper.text()).toContain('━━━')
  })

  it('renders ┃ when orientation is vertical', () => {
    const el = createLineElement({
      content: { type: 'line', orientation: 'vertical', lineWidth: 1, lineStyle: 'solid' },
    })
    const wrapper = mount(LineElement, { props: { element: el } })
    expect(wrapper.text()).toContain('┃')
  })

  it('wrapper has correct classes', () => {
    const el = createLineElement()
    const wrapper = mount(LineElement, { props: { element: el } })
    const div = wrapper.find('div')
    expect(div.classes()).toContain('flex')
    expect(div.classes()).toContain('h-full')
    expect(div.classes()).toContain('w-full')
    expect(div.classes()).toContain('items-center')
    expect(div.classes()).toContain('justify-center')
    expect(div.classes()).toContain('text-gray-400')
  })
})

describe('LineProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders Orientation select with both options', () => {
    const el = createLineElement()
    const wrapper = mount(LineProperty, { props: { element: el } })
    const selects = wrapper.findAllComponents({ name: 'SelectInput' })
    const orientationSelect = selects.find(s => s.props('label') === 'Orientation')
    expect(orientationSelect).toBeDefined()
    expect(orientationSelect!.props('options')).toEqual(['horizontal', 'vertical'])
  })

  it('renders Line Width NumberInput', () => {
    const el = createLineElement()
    const wrapper = mount(LineProperty, { props: { element: el } })
    const numberInputs = wrapper.findAllComponents({ name: 'NumberInput' })
    const lineWidthInput = numberInputs.find(n => n.props('label') === 'Line Width')
    expect(lineWidthInput).toBeDefined()
    expect(lineWidthInput!.props('modelValue')).toBe(1)
  })

  it('renders Style select with solid, dashed, dotted', () => {
    const el = createLineElement()
    const wrapper = mount(LineProperty, { props: { element: el } })
    const selects = wrapper.findAllComponents({ name: 'SelectInput' })
    const styleSelect = selects.find(s => s.props('label') === 'Style')
    expect(styleSelect).toBeDefined()
    expect(styleSelect!.props('options')).toEqual(['solid', 'dashed', 'dotted'])
  })

  it('Changing Orientation calls updateContent', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('line')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(LineProperty, { props: { element: el } })

    const selects = wrapper.findAllComponents({ name: 'SelectInput' })
    const orientationSelect = selects.find(s => s.props('label') === 'Orientation')!
    await orientationSelect.vm.$emit('update:model-value', 'vertical')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).orientation).toBe('vertical')
  })
})
