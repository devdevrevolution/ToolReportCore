import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'
import { usePageNumberElement } from '../composables/usePageNumberElement'
import PageNumberElement from '../PageNumberElement.vue'
import PageNumberProperty from '../PageNumberProperty.vue'

function createPageNumberElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'pn-1',
    type: 'page_number',
    x: 0,
    y: 0,
    width: 40,
    height: 10,
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
      type: 'page_number',
      format: 'Page {current} of {total}',
      startAt: 1,
    },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('usePageNumberElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns format and startAt', () => {
    const el = createPageNumberElement()
    const { format, startAt } = usePageNumberElement(el)
    expect(format.value).toBe('Page {current} of {total}')
    expect(startAt.value).toBe(1)
  })

  it('updateContent updates format', () => {
    const store = useDesignerStore()
    const elId = store.addElement('page_number')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = usePageNumberElement(el)

    updateContent('format', 'Pág. {n}')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).format).toBe('Pág. {n}')
  })

  it('updateContent updates startAt', () => {
    const store = useDesignerStore()
    const elId = store.addElement('page_number')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = usePageNumberElement(el)

    updateContent('startAt', 5)

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).startAt).toBe(5)
  })
})

describe('PageNumberElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders static label', () => {
    const el = createPageNumberElement()
    const wrapper = mount(PageNumberElement, { props: { element: el } })
    expect(wrapper.text()).toContain('📄 Page #')
  })

  it('wrapper has correct classes', () => {
    const el = createPageNumberElement()
    const wrapper = mount(PageNumberElement, { props: { element: el } })
    const div = wrapper.find('div')
    expect(div.classes()).toContain('flex')
    expect(div.classes()).toContain('h-full')
    expect(div.classes()).toContain('w-full')
    expect(div.classes()).toContain('items-center')
    expect(div.classes()).toContain('justify-center')
    expect(div.classes()).toContain('text-gray-500')
  })
})

describe('PageNumberProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders Format TextInput with current value', () => {
    const el = createPageNumberElement()
    const wrapper = mount(PageNumberProperty, { props: { element: el } })
    const textInputs = wrapper.findAllComponents({ name: 'TextInput' })
    const formatInput = textInputs.find(t => t.props('label') === 'Format')
    expect(formatInput).toBeDefined()
    expect(formatInput!.props('modelValue')).toBe('Page {current} of {total}')
  })

  it('renders StartAt NumberInput with :min=1', () => {
    const el = createPageNumberElement()
    const wrapper = mount(PageNumberProperty, { props: { element: el } })
    const numberInput = wrapper.findComponent({ name: 'NumberInput' })
    expect(numberInput.exists()).toBe(true)
    expect(numberInput.props('label')).toBe('Start At')
    expect(numberInput.props('min')).toBe(1)
    expect(numberInput.props('modelValue')).toBe(1)
  })

  it('updating Format calls updateContent', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('page_number')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(PageNumberProperty, { props: { element: el } })

    const textInputs = wrapper.findAllComponents({ name: 'TextInput' })
    const formatInput = textInputs.find(t => t.props('label') === 'Format')!
    await formatInput.vm.$emit('update:model-value', 'Pág. {n}')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).format).toBe('Pág. {n}')
  })

  it('updating StartAt calls updateContent', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('page_number')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(PageNumberProperty, { props: { element: el } })

    const numberInput = wrapper.findComponent({ name: 'NumberInput' })
    await numberInput.vm.$emit('update:model-value', 5)

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).startAt).toBe(5)
  })
})
