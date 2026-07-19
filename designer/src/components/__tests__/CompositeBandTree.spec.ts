// ──────────────────────────────────────────────
// Toolreport Designer — CompositeBandTree Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import CompositeBandTree from '../navigation/CompositeBandTree.vue'

describe('CompositeBandTree', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('renders the composite-band-tree root', () => {
        const wrapper = mount(CompositeBandTree)
        expect(wrapper.find('[data-testid="composite-band-tree"]').exists()).toBe(true)
    })

    it('renders all default bands', () => {
        const store = useDesignerStore()
        expect(store.page.bands).toHaveLength(7)

        const wrapper = mount(CompositeBandTree)
        const headers = wrapper.findAll('[data-testid="band-header"]')
        expect(headers).toHaveLength(7)
    })

    it('shows "empty" indicator for bands with no composite content', () => {
        const wrapper = mount(CompositeBandTree)
        const headers = wrapper.findAll('[data-testid="band-header"]')
        // All default bands have empty content
        expect(headers[0].text()).toContain('empty')
    })

    it('shows "No content" hint for expanded empty bands', () => {
        const wrapper = mount(CompositeBandTree)
        expect(wrapper.text()).toContain('No content')
    })

    it('renders CompositeTreeItem when a band has composite content', () => {
        const store = useDesignerStore()
        store.addCompositeNode('detail', 'VBox')

        const wrapper = mount(CompositeBandTree)
        // CompositeTreeItem renders the node type badge text "VBox"
        expect(wrapper.text()).toContain('VBox')
    })

    it('selects band and clears composite selection on header click', async () => {
        const store = useDesignerStore()
        const wrapper = mount(CompositeBandTree)

        const headers = wrapper.findAll('[data-testid="band-header"]')
        await headers[0].trigger('click')

        expect(store.selectedBandId).toBe('title')
        expect(store.selectedCompositeNodeId).toBeNull()
    })

    it('toggles band enabled state when clicking enable toggle', async () => {
        const store = useDesignerStore()
        const wrapper = mount(CompositeBandTree)

        const enableToggles = wrapper.findAll('[data-testid="band-enable-toggle"]')
        await enableToggles[0].trigger('click')

        expect(store.page.bands![0].enabled).toBe(false)
    })

    it('renders nested children inside a VBox root', () => {
        const store = useDesignerStore()
        const rootId = store.addCompositeNode('detail', 'VBox')
        const root = store.page.bands!.find(b => b.id === 'detail')!.children![0]
        store.addChildCompositeNode(root.node.id, 'Label')
        store.addChildCompositeNode(root.node.id, 'Shape')

        const wrapper = mount(CompositeBandTree)
        const text = wrapper.text()
        // Root row shows VBox badge
        expect(text).toContain('VBox')
        // Nested children appear via CompositeTreeItem
        expect(text).toContain('Label')
        expect(text).toContain('Shape')
    })

    it('renders deeply nested children (HBox inside VBox)', () => {
        const store = useDesignerStore()
        const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0]
        const rootId = store.addCompositeNode('detail', 'VBox')
        const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as import('@/types/designer').CompositeRoot
        const hBoxId = store.addChildCompositeNode(root.node.id, 'HBox')!
        store.addChildCompositeNode(hBoxId, 'Label')

        const wrapper = mount(CompositeBandTree)
        const text = wrapper.text()
        expect(text).toContain('VBox')
        expect(text).toContain('HBox')
        expect(text).toContain('Label')
    })
})