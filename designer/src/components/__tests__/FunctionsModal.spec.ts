// ──────────────────────────────────────────────
// Toolreport Designer — FunctionsModal Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import FunctionsModal from '../modals/FunctionsModal.vue'

describe('FunctionsModal', () => {
    let wrapper: ReturnType<typeof mount>

    function openModal(): void {
        wrapper = mount(FunctionsModal, {
            props: { modelValue: true },
            global: {
                // Disable Teleport so content renders inside the wrapper
                stubs: { Teleport: true },
            },
        })
    }

    beforeEach(() => {
        openModal()
    })

    afterEach(() => {
        wrapper?.unmount()
    })

    // ── Rendering ────────────────────────────────

    it('renders the modal when modelValue is true', () => {
        expect(wrapper.find('.fixed.inset-0').exists()).toBe(true)
    })

    it('displays all function categories as tabs', () => {
        const categories = ['Math', 'Text', 'Date', 'Logic', 'Formatting']
        const tabs = wrapper.findAll('button')
        const tabTexts = tabs.map(t => t.text())
        for (const cat of categories) {
            expect(tabTexts.some(t => t.includes(cat))).toBe(true)
        }
    })

    it('shows functions for the default Math category', () => {
        const fnCards = wrapper.findAll('.cursor-pointer.rounded.border')
        expect(fnCards.length).toBeGreaterThan(0)
        expect(wrapper.text()).toContain('SUM')
    })

    // ── Search ──────────────────────────────────

    it('filters functions by name when searching', async () => {
        const input = wrapper.find('input[placeholder="Search functions..."]')
        await input.setValue('round')
        expect(wrapper.text()).toContain('ROUND')
    })

    it('shows empty message when no functions match search', async () => {
        const input = wrapper.find('input[placeholder="Search functions..."]')
        await input.setValue('zzzznonexistent')
        expect(wrapper.text()).toContain('No functions match your search')
    })

    // ── Category tabs ────────────────────────────

    it('switches category when clicking a tab', async () => {
        const textTab = wrapper.findAll('button').find(b => b.text().includes('Text'))
        expect(textTab).toBeTruthy()
        await textTab!.trigger('click')
        expect(wrapper.text()).toContain('UPPER')
    })

    // ── Function selection ───────────────────────

    it('emits select with function syntax when clicking a function', async () => {
        const fnCards = wrapper.findAll('.cursor-pointer.rounded.border')
        expect(fnCards.length).toBeGreaterThan(0)
        await fnCards[0].trigger('click')
        expect(wrapper.emitted('select')).toBeTruthy()
        const emitted = wrapper.emitted('select')!
        expect(emitted[0][0]).toContain('SUM')
    })

    it('emits update:modelValue false when closing', async () => {
        const closeBtn = wrapper.findAll('button').find(b => b.text().includes('\u2715'))
        expect(closeBtn).toBeTruthy()
        await closeBtn!.trigger('click')
        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
        expect(wrapper.emitted('update:modelValue')![0][0]).toBe(false)
    })

    // ── Search across categories ─────────────────

    it('search shows results across all categories', async () => {
        const input = wrapper.find('input[placeholder="Search functions..."]')
        await input.setValue('clamp')
        expect(wrapper.text()).toContain('CLAMP')
    })
})
