import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'
import { useBarcodeElement } from '../composables/useBarcodeElement'
import BarcodeElement from '../BarcodeElement.vue'
import BarcodeProperty from '../BarcodeProperty.vue'

function createBarcodeElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'barcode-1',
    type: 'barcode',
    x: 0,
    y: 0,
    width: 80,
    height: 25,
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
      type: 'barcode',
      symbology: 'code128',
      value: '12345',
      showLabel: true,
    },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('useBarcodeElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns all content fields', () => {
    const el = createBarcodeElement()
    const { content, symbology, value, showLabel } = useBarcodeElement(el)
    expect(symbology.value).toBe('code128')
    expect(value.value).toBe('12345')
    expect(showLabel.value).toBe(true)
    expect(content.value.variable).toBeUndefined()
  })

  it('returns variable when set', () => {
    const el = createBarcodeElement({
      content: { type: 'barcode', symbology: 'code128', value: '', showLabel: false, variable: 'product.sku' },
    })
    const { variable, showLabel } = useBarcodeElement(el)
    expect(variable.value).toBe('product.sku')
    expect(showLabel.value).toBe(false)
  })

  it('updateContent updates each field independently', () => {
    const store = useDesignerStore()
    const elId = store.addElement('barcode')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useBarcodeElement(el)

    updateContent('symbology', 'qr')
    updateContent('value', 'TEST')
    updateContent('showLabel', false)

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const c = updated.content as any
    expect(c.symbology).toBe('qr')
    expect(c.value).toBe('TEST')
    expect(c.showLabel).toBe(false)
  })
})

describe('BarcodeElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders static label regardless of content', () => {
    const el1 = createBarcodeElement()
    const wrapper1 = mount(BarcodeElement, { props: { element: el1 } })
    expect(wrapper1.text()).toContain('▌▌ Barcode')

    const el2 = createBarcodeElement({
      content: { type: 'barcode', symbology: 'qr', value: '', showLabel: false },
    })
    const wrapper2 = mount(BarcodeElement, { props: { element: el2 } })
    expect(wrapper2.text()).toContain('▌▌ Barcode')
  })

  it('wrapper has correct classes', () => {
    const el = createBarcodeElement()
    const wrapper = mount(BarcodeElement, { props: { element: el } })
    const div = wrapper.find('div')
    expect(div.classes()).toContain('flex')
    expect(div.classes()).toContain('h-full')
    expect(div.classes()).toContain('w-full')
    expect(div.classes()).toContain('items-center')
    expect(div.classes()).toContain('justify-center')
    expect(div.classes()).toContain('font-mono')
    expect(div.classes()).toContain('text-gray-500')
  })
})

describe('BarcodeProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders all 4 form controls', () => {
    const el = createBarcodeElement()
    const wrapper = mount(BarcodeProperty, { props: { element: el } })

    const selects = wrapper.findAllComponents({ name: 'SelectInput' })
    expect(selects.length).toBe(1)
    expect(selects[0].props('label')).toBe('Symbology')

    const textInputs = wrapper.findAllComponents({ name: 'TextInput' })
    expect(textInputs.length).toBe(2)
    expect(textInputs[0].props('label')).toBe('Value')
    expect(textInputs[1].props('label')).toBe('Variable')

    const checkbox = wrapper.find('input[type="checkbox"]')
    expect(checkbox.exists()).toBe(true)
  })

  it('Symbology select has correct options', () => {
    const el = createBarcodeElement()
    const wrapper = mount(BarcodeProperty, { props: { element: el } })
    const select = wrapper.findComponent({ name: 'SelectInput' })
    expect(select.props('options')).toEqual(['code128', 'code39', 'ean13', 'qr'])
  })

  it('updating SelectInput calls updateContent', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('barcode')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(BarcodeProperty, { props: { element: el } })

    const select = wrapper.findComponent({ name: 'SelectInput' })
    await select.vm.$emit('update:model-value', 'qr')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).symbology).toBe('qr')
  })
})
