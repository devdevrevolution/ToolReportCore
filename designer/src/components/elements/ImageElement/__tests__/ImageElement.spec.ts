import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'
import { useImageElement } from '../composables/useImageElement'
import ImageElement from '../ImageElement.vue'
import ImageProperty from '../ImageProperty.vue'

function createImageElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'img-1',
    type: 'image',
    x: 0,
    y: 0,
    width: 80,
    height: 60,
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
      type: 'image',
      imageUrl: '',
      altText: '',
      variable: undefined,
    },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('useImageElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns correct computed values for imageUrl, altText, variable', () => {
    const el = createImageElement({
      content: { type: 'image', imageUrl: 'https://example.com/photo.jpg', altText: 'A photo', variable: 'photo' },
    })
    const { imageUrl, altText, variable } = useImageElement(el)
    expect(imageUrl.value).toBe('https://example.com/photo.jpg')
    expect(altText.value).toBe('A photo')
    expect(variable.value).toBe('photo')
  })

  it('hasValidUrl is true when URL is set and does not contain {{', () => {
    const el = createImageElement({
      content: { type: 'image', imageUrl: 'https://example.com/photo.jpg', altText: '', variable: undefined },
    })
    const { hasValidUrl } = useImageElement(el)
    expect(hasValidUrl.value).toBe(true)
  })

  it('hasValidUrl is false when URL contains {{', () => {
    const el = createImageElement({
      content: { type: 'image', imageUrl: '{{ photo }}', altText: '', variable: undefined },
    })
    const { hasValidUrl } = useImageElement(el)
    expect(hasValidUrl.value).toBe(false)
  })

  it('hasValidUrl is false when URL is empty', () => {
    const el = createImageElement()
    const { hasValidUrl } = useImageElement(el)
    expect(hasValidUrl.value).toBe(false)
  })

  it('imagePreviewContainerStyle returns borderRadius styles when set', () => {
    const el = createImageElement({
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
        borderRadius: 8,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
      },
    })
    const { imagePreviewContainerStyle } = useImageElement(el)
    expect(imagePreviewContainerStyle.value).toEqual({
      borderRadius: '8px',
      overflow: 'hidden',
    })
  })

  it('imagePreviewContainerStyle returns empty when borderRadius is 0', () => {
    const el = createImageElement()
    const { imagePreviewContainerStyle } = useImageElement(el)
    expect(imagePreviewContainerStyle.value).toEqual({})
  })

  it('imagePreviewContainerStyle returns empty when borderRadius is null', () => {
    const el = createImageElement({
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
        borderRadius: null as any,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
      },
    })
    const { imagePreviewContainerStyle } = useImageElement(el)
    expect(imagePreviewContainerStyle.value).toEqual({})
  })

  it('imagePreviewImgStyle returns borderRadius when set', () => {
    const el = createImageElement({
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
        borderRadius: 8,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
      },
    })
    const { imagePreviewImgStyle } = useImageElement(el)
    expect(imagePreviewImgStyle.value).toEqual({ borderRadius: '8px' })
  })

  it('onImageError hides the img element', () => {
    const el = createImageElement()
    const { onImageError } = useImageElement(el)
    const img = document.createElement('img')
    const event = new Event('error')
    Object.defineProperty(event, 'target', { value: img, enumerable: true })

    onImageError(event)

    expect(img.style.display).toBe('none')
  })

  it('updateContent calls store.updateElement with correct payload', () => {
    const store = useDesignerStore()
    const elId = store.addElement('image')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useImageElement(el)

    updateContent('imageUrl', 'https://example.com/new.jpg')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).imageUrl).toBe('https://example.com/new.jpg')
  })

  it('updateContent sets variable to undefined when undefined passed', () => {
    const store = useDesignerStore()
    const elId = store.addElement('image')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const { updateContent } = useImageElement(el)

    updateContent('variable', undefined)

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).variable).toBeUndefined()
  })
})

describe('ImageElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders <img> for valid URL', () => {
    const el = createImageElement({
      content: { type: 'image', imageUrl: 'https://example.com/photo.jpg', altText: '', variable: undefined },
    })
    const wrapper = mount(ImageElement, { props: { element: el } })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/photo.jpg')
    expect(img.attributes('draggable')).toBe('false')
    expect(img.classes()).toContain('max-h-full')
    expect(img.classes()).toContain('max-w-full')
    expect(img.classes()).toContain('object-contain')
  })

  it('shows variable badge when URL contains {{', () => {
    const el = createImageElement({
      content: { type: 'image', imageUrl: '{{ photo }}', altText: '', variable: 'photo' },
    })
    const wrapper = mount(ImageElement, { props: { element: el } })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toContain('🖼')
    expect(wrapper.text()).toContain('photo')
  })

  it('shows variable badge when URL is empty but variable is set', () => {
    const el = createImageElement({
      content: { type: 'image', imageUrl: '', altText: '', variable: 'myPhoto' },
    })
    const wrapper = mount(ImageElement, { props: { element: el } })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toContain('🖼')
    expect(wrapper.text()).toContain('myPhoto')
  })

  it('shows placeholder when no URL and no variable', () => {
    const el = createImageElement()
    const wrapper = mount(ImageElement, { props: { element: el } })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toContain('🖼')
  })

  it('wrapper has correct container classes', () => {
    const el = createImageElement()
    const wrapper = mount(ImageElement, { props: { element: el } })
    const container = wrapper.find('div:first-child')
    expect(container.classes()).toContain('flex')
    expect(container.classes()).toContain('h-full')
    expect(container.classes()).toContain('w-full')
    expect(container.classes()).toContain('items-center')
    expect(container.classes()).toContain('justify-center')
    expect(container.classes()).toContain('bg-gray-50')
    expect(container.classes()).toContain('text-gray-400')
  })
})

describe('ImageProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders Image URL TextInput', () => {
    const el = createImageElement()
    const wrapper = mount(ImageProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, TextInput: true },
      },
    })
    const textInputs = wrapper.findAllComponents({ name: 'TextInput' })
    const urlInput = textInputs.find(t => t.props('label') === 'Image URL')
    expect(urlInput).toBeDefined()
  })

  it('renders Alt Text TextInput', () => {
    const el = createImageElement()
    const wrapper = mount(ImageProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, TextInput: true },
      },
    })
    const textInputs = wrapper.findAllComponents({ name: 'TextInput' })
    const altTextInput = textInputs.find(t => t.props('label') === 'Alt Text')
    expect(altTextInput).toBeDefined()
  })

  it('shows variable input with ↳ button', () => {
    const el = createImageElement()
    const wrapper = mount(ImageProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, TextInput: true },
      },
    })
    const buttons = wrapper.findAll('button')
    const fieldBtn = buttons.find(b => b.text().includes('↳'))
    expect(fieldBtn).toBeDefined()
  })

  it('FieldSelector opens when ↳ button is clicked', async () => {
    const el = createImageElement()
    const wrapper = mount(ImageProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, TextInput: true },
      },
    })
    const buttons = wrapper.findAll('button')
    const fieldBtn = buttons.find(b => b.text().includes('↳'))
    await fieldBtn!.trigger('click')
    const fieldSelector = wrapper.findComponent({ name: 'FieldSelector' })
    expect(fieldSelector.exists()).toBe(true)
  })

  it('URL input updates store in real-time', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('image')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(ImageProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, TextInput: true },
      },
    })

    const textInputs = wrapper.findAllComponents({ name: 'TextInput' })
    const urlInput = textInputs.find(t => t.props('label') === 'Image URL')
    await urlInput!.vm.$emit('update:model-value', 'https://example.com/new.jpg')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).imageUrl).toBe('https://example.com/new.jpg')
  })

  it('variable input updates store on typing', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('image')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const wrapper = mount(ImageProperty, {
      props: { element: el },
      global: {
        stubs: { FieldSelector: true, TextInput: true },
      },
    })

    const input = wrapper.find('input[type="text"]')
    await input.setValue('data.photo')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    expect((updated.content as any).variable).toBe('data.photo')
  })
})
