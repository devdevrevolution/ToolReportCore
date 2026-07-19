import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement, ContainerContent } from '@/types/designer'
import { useContainerElement } from '../composables/useContainerElement'
import ContainerElement from '../ContainerElement.vue'
import ContainerProperty from '../ContainerProperty.vue'

function createContainerElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'container-1',
    type: 'container',
    x: 20,
    y: 30,
    width: 160,
    height: 100,
    rotation: 0,
    positionMode: 'absolute',
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
      type: 'container',
      children: [],
      layout: 'vertical',
      gap: 2,
      padding: 4,
    } as ContainerContent,
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

function createChildElement(id: string, overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id,
    type: 'text',
    x: 10,
    y: 10,
    width: 60,
    height: 30,
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
    content: { type: 'text', text: 'Child' },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

describe('useContainerElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns children for container with children', () => {
    const child1 = createChildElement('c1')
    const child2 = createChildElement('c2')
    const el = createContainerElement({
      content: { type: 'container', children: [child1, child2], layout: 'vertical', gap: 2, padding: 4 },
    })
    const { children, isContainer, isEmpty } = useContainerElement(el)
    expect(children.value.length).toBe(2)
    expect(isContainer.value).toBe(true)
    expect(isEmpty.value).toBe(false)
  })

  it('returns empty children for empty container', () => {
    const el = createContainerElement()
    const { children, isEmpty } = useContainerElement(el)
    expect(children.value.length).toBe(0)
    expect(isEmpty.value).toBe(true)
  })

  it('returns empty children and isContainer=false for non-container', () => {
    const el = createChildElement('non-container')
    const { children, isContainer, isEmpty } = useContainerElement(el)
    expect(children.value.length).toBe(0)
    expect(isContainer.value).toBe(false)
    expect(isEmpty.value).toBe(true)
  })

  it('addChild delegates to store and returns new id', () => {
    const store = useDesignerStore()
    const elId = store.addElement('container')
    const bands = store.page.bands!
    const allEls = bands.flatMap(b => b.children)
    const el = allEls.find(e => e.id === elId)! as DesignerElement

    const { addChild, children } = useContainerElement(el)
    expect(children.value.length).toBe(0)

    const newId = addChild('text')
    expect(newId).toBeTruthy()
    expect(typeof newId).toBe('string')

    const updatedEl = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const content = updatedEl.content as ContainerContent
    expect(content.children.length).toBe(1)
    expect(content.children[0].type).toBe('text')
  })

  it('removeChild removes child from container', () => {
    const store = useDesignerStore()
    const elId = store.addElement('container')
    const c1Id = store.addChildElement(elId, 'text')
    store.addChildElement(elId, 'image')
    const bands = store.page.bands!
    const el = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement

    const { removeChild, children } = useContainerElement(el)
    expect(children.value.length).toBe(2)

    removeChild(c1Id)
    expect(children.value.length).toBe(1)
    expect(children.value[0].type).toBe('image')
  })

  it('addChild returns null for non-container element', () => {
    const el = createChildElement('non-container')
    const { addChild } = useContainerElement(el)
    expect(addChild('text')).toBeNull()
  })

  it('returns layout, gap, and padding from content', () => {
    const el = createContainerElement({
      content: { type: 'container', children: [], layout: 'horizontal', gap: 5, padding: 10 },
    })
    const { layout, gap, padding } = useContainerElement(el)
    expect(layout.value).toBe('horizontal')
    expect(gap.value).toBe(5)
    expect(padding.value).toBe(10)
  })
})

describe('ContainerElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders as a div with container-element class', () => {
    const el = createContainerElement()
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    const containerDiv = wrapper.find('.container-element')
    expect(containerDiv.exists()).toBe(true)
  })

  it('shows dashed border when empty', () => {
    const el = createContainerElement()
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    const containerDiv = wrapper.find('.container-element')
    expect(containerDiv.classes()).toContain('border-dashed')
  })

  it('does not show dashed border when it has children', () => {
    const child = createChildElement('c1')
    const el = createContainerElement({
      content: { type: 'container', children: [child], layout: 'vertical', gap: 2, padding: 4 },
    })
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    const containerDiv = wrapper.find('.container-element')
    expect(containerDiv.classes()).not.toContain('border-dashed')
  })

  it('shows "Empty container" text when empty', () => {
    const el = createContainerElement()
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    expect(wrapper.text()).toContain('Empty container')
  })

  it('renders children inside the container', () => {
    const child1 = createChildElement('c1')
    const child2 = createChildElement('c2', { type: 'image', x: 10, y: 50, width: 80, height: 40 })
    const el = createContainerElement({
      content: { type: 'container', children: [child1, child2], layout: 'vertical', gap: 2, padding: 4 },
    })
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    // Children should render as ElementRenderer instances inside the container
    const childDivs = wrapper.findAll('.container-element > div:not(.flex)')
    // There should be children rendered (the v-for divs that wrap ElementRenderer)
    expect(wrapper.findAll('[style*="position: absolute"]').length).toBe(2)
  })

  it('renders fill-mode child at origin with 100% dimensions', () => {
    const fillChild = createChildElement('fill-child', {
      positionMode: 'fill',
      x: 50,
      y: 50,
      width: 80,
      height: 40,
    })
    const el = createContainerElement({
      content: { type: 'container', children: [fillChild], layout: 'vertical', gap: 2, padding: 4 },
    })
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    // Fill child should have style containing width: 100%, height: 100%, top: 0, left: 0
    const fillDiv = wrapper.find('[style*="width: 100%"]')
    expect(fillDiv.exists()).toBe(true)
    expect(fillDiv.attributes('style')).toContain('height: 100%')
    expect(fillDiv.attributes('style')).toContain('top: 0')
    expect(fillDiv.attributes('style')).toContain('left: 0')
  })

  it('renders absolute child at relative coordinates', () => {
    const child = createChildElement('abs-child', {
      positionMode: 'absolute',
      x: 15,
      y: 20,
      width: 80,
      height: 40,
    })
    const el = createContainerElement({
      content: { type: 'container', children: [child], layout: 'vertical', gap: 2, padding: 4 },
    })
    const wrapper = mount(ContainerElement, { props: { element: el, scale: 1 } })
    const childDiv = wrapper.find('[style*="position: absolute"]')
    expect(childDiv.exists()).toBe(true)
    const style = childDiv.attributes('style')
    expect(style).toContain('top: 20px')
    expect(style).toContain('left: 15px')
    expect(style).toContain('width: 80px')
    expect(style).toContain('height: 40px')
  })
})

describe('ContainerProperty property panel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders children section with count', () => {
    const child1 = createChildElement('c1')
    const child2 = createChildElement('c2')
    const el = createContainerElement({
      content: { type: 'container', children: [child1, child2], layout: 'vertical', gap: 2, padding: 4 },
    })
    const wrapper = mount(ContainerProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('Children')
    expect(wrapper.text()).toContain('(2)')
  })

  it('renders "No children" when empty', () => {
    const el = createContainerElement()
    const wrapper = mount(ContainerProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('No children')
  })

  it('shows add child button and dropdown', () => {
    const el = createContainerElement()
    const wrapper = mount(ContainerProperty, { props: { element: el } })
    const select = wrapper.find('select')
    expect(select.exists()).toBe(true)
    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)
  })

  it('adds a child when clicking add button', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('container')
    const bands = store.page.bands!
    const el = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement

    const wrapper = mount(ContainerProperty, { props: { element: el } })

    // Select "Image" from dropdown and click Add
    const select = wrapper.find('select')
    await select.setValue('image')
    const button = wrapper.find('button')
    await button.trigger('click')

    const updatedEl = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const content = updatedEl.content as ContainerContent
    expect(content.children.length).toBe(1)
    expect(content.children[0].type).toBe('image')
  })

  it('removes a child when clicking remove button', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('container')
    const childId = store.addChildElement(elId, 'text')
    store.addChildElement(elId, 'image')
    const bands = store.page.bands!
    const el = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement

    const wrapper = mount(ContainerProperty, { props: { element: el } })

    // Find and click the remove button for the first child
    const removeButtons = wrapper.findAll('button')
    // The last button in each child row is the remove button (✕)
    // After the add button, we have: child row buttons (positionMode toggle, remove)
    // Let's find the remove button by its title
    const removeBtn = wrapper.find('button[title="Remove child"]')
    expect(removeBtn.exists()).toBe(true)
    await removeBtn.trigger('click')

    const updatedEl = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement
    const content = updatedEl.content as ContainerContent
    expect(content.children.length).toBe(1)
  })

  it('selects child in store when clicking on child entry', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('container')
    const childId = store.addChildElement(elId, 'text')
    const bands = store.page.bands!
    const el = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement

    const wrapper = mount(ContainerProperty, { props: { element: el } })

    // Click on the child entry (the clickable div with @click)
    const childEntry = wrapper.find('.cursor-pointer')
    expect(childEntry.exists()).toBe(true)
    await childEntry.trigger('click')

    expect(store.selectedElementId).toBe(childId)
  })

  it('toggles positionMode when clicking the badge', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('container')
    const childId = store.addChildElement(elId, 'text')
    const bands = store.page.bands!
    const el = bands.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement as DesignerElement

    const wrapper = mount(ContainerProperty, { props: { element: el } })

    // Find the position mode badge (the button with "Absolute" text)
    const badge = wrapper.find('button[title*="Absolute mode"]')
    expect(badge.exists()).toBe(true)

    await badge.trigger('click')

    // Check the child's positionMode was updated
    const content = el.content as ContainerContent
    expect(content.children[0].positionMode).toBe('fill')
  })
})
