// ──────────────────────────────────────────────
// Toolreport Designer — DesignerShell Unit Tests
// ──────────────────────────────────────────────
//
// Approach: mock `@/api/client` so `createApiClient` returns a controllable
// fake client. `provideApiConfig()` (composables/useApi) calls
// `createApiClient(config)` and stores the result as the singleton, so
// mocking the factory is the single injection point.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { ApiClient, PdfTemplate } from '@/api/types'

// ── Fake API client ──────────────────────────────
let fakeGetTemplate: ((id: number) => Promise<PdfTemplate>) | null = null

vi.mock('@/api/client', () => ({
    createApiClient: (): ApiClient => ({
        getTemplates: vi.fn(),
        getTemplate: (id: number) => fakeGetTemplate!(id),
        createTemplate: vi.fn(),
        updateTemplate: vi.fn(),
        deleteTemplate: vi.fn(),
        generatePdf: vi.fn(),
        getDocuments: vi.fn(),
        getDownloadUrl: vi.fn(),
        testDatasource: vi.fn(),
    }),
}))

// Import AFTER vi.mock so the factory is used.
import DesignerShell from '@/components/shell/DesignerShell.vue'

function makeTemplate(id: number, engine: 'dompdf' | 'pdf-engine' | null): PdfTemplate {
    return {
        id,
        name: `Template ${id}`,
        slug: `template-${id}`,
        description: null,
        page: {},
        config: {},
        is_active: true,
        engine: engine ?? undefined,
        element_count: 0,
        created_at: '',
        updated_at: '',
    } as PdfTemplate
}

describe('DesignerShell', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        fakeGetTemplate = null
    })

    // ── New mode (templateId = null) ───────────

    it('shows EngineSelectModal in new mode', () => {
        const wrapper = mount(DesignerShell, {
            props: { templateId: null },
        })
        expect(wrapper.find('[data-testid="engine-select-modal"]').exists()).toBe(true)
    })

    it('mounts CompositeDesigner after confirming pdf-engine', async () => {
        const wrapper = mount(DesignerShell, {
            props: { templateId: null },
        })

        // Select pdf-engine and confirm
        await wrapper.find('[data-testid="engine-card-pdf-engine"]').trigger('click')
        await wrapper.find('[data-testid="engine-select-confirm"]').trigger('click')

        // CompositeDesigner hosts CompositeToolbar
        expect(wrapper.find('[data-testid="composite-toolbar"]').exists()).toBe(true)
    })

    it('mounts PdfDesigner after confirming dompdf', async () => {
        const wrapper = mount(DesignerShell, {
            props: { templateId: null },
        })

        await wrapper.find('[data-testid="engine-card-dompdf"]').trigger('click')
        await wrapper.find('[data-testid="engine-select-confirm"]').trigger('click')

        // PdfDesigner hosts DesignerToolbar
        expect(wrapper.find('[data-testid="designer-toolbar"]').exists()).toBe(true)
    })

    it('sets store engine on confirm', async () => {
        const store = useDesignerStore()
        const wrapper = mount(DesignerShell, {
            props: { templateId: null },
        })

        await wrapper.find('[data-testid="engine-card-pdf-engine"]').trigger('click')
        await wrapper.find('[data-testid="engine-select-confirm"]').trigger('click')

        expect(store.engine).toBe('pdf-engine')
    })

    // ── Edit mode (templateId provided) ───────

    it('shows loading state then mounts CompositeDesigner when engine is pdf-engine', async () => {
        fakeGetTemplate = vi.fn().mockResolvedValue(makeTemplate(122, 'pdf-engine'))

        const wrapper = mount(DesignerShell, {
            props: { templateId: '122' },
        })

        // Loading initially
        expect(wrapper.text()).toContain('Loading')

        await flushPromises()

        // CompositeDesigner mounted
        expect(wrapper.find('[data-testid="composite-toolbar"]').exists()).toBe(true)
        expect(fakeGetTemplate).toHaveBeenCalledWith(122)
    })

    it('mounts PdfDesigner when backend engine is dompdf', async () => {
        fakeGetTemplate = vi.fn().mockResolvedValue(makeTemplate(42, 'dompdf'))

        const wrapper = mount(DesignerShell, {
            props: { templateId: '42' },
        })

        await flushPromises()

        expect(wrapper.find('[data-testid="designer-toolbar"]').exists()).toBe(true)
    })

    it('defaults to PdfDesigner when backend engine is null', async () => {
        fakeGetTemplate = vi.fn().mockResolvedValue(makeTemplate(7, null))

        const wrapper = mount(DesignerShell, {
            props: { templateId: '7' },
        })

        await flushPromises()

        expect(wrapper.find('[data-testid="designer-toolbar"]').exists()).toBe(true)
    })

    it('shows error state when getTemplate fails', async () => {
        fakeGetTemplate = vi.fn().mockRejectedValue(new Error('network'))

        const wrapper = mount(DesignerShell, {
            props: { templateId: '999' },
        })

        await flushPromises()

        expect(wrapper.text()).toContain('Failed to load template')
    })
})