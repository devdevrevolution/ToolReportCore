// ──────────────────────────────────────────────
// Toolreport Designer — CompositeToolbar palette detach tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import CompositeToolbar from '../layout/CompositeToolbar.vue'

// Mock composables used by CompositeToolbar
vi.mock('@/composables/useApi', () => ({
    useApi: () => ({
        testDatasource: vi.fn().mockResolvedValue({ success: true, fields: [], status: 200 }),
    }),
    provideApiConfig: vi.fn(),
}))

// Mock useResize — prevents DOM measurement issues in tests
vi.mock('@/composables/useResize', () => ({
    useResize: () => ({
        onResizeStart: vi.fn(),
        isResizing: { value: false },
    }),
}))

describe('CompositeToolbar — Palette Detach', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('renders the detach button in Components header', () => {
        const wrapper = mount(CompositeToolbar)
        expect(wrapper.find('[data-testid="detach-palette-btn"]').exists()).toBe(true)
    })

    it('shows palette section when palette is docked', () => {
        const store = useDesignerStore()
        store.paletteDetached = false

        const wrapper = mount(CompositeToolbar)
        expect(wrapper.find('[data-testid="composite-palette"]').exists()).toBe(true)
    })

    it('hides palette section when palette is detached', () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(CompositeToolbar)
        expect(wrapper.find('[data-testid="composite-palette"]').exists()).toBe(false)
    })

    it('toggles paletteDetached when detach button is clicked', async () => {
        const store = useDesignerStore()
        expect(store.paletteDetached).toBe(false)

        const wrapper = mount(CompositeToolbar)
        await wrapper.find('[data-testid="detach-palette-btn"]').trigger('click')
        expect(store.paletteDetached).toBe(true)
    })

    it('still shows outline, datasources, and fields when palette is detached', () => {
        const store = useDesignerStore()
        store.paletteDetached = true

        const wrapper = mount(CompositeToolbar)
        expect(wrapper.text()).toContain('Outline')
        expect(wrapper.text()).toContain('Datasources')
        expect(wrapper.text()).toContain('Fields')
    })
})
