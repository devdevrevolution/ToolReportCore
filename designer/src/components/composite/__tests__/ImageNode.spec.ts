import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { ImageNode, CompositeRoot } from '@/types/designer'
import ImageNodeComponent from '../ImageNode.vue'

// Mock localStorage for Node test environment
const localStorageMock = (() => {
    let store: Record<string, string> = {}
    return {
        getItem: (key: string) => store[key] ?? null,
        setItem: (key: string, value: string) => { store[key] = value },
        removeItem: (key: string) => { delete store[key] },
        clear: () => { store = {} },
    }
})()
vi.stubGlobal('localStorage', localStorageMock)

function createImageNode(overrides: Partial<ImageNode> = {}): ImageNode {
    return {
        id: 'img-node-1',
        type: 'Image',
        ...overrides,
    }
}

describe('ImageNode composite component', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        localStorageMock.clear()
    })

    describe('rendering', () => {
        it('renders SVG image when url is a direct URL', () => {
            const node = createImageNode({ url: 'https://example.com/photo.jpg' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const svg = wrapper.find('svg')
            expect(svg.exists()).toBe(true)
            const image = svg.find('image')
            expect(image.exists()).toBe(true)
            expect(image.attributes('href')).toBe('https://example.com/photo.jpg')
            expect(image.attributes('draggable')).toBe('false')
        })

        it('renders variable placeholder when url contains {{ }}', () => {
            const node = createImageNode({ url: '{{ logo }}' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            expect(wrapper.find('image').exists()).toBe(false)
            expect(wrapper.text()).toContain('🖼')
            expect(wrapper.text()).toContain('logo')
        })

        it('extracts variable name correctly from {{ name }}', () => {
            const node = createImageNode({ url: '{{ logo }}' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            expect(wrapper.text()).toContain('logo')
        })

        it('extracts variable name from {{ name }} with spaces', () => {
            const node = createImageNode({ url: '{{   companyLogo   }}' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            expect(wrapper.text()).toContain('companyLogo')
        })

        it('renders generic placeholder when url is empty', () => {
            const node = createImageNode()
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            expect(wrapper.find('image').exists()).toBe(false)
            expect(wrapper.text()).toContain('🖼')
        })

        it('hides image on error', async () => {
            const node = createImageNode({ url: 'https://example.com/broken.jpg' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const image = wrapper.find('image')
            await image.trigger('error')
            expect(image.attributes('style')).toContain('display: none')
        })
    })

    describe('style computation', () => {
        it('maps objectFit cover to SVG slice', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                objectFit: 'cover',
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const image = wrapper.find('image')
            expect(image.attributes('preserveAspectRatio')).toBe('xMidYMid slice')
        })

        it('maps objectFit contain to SVG meet', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                objectFit: 'contain',
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const image = wrapper.find('image')
            expect(image.attributes('preserveAspectRatio')).toBe('xMidYMid meet')
        })

        it('applies borderRadius via clipPath', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                borderRadius: 8,
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const svg = wrapper.find('svg')
            expect(svg.find('clipPath').exists()).toBe(true)
        })

        it('applies opacity attribute when less than 1', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                opacity: 0.5,
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const image = wrapper.find('image')
            expect(image.attributes('opacity')).toBe('0.5')
        })

        it('does not apply opacity when equal to 1', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                opacity: 1,
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const image = wrapper.find('image')
            expect(image.attributes('opacity')).toBe('1')
        })

        it('applies explicit width in mm converted to px', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                width: 50,
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const div = wrapper.find('div')
            expect(div.attributes('style')).toContain('width')
        })

        it('applies explicit height in mm converted to px', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                height: 40,
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const div = wrapper.find('div')
            expect(div.attributes('style')).toContain('height')
        })

        it('applies wrapper overflow hidden', () => {
            const node = createImageNode({
                url: 'https://example.com/photo.jpg',
                borderRadius: 8,
            })
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            const div = wrapper.find('div')
            expect(div.classes()).toContain('overflow-hidden')
        })
    })

    describe('parent layout adaptation', () => {
        it('adds h-full class when parentLayout is HBox', () => {
            const node = createImageNode({ url: 'https://example.com/photo.jpg' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node, parentLayout: 'HBox' },
            })
            const div = wrapper.find('div')
            expect(div.classes()).toContain('h-full')
        })

        it('adds w-full class when parentLayout is VBox', () => {
            const node = createImageNode({ url: 'https://example.com/photo.jpg' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node, parentLayout: 'VBox' },
            })
            const div = wrapper.find('div')
            expect(div.classes()).toContain('w-full')
        })

        it('sets wrapper width to 100% when in VBox and no explicit width', () => {
            const node = createImageNode({ url: 'https://example.com/photo.jpg' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node, parentLayout: 'VBox' },
            })
            const div = wrapper.find('div')
            expect(div.attributes('style')).toContain('width: 100%')
        })

        it('sets wrapper height to 100% when in HBox and no explicit height', () => {
            const node = createImageNode({ url: 'https://example.com/photo.jpg' })
            const wrapper = mount(ImageNodeComponent, {
                props: { node, parentLayout: 'HBox' },
            })
            const div = wrapper.find('div')
            expect(div.attributes('style')).toContain('height: 100%')
        })
    })

    describe('backward compatibility', () => {
        it('renders variable placeholder when url contains {{ }} even if variable field exists on node', () => {
            const node = createImageNode({
                url: '{{ logo }}',
                variable: 'legacyVariable',
            } as Partial<ImageNode>)
            const wrapper = mount(ImageNodeComponent, {
                props: { node },
            })
            expect(wrapper.find('image').exists()).toBe(false)
            expect(wrapper.text()).toContain('logo')
        })
    })
})

describe('ImageNode store integration', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        localStorageMock.clear()
    })

    it('creates an Image composite node with defaults', () => {
        const store = useDesignerStore()
        const rootId = store.addCompositeNode('detail', 'Image')

        const band = store.page.bands!.find(b => b.id === 'detail')!
        expect(band.children).toHaveLength(1)
        const root = band.children![0] as CompositeRoot
        expect(root.node.type).toBe('Image')
        expect((root.node as ImageNode).objectFit).toBe('contain')
        expect((root.node as ImageNode).width).toBe(40)
        expect((root.node as ImageNode).height).toBe(30)
    })

    it('can update Image url property', () => {
        const store = useDesignerStore()
        const rootId = store.addCompositeNode('detail', 'Image')
        const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

        store.updateCompositeNode(root.node.id, {
            url: 'https://example.com/logo.png',
            objectFit: 'cover',
        })

        const node = root.node as ImageNode
        expect(node.url).toBe('https://example.com/logo.png')
        expect(node.objectFit).toBe('cover')
    })

    it('can add Image as child of VBox', () => {
        const store = useDesignerStore()
        const vBoxRootId = store.addCompositeNode('detail', 'VBox')
        const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

        const childId = store.addChildCompositeNode(vBoxRoot.node.id, 'Image')
        expect(childId).not.toBeNull()

        const vBoxNode = vBoxRoot.node as { type: string; children: unknown[] }
        expect(vBoxNode.children).toHaveLength(1)
        expect((vBoxNode.children[0] as { type: string }).type).toBe('Image')
    })

    it('can add Image as child of HBox', () => {
        const store = useDesignerStore()
        const hBoxRootId = store.addCompositeNode('detail', 'HBox')
        const hBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

        const childId = store.addChildCompositeNode(hBoxRoot.node.id, 'Image')
        expect(childId).not.toBeNull()

        const hBoxNode = hBoxRoot.node as { type: string; children: unknown[] }
        expect(hBoxNode.children).toHaveLength(1)
        expect((hBoxNode.children[0] as { type: string }).type).toBe('Image')
    })
})
