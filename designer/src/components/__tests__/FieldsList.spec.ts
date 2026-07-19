// ──────────────────────────────────────────────
// Toolreport Designer — FieldsList Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach, beforeAll } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import FieldsList from '../navigation/FieldsList.vue'

// ── jsdom polyfills ────────────────────────────

// jsdom does not provide DataTransfer — polyfill it.
beforeAll(() => {
    if (typeof window.DataTransfer !== 'undefined') return

    class DataTransferMock {
        data: Record<string, string> = {}
        effectAllowed = 'copy'
        dropEffect: 'copy' | 'link' | 'move' | 'none' = 'copy'
        files: File[] = []
        items: DataTransferItem[] = []

        setData(format: string, data: string): void {
            this.data[format] = data
        }
        getData(format: string): string {
            return this.data[format] ?? ''
        }
        clearData(format?: string): void {
            if (format) delete this.data[format]
            else this.data = {}
        }
        setDragImage(): void {}
    }
    ;(window as any).DataTransfer = DataTransferMock
})

// jsdom does not provide DragEvent — polyfill it.
beforeAll(() => {
    if (typeof window.DragEvent !== 'undefined') return

    class DragEventMock extends MouseEvent {
        dataTransfer: DataTransfer | null
        constructor(type: string, init?: DragEventInit) {
            super(type, init as MouseEventInit)
            this.dataTransfer = (init as any)?.dataTransfer ?? null
        }
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(window as any).DragEvent = DragEventMock
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(globalThis as any).DragEvent = DragEventMock
})

describe('FieldsList', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    // ── Empty state ───────────────────────────

    it('shows empty state message when no discoveredFields', () => {
        const wrapper = mount(FieldsList)
        expect(wrapper.text()).toContain('No fields discovered yet')
    })

    // ── Grouping by datasource ─────────────────

    it('groups fields by datasourceId', () => {
        const store = useDesignerStore()
        const id1 = store.addDatasource({
            name: 'Users API', url: 'https://api.com/users', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })
        const id2 = store.addDatasource({
            name: 'Orders API', url: 'https://api.com/orders', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })

        store.discoveredFields.push(
            { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: id1 },
            { name: 'total', path: 'total', type: 'number', level: 0, datasourceId: id2 },
        )

        const wrapper = mount(FieldsList)

        // Should have two group headers (in insertion order: Users then Orders)
        const groupHeaders = wrapper.findAll('[data-testid="ds-group-header"]')
        expect(groupHeaders.length).toBe(2)
        expect(groupHeaders[0].text()).toContain('Users API')
        expect(groupHeaders[1].text()).toContain('Orders API')
    })

    it('shows field count per datasource group', () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })

        store.discoveredFields.push(
            { name: 'a', path: 'a', type: 'string', level: 0, datasourceId: id },
            { name: 'b', path: 'b', type: 'string', level: 0, datasourceId: id },
        )

        const wrapper = mount(FieldsList)
        expect(wrapper.text()).toMatch(/2 fields/)
    })

    it('skips orphan fields whose datasource no longer exists', () => {
        const store = useDesignerStore()
        // Add an orphan field with a non-existent datasourceId
        store.discoveredFields.push(
            { name: 'orphan', path: 'orphan', type: 'string', level: 0, datasourceId: 'nonexistent' },
        )

        const wrapper = mount(FieldsList)
        // Should show empty state since no valid datasources
        expect(wrapper.text()).toContain('No fields discovered yet')
    })

    // ── Tree structure ─────────────────────────

    it('renders root-level primitives as draggable field entries', () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })

        store.discoveredFields.push(
            { name: 'Email', path: 'email', type: 'string', level: 0, datasourceId: id },
            { name: 'Count', path: 'count', type: 'number', level: 0, datasourceId: id },
        )

        const wrapper = mount(FieldsList)
        const entries = wrapper.findAll('[data-testid="field-entry"]')
        expect(entries).toHaveLength(2)
        // Root nodes sorted alphabetically by path: count < email
        expect(entries[0].text()).toContain('Count')
        expect(entries[1].text()).toContain('Email')
    })

    it('renders parent objects as non-draggable tree nodes with expand toggle', () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })

        store.discoveredFields.push(
            { name: 'Data', path: 'data', type: 'object', level: 0, datasourceId: id },
            { name: 'Email', path: 'email', type: 'string', level: 0, datasourceId: id },
            { name: 'City', path: 'data.city', type: 'string', level: 1, datasourceId: id },
        )

        const wrapper = mount(FieldsList)

        // Data is an object → not draggable → rendered as tree-node
        const treeNodes = wrapper.findAll('[data-testid="tree-node"]')
        expect(treeNodes).toHaveLength(1)
        expect(treeNodes[0].text()).toContain('Data')

        // Toggle button exists for Data
        expect(wrapper.find('[data-testid="tree-node-toggle"]').exists()).toBe(true)

        // Children are collapsed by default — not in the DOM
        const entriesCollapsed = wrapper.findAll('[data-testid="field-entry"]')
        expect(entriesCollapsed).toHaveLength(1) // only Email at root
        expect(entriesCollapsed[0].text()).toContain('Email')
    })

    it('expands children when toggle is clicked', async () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })

        store.discoveredFields.push(
            { name: 'Data', path: 'data', type: 'object', level: 0, datasourceId: id },
            { name: 'Email', path: 'email', type: 'string', level: 0, datasourceId: id },
            { name: 'City', path: 'data.city', type: 'string', level: 1, datasourceId: id },
        )

        const wrapper = mount(FieldsList)

        // Before expand: only Email is visible (Data is non-draggable tree-node, City collapsed)
        let before = wrapper.findAll('[data-testid="field-entry"]')
        expect(before).toHaveLength(1)
        expect(before[0].text()).toContain('Email')

        // Click toggle to expand Data
        const toggle = wrapper.find('[data-testid="tree-node-toggle"]')
        await toggle.trigger('click')

        // After expand: City (child of Data) appears.
        // DOM order: Data > City, then Email. So City comes first alphabetically.
        const after = wrapper.findAll('[data-testid="field-entry"]')
        expect(after).toHaveLength(2)
        expect(after[0].text()).toContain('City')
        expect(after[1].text()).toContain('Email')
    })

    it('sorts sibling nodes alphabetically by path', () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })

        store.discoveredFields.push(
            { name: 'Z', path: 'zeta', type: 'string', level: 0, datasourceId: id },
            { name: 'A', path: 'alpha', type: 'string', level: 0, datasourceId: id },
        )

        const wrapper = mount(FieldsList)
        const fieldEntries = wrapper.findAll('[data-testid="field-entry"]')
        expect(fieldEntries[0].text()).toContain('A') // alpha first
        expect(fieldEntries[1].text()).toContain('Z') // zeta second
    })

    // ── Drag data ──────────────────────────────

    it('sets field-path data on dragstart', async () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })
        store.discoveredFields.push(
            { name: 'email', path: 'data.email', type: 'string', level: 0, datasourceId: id },
        )

        const wrapper = mount(FieldsList)
        const fieldEl = wrapper.find('[data-testid="field-entry"]')

        // Build a DataTransfer and dispatch dragstart
        const dt = new DataTransfer()
        const event = new DragEvent('dragstart', {
            dataTransfer: dt,
            bubbles: true,
            cancelable: true,
        })

        await fieldEl.element.dispatchEvent(event)

        const fieldData = dt.getData('field-path')
        expect(fieldData).toBeTruthy()
        const parsed = JSON.parse(fieldData)
        expect(parsed.path).toBe('data.email')
        expect(parsed.name).toBe('email')
        expect(parsed.type).toBe('string')
    })

    // ── Type badge colors ──────────────────────

    it('renders a type badge for each field', () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })
        store.discoveredFields.push(
            { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: id },
        )

        const wrapper = mount(FieldsList)
        const badge = wrapper.find('[data-testid="field-type-badge"]')
        expect(badge.exists()).toBe(true)
        expect(badge.text()).toBe('string')
    })

    // ── Path display ───────────────────────────

    it('displays the field path in monospace', () => {
        const store = useDesignerStore()
        const id = store.addDatasource({
            name: 'API', url: 'https://api.com', method: 'GET',
            headers: {}, auth: { type: 'none' }, timeout: 5000,
        })
        store.discoveredFields.push(
            { name: 'email', path: 'data.user.email', type: 'string', level: 0, datasourceId: id },
        )

        const wrapper = mount(FieldsList)
        expect(wrapper.text()).toContain('data.user.email')
    })
})
