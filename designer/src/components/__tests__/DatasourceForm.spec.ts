// ──────────────────────────────────────────────
// Toolreport Designer — DatasourceForm Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import DatasourceForm from '../forms/DatasourceForm.vue'
import type { DatasourceConfig } from '@/types/designer'

/**
 * Helper: fill required fields and submit the form.
 * Optionally override specific field values.
 */
async function fillAndSave(
    wrapper: ReturnType<typeof mount>,
    overrides?: { name?: string; url?: string; timeout?: number },
) {
    const nameInput = wrapper.find<HTMLInputElement>('[data-testid="ds-name-input"]')
    const urlInput = wrapper.find<HTMLInputElement>('[data-testid="ds-url-input"]')

    await nameInput.setValue(overrides?.name ?? 'My API')
    await urlInput.setValue(overrides?.url ?? 'https://api.example.com/data')

    if (overrides?.timeout !== undefined) {
        const timeoutInput = wrapper.find<HTMLInputElement>('[data-testid="ds-timeout-input"]')
        await timeoutInput.setValue(overrides.timeout)
    }

    await wrapper.find('[data-testid="ds-save-btn"]').trigger('click')
}

describe('DatasourceForm', () => {
    // ── Save / Cancel emission ────────────────

    it('emits save with correct data when form is valid', async () => {
        const wrapper = mount(DatasourceForm)

        await wrapper.find<HTMLInputElement>('[data-testid="ds-name-input"]').setValue('My API')
        await wrapper.find<HTMLInputElement>('[data-testid="ds-url-input"]').setValue('https://api.example.com/data')
        await wrapper.find('[data-testid="ds-save-btn"]').trigger('click')

        const emitted = wrapper.emitted('save')
        expect(emitted).toBeTruthy()
        const payload = emitted![0][0] as Omit<DatasourceConfig, 'id' | 'lastError'>
        expect(payload.name).toBe('My API')
        expect(payload.url).toBe('https://api.example.com/data')
        expect(payload.method).toBe('GET')
        expect(payload.auth).toEqual({ type: 'none' })
        expect(payload.timeout).toBe(5000)
        expect(payload.headers).toEqual({})
    })

    it('emits cancel when cancel button is clicked', async () => {
        const wrapper = mount(DatasourceForm)
        await wrapper.find('[data-testid="ds-cancel-btn"]').trigger('click')
        expect(wrapper.emitted('cancel')).toBeTruthy()
    })

    // ── Validation ─────────────────────────────

    it('shows validation errors for empty required fields', async () => {
        const wrapper = mount(DatasourceForm)
        await wrapper.find('[data-testid="ds-save-btn"]').trigger('click')

        expect(wrapper.text()).toContain('Name is required')
        expect(wrapper.text()).toContain('URL is required')
    })

    it('does not emit save when validation fails', async () => {
        const wrapper = mount(DatasourceForm)
        await wrapper.find('[data-testid="ds-save-btn"]').trigger('click')

        expect(wrapper.emitted('save')).toBeFalsy()
    })

    it('validates URL is HTTP(S)', async () => {
        const wrapper = mount(DatasourceForm)

        await wrapper.find<HTMLInputElement>('[data-testid="ds-name-input"]').setValue('Test')
        await wrapper.find<HTMLInputElement>('[data-testid="ds-url-input"]').setValue('not-a-valid-url')
        await wrapper.find('[data-testid="ds-save-btn"]').trigger('click')

        expect(wrapper.text()).toContain('Must be a valid HTTP(S) URL')
    })

    it('validates timeout lower bound', async () => {
        const wrapper = mount(DatasourceForm)
        await fillAndSave(wrapper, { timeout: 500 })

        expect(wrapper.text()).toContain('Timeout must be 1000-60000ms')
    })

    it('validates timeout upper bound', async () => {
        const wrapper = mount(DatasourceForm)
        await fillAndSave(wrapper, { timeout: 61000 })

        expect(wrapper.text()).toContain('Timeout must be 1000-60000ms')
    })

    it('accepts timeout boundary values', async () => {
        const wrapper = mount(DatasourceForm)
        await fillAndSave(wrapper, { timeout: 1000 })

        // Should pass validation
        expect(wrapper.emitted('save')).toBeTruthy()
    })

    // ── Pre-fill (edit mode) ───────────────────

    it('pre-fills form when editing an existing datasource', () => {
        const datasource: DatasourceConfig = {
            id: 'ds-1',
            name: 'Existing API',
            url: 'https://api.example.com',
            method: 'POST',
            headers: { 'X-API-Key': 'secret' },
            auth: { type: 'bearer', token: 'tok123' },
            timeout: 30000,
            lastError: null,
        }

        const wrapper = mount(DatasourceForm, {
            props: { datasource },
        })

        const nameEl = wrapper.find<HTMLInputElement>('[data-testid="ds-name-input"]').element
        expect(nameEl.value).toBe('Existing API')

        const urlEl = wrapper.find<HTMLInputElement>('[data-testid="ds-url-input"]').element
        expect(urlEl.value).toBe('https://api.example.com')

        const methodEl = wrapper.find<HTMLSelectElement>('[data-testid="ds-method-select"]').element
        expect(methodEl.value).toBe('POST')
    })

    it('pre-fills auth token when auth type is bearer', () => {
        const datasource: DatasourceConfig = {
            id: 'ds-1',
            name: 'Secured API',
            url: 'https://api.example.com',
            method: 'GET',
            headers: {},
            auth: { type: 'bearer', token: 'my-token' },
            timeout: 5000,
            lastError: null,
        }

        const wrapper = mount(DatasourceForm, {
            props: { datasource },
        })

        const tokenEl = wrapper.find<HTMLInputElement>('[data-testid="ds-auth-token-input"]')
        expect(tokenEl.exists()).toBe(true)
        expect(tokenEl.element.value).toBe('my-token')
    })

    // ── Dynamic headers ────────────────────────

    it('renders one empty header row by default when creating', () => {
        const wrapper = mount(DatasourceForm)
        const rows = wrapper.findAll('[data-testid="ds-header-row"]')
        expect(rows.length).toBe(1)
    })

    it('adds a new header row when clicking add header button', async () => {
        const wrapper = mount(DatasourceForm)
        await wrapper.find('[data-testid="ds-add-header-btn"]').trigger('click')
        expect(wrapper.findAll('[data-testid="ds-header-row"]').length).toBe(2)
    })

    it('removes a header row when clicking remove header button', async () => {
        const wrapper = mount(DatasourceForm)
        await wrapper.find('[data-testid="ds-add-header-btn"]').trigger('click')
        expect(wrapper.findAll('[data-testid="ds-header-row"]').length).toBe(2)

        const removeBtns = wrapper.findAll('[data-testid="ds-remove-header-btn"]')
        await removeBtns[0].trigger('click')
        expect(wrapper.findAll('[data-testid="ds-header-row"]').length).toBe(1)
    })

    // ── Method selection ───────────────────────

    it('shows GET and POST as method options', () => {
        const wrapper = mount(DatasourceForm)
        const methodSelect = wrapper.find<HTMLSelectElement>('[data-testid="ds-method-select"]')
        const options = methodSelect.findAll('option').map(o => o.text())
        expect(options).toContain('GET')
        expect(options).toContain('POST')
    })
})
