// ──────────────────────────────────────────────
// Toolreport Designer — BandTree Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import BandTree from '../navigation/BandTree.vue'

describe('BandTree', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    // ── Renders all bands ─────────────────────

    it('renders all default bands', () => {
        const store = useDesignerStore()
        expect(store.page.bands).toHaveLength(7)

        const wrapper = mount(BandTree)
        const headers = wrapper.findAll('[data-testid="band-header"]')
        expect(headers).toHaveLength(7)
    })

    it('shows band labels in order', () => {
        const wrapper = mount(BandTree)
        const labels = wrapper
            .findAll('[data-testid="band-header"]')
            .map(h => h.text())

        // Check a few key labels are present in order
        expect(labels[0]).toContain('Title')
        expect(labels[1]).toContain('Page Header')
        expect(labels[2]).toContain('Column Header')
        expect(labels[3]).toContain('Detail')
        expect(labels[4]).toContain('Column Footer')
        expect(labels[5]).toContain('Summary')
        expect(labels[6]).toContain('Page Footer')
    })

    it('shows height in mm for each band', () => {
        const wrapper = mount(BandTree)
        const headers = wrapper.findAll('[data-testid="band-header"]')

        // Title = 20mm, Page Header = 12mm, etc.
        expect(headers[0].text()).toMatch(/20mm/)
        expect(headers[1].text()).toMatch(/12mm/)
    })

    // ── Empty state per band ──────────────────

    it('shows "empty" for bands with no elements', () => {
        const wrapper = mount(BandTree)
        const headers = wrapper.findAll('[data-testid="band-header"]')
        // All default bands have empty elements
        expect(headers[0].text()).toContain('empty')
    })

    it('shows element count badge when band has elements', () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'title')
        store.addElement('image', { x: 20, y: 10 }, 'title')

        const wrapper = mount(BandTree)
        const headers = wrapper.findAll('[data-testid="band-header"]')
        // Title band should now show "2" instead of "empty"
        expect(headers[0].text()).toContain('2')
        expect(headers[0].text()).not.toContain('empty')
    })

    it('shows "No elements" hint for expanded empty bands', () => {
        const wrapper = mount(BandTree)
        expect(wrapper.text()).toContain('No elements')
    })

    // ── Expand / Collapse ─────────────────────

    it('starts with all bands expanded', () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'title')

        const wrapper = mount(BandTree)
        // Elements should be visible since bands start expanded
        expect(wrapper.findAll('[data-testid="band-element"]').length).toBeGreaterThan(0)
    })

    it('toggles band visibility when clicking toggle button', async () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'title')

        const wrapper = mount(BandTree)
        expect(wrapper.findAll('[data-testid="band-element"]').length).toBe(1)

        // Click collapse toggle to collapse
        const toggles = wrapper.findAll('[data-testid="band-collapse-toggle"]')
        await toggles[0].trigger('click')

        // Elements should be hidden
        expect(wrapper.findAll('[data-testid="band-element"]').length).toBe(0)

        // Click collapse toggle again to expand
        await toggles[0].trigger('click')
        expect(wrapper.findAll('[data-testid="band-element"]').length).toBe(1)
    })

    // ── Selection ─────────────────────────────

    it('selects band on header click', async () => {
        const store = useDesignerStore()
        const wrapper = mount(BandTree)

        const headers = wrapper.findAll('[data-testid="band-header"]')
        await headers[0].trigger('click')

        expect(store.selectedBandId).toBe('title')
        expect(store.selectedElementId).toBeNull()
    })

    it('selects element and parent band on element click', async () => {
        const store = useDesignerStore()
        const elId = store.addElement('text', { x: 10, y: 10 }, 'detail')

        const wrapper = mount(BandTree)
        const elements = wrapper.findAll('[data-testid="band-element"]')
        await elements[0].trigger('click')

        expect(store.selectedElementId).toBe(elId)
        expect(store.selectedBandId).toBe('detail')
    })

    it('applies active class to selected band header', async () => {
        const store = useDesignerStore()
        const wrapper = mount(BandTree)

        const headers = wrapper.findAll('[data-testid="band-header"]')
        await headers[1].trigger('click') // pageHeader

        // Should have blue background class
        expect(headers[1].classes()).toContain('bg-blue-100')
        // Previously selected should NOT have blue background
        expect(headers[0].classes()).not.toContain('bg-blue-100')
    })

    it('applies active class to selected element row', async () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'title')

        const wrapper = mount(BandTree)
        const elements = wrapper.findAll('[data-testid="band-element"]')
        await elements[0].trigger('click')

        expect(elements[0].classes()).toContain('bg-blue-50')
    })

    // ── Element rendering ─────────────────────

    it('renders element type badge', () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'title')

        const wrapper = mount(BandTree)
        expect(wrapper.text()).toContain('text')
    })

    it('shows text content preview', () => {
        const store = useDesignerStore()
        const elId = store.addElement('text', { x: 10, y: 10 }, 'title')
        store.updateElement(elId, {
            content: { type: 'text', text: 'Hello World' },
        })

        const wrapper = mount(BandTree)
        expect(wrapper.text()).toContain('Hello World')
    })

    it('shows page number format preview', () => {
        const store = useDesignerStore()
        store.addElement('page_number', { x: 10, y: 10 }, 'pageFooter')

        const wrapper = mount(BandTree)
        expect(wrapper.text()).toContain('Page {current} of {total}')
    })

    // ── Enable / Disable ──────────────────────

    it('starts with all bands enabled', () => {
        const store = useDesignerStore()
        const wrapper = mount(BandTree)
        // All default bands have enabled === true
        store.page.bands?.forEach(band => {
            expect(band.enabled).toBe(true)
        })
        // All headers should show the "●" (enabled) indicator
        expect(wrapper.text()).toContain('●')
    })

    it('toggles band enabled state when clicking enable toggle', async () => {
        const store = useDesignerStore()
        const wrapper = mount(BandTree)

        // Click the enable toggle on the first band
        const enableToggles = wrapper.findAll('[data-testid="band-enable-toggle"]')
        await enableToggles[0].trigger('click')

        // Band should now be disabled
        expect(store.page.bands![0].enabled).toBe(false)

        // Click again to re-enable
        await enableToggles[0].trigger('click')
        expect(store.page.bands![0].enabled).toBe(true)
    })

    it('shows line-through label for disabled band', async () => {
        const store = useDesignerStore()
        store.updateBand('title', { enabled: false })
        const wrapper = mount(BandTree)

        const headers = wrapper.findAll('[data-testid="band-header"]')
        expect(headers[0].text()).toContain('Title')
        expect(headers[0].classes()).toContain('text-gray-400')
    })

    it('disables element row interaction for disabled band', () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'detail')
        store.updateBand('detail', { enabled: false })

        const wrapper = mount(BandTree)
        const elements = wrapper.findAll('[data-testid="band-element"]')
        expect(elements.length).toBe(1)
        // Should have opacity-60 (disabled)
        expect(elements[0].classes()).toContain('opacity-60')
    })

    // ── Bands with mixed elements ─────────────

    it('shows elements across multiple bands', () => {
        const store = useDesignerStore()
        store.addElement('text', { x: 10, y: 10 }, 'title')
        store.addElement('image', { x: 10, y: 10 }, 'pageHeader')
        store.addElement('line', { x: 10, y: 10 }, 'detail')

        const wrapper = mount(BandTree)
        const elements = wrapper.findAll('[data-testid="band-element"]')
        expect(elements).toHaveLength(3)

        // Title has 1 element, pageHeader has 1, detail has 1
        const headers = wrapper.findAll('[data-testid="band-header"]')
        expect(headers[0].text()).toContain('1') // title
        expect(headers[1].text()).toContain('1') // pageHeader
        expect(headers[3].text()).toContain('1') // detail
        // Other bands should be empty
        expect(headers[2].text()).toContain('empty') // columnHeader
    })
})
