// ──────────────────────────────────────────────
// Toolreport Designer — FloatingPalette tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import FloatingPalette from '../layout/FloatingPalette.vue'

// Mock useApi — needed by store sub-calls
vi.mock('@/composables/useApi', () => ({
    useApi: () => ({
        testDatasource: vi.fn().mockResolvedValue({ success: true, fields: [], status: 200 }),
    }),
    provideApiConfig: vi.fn(),
}))

describe('FloatingPalette', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    afterEach(() => {
        // Clean up any teleported content from body
        document.body.innerHTML = ''
    })

    it('does not render when palette is docked', () => {
        const store = useDesignerStore()
        store.paletteDetached = false

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        expect(wrapper.find('[data-testid="floating-palette"]').exists()).toBe(false)
        wrapper.unmount()
    })

    it('renders when palette is detached', () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        expect(document.querySelector('[data-testid="floating-palette"]')).not.toBeNull()
        wrapper.unmount()
    })

    it('displays all 6 component types', () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        const expectedTypes = ['VBox', 'HBox', 'Label', 'Shape', 'Table', 'Image']
        for (const type of expectedTypes) {
            expect(document.querySelector(`[data-testid="float-add-${type}"]`)).not.toBeNull()
        }
        wrapper.unmount()
    })

    it('renders the dock button', () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        expect(document.querySelector('[data-testid="floating-palette-dock"]')).not.toBeNull()
        wrapper.unmount()
    })

    it('docks palette when dock button is clicked', async () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        const dockBtn = document.querySelector('[data-testid="floating-palette-dock"]') as HTMLElement
        dockBtn.click()
        await wrapper.vm.$nextTick()

        expect(store.paletteDetached).toBe(false)
        wrapper.unmount()
    })

    it('adds composite node when component button is clicked', async () => {
        const store = useDesignerStore()
        store.paletteDetached = true
        store.selectedBandId = 'detail'

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        const btn = document.querySelector('[data-testid="float-add-Label"]') as HTMLElement
        btn.click()
        await wrapper.vm.$nextTick()

        const band = store.page.bands!.find(b => b.id === 'detail')!
        expect(band.children).toHaveLength(1)
        const root = band.children![0] as any
        expect(root.node.type).toBe('Label')
        wrapper.unmount()
    })

    it('applies palette position from store', () => {
        const store = useDesignerStore()
        store.paletteDetached = true
        store.palettePosition = { x: 150, y: 300 }

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        const palette = document.querySelector('[data-testid="floating-palette"]') as HTMLElement
        expect(palette.getAttribute('style')).toContain('left: 150px')
        expect(palette.getAttribute('style')).toContain('top: 300px')
        wrapper.unmount()
    })

    it('sets composite-node-type on dragstart', async () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(FloatingPalette, { attachTo: document.body })
        const btn = document.querySelector('[data-testid="float-add-VBox"]') as HTMLElement

        const setDataMock = vi.fn()
        const dragStartEvent = new Event('dragstart', { bubbles: true }) as any
        dragStartEvent.dataTransfer = { setData: setDataMock, effectAllowed: '' }
        btn.dispatchEvent(dragStartEvent)

        expect(setDataMock).toHaveBeenCalledWith('composite-node-type', 'VBox')
        expect(dragStartEvent.dataTransfer.effectAllowed).toBe('copy')
        wrapper.unmount()
    })
})
