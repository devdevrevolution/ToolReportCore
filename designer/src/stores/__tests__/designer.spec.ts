// ──────────────────────────────────────────────
// Toolreport Designer — Pinia Store Unit Tests
// ──────────────────────────────────────────────

import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, beforeEach } from 'vitest'
import { useDesignerStore } from '../designer'
import type { DatasourceConfig, DiscoveredField, ReportBand, BandAnchor, DesignerElement } from '@/types/designer'
import type { PdfTemplate } from '@/api/types'

function makeTemplate(overrides?: Partial<PdfTemplate>): PdfTemplate {
    return {
        id: 1,
        name: 'Test Template',
        slug: 'test-template',
        description: null,
        page: {},
        config: {},
        is_active: true,
        element_count: 0,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        ...overrides,
    }
}

describe('Designer Store — Datasources', () => {
    let store: ReturnType<typeof useDesignerStore>

    beforeEach(() => {
        setActivePinia(createPinia())
        store = useDesignerStore()
    })

    // ── State initialisation ──────────────────

    it('initialises datasources and discoveredFields as empty arrays', () => {
        expect(store.datasources).toEqual([])
        expect(store.discoveredFields).toEqual([])
    })

    // ── addDatasource ─────────────────────────

    describe('addDatasource', () => {
        it('adds a datasource with a generated UUID and sets isDirty', () => {
            const input = {
                name: 'My API',
                url: 'https://api.example.com/data',
                method: 'GET' as const,
                headers: { Accept: 'application/json' },
                auth: { type: 'none' as const },
                timeout: 10000,
            }

            const id = store.addDatasource(input)

            expect(id).toBeDefined()
            expect(typeof id).toBe('string')
            expect(id.length).toBeGreaterThan(0)
            expect(store.datasources).toHaveLength(1)
            expect(store.datasources[0]).toMatchObject({
                ...input,
                id,
                lastError: null,
            })
            expect(store.isDirty).toBe(true)
        })

        // ── 7.2 Edge case: UUID v4 format ────────

        it('7.2 addDatasource generates valid UUID v4', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })

            // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
            const uuidV4Regex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
            expect(id).toMatch(uuidV4Regex)
        })
    })

    // ── updateDatasource ──────────────────────

    describe('updateDatasource', () => {
        it('merges partial patch and sets isDirty', () => {
            const id = store.addDatasource({
                name: 'My API',
                url: 'https://api.example.com/data',
                method: 'GET',
                headers: {},
                auth: { type: 'none' },
                timeout: 10000,
            })
            store.isDirty = false

            store.updateDatasource(id, { name: 'Updated API', timeout: 30000 })

            expect(store.datasources[0].name).toBe('Updated API')
            expect(store.datasources[0].timeout).toBe(30000)
            expect(store.datasources[0].url).toBe('https://api.example.com/data')
            expect(store.isDirty).toBe(true)
        })

        it('no-ops gracefully for a non-existent id', () => {
            // Should not throw
            store.updateDatasource('non-existent', { name: 'Nope' })
            expect(store.datasources).toHaveLength(0)
        })
    })

    // ── removeDatasource ──────────────────────

    describe('removeDatasource', () => {
        it('removes the datasource and its associated discoveredFields', () => {
            const id = store.addDatasource({
                name: 'API',
                url: 'https://api.example.com',
                method: 'GET',
                headers: {},
                auth: { type: 'none' },
                timeout: 10000,
            })
            store.setDiscoveredFields(id, [
                { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: id },
            ])
            expect(store.datasources).toHaveLength(1)
            expect(store.discoveredFields).toHaveLength(1)
            store.isDirty = false

            store.removeDatasource(id)

            expect(store.datasources).toHaveLength(0)
            expect(store.discoveredFields).toHaveLength(0)
            expect(store.isDirty).toBe(true)
        })

        it('no-ops gracefully for a non-existent id', () => {
            store.removeDatasource('non-existent')
            expect(store.datasources).toHaveLength(0)
        })

        it('preserves discoveredFields from other datasources', () => {
            const id1 = store.addDatasource({
                name: 'API 1',
                url: 'https://api1.example.com',
                method: 'GET',
                headers: {},
                auth: { type: 'none' },
                timeout: 10000,
            })
            const id2 = store.addDatasource({
                name: 'API 2',
                url: 'https://api2.example.com',
                method: 'GET',
                headers: {},
                auth: { type: 'none' },
                timeout: 10000,
            })
            store.setDiscoveredFields(id1, [
                { name: 'f1', path: 'f1', type: 'string', level: 0, datasourceId: id1 },
            ])
            store.setDiscoveredFields(id2, [
                { name: 'f2', path: 'f2', type: 'string', level: 0, datasourceId: id2 },
            ])
            expect(store.discoveredFields).toHaveLength(2)

            store.removeDatasource(id1)

            expect(store.discoveredFields).toHaveLength(1)
            expect(store.discoveredFields[0].datasourceId).toBe(id2)
        })
    })

    // ── setDiscoveredFields ───────────────────

    describe('setDiscoveredFields', () => {
        it('replaces fields for a given datasource ID', () => {
            const id = store.addDatasource({
                name: 'API',
                url: 'https://api.example.com',
                method: 'GET',
                headers: {},
                auth: { type: 'none' },
                timeout: 10000,
            })

            store.setDiscoveredFields(id, [
                { name: 'a', path: 'a', type: 'string', level: 0, datasourceId: id },
            ])
            expect(store.discoveredFields).toHaveLength(1)

            // Replace with different set
            store.setDiscoveredFields(id, [
                { name: 'x', path: 'x', type: 'number', level: 0, datasourceId: id },
                { name: 'y', path: 'y', type: 'boolean', level: 1, datasourceId: id },
            ])
            expect(store.discoveredFields).toHaveLength(2)
            expect(store.isDirty).toBe(true)
        })

        it('keeps fields from other datasources intact when replacing', () => {
            const id1 = store.addDatasource({
                name: 'API 1', url: 'https://a.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            const id2 = store.addDatasource({
                name: 'API 2', url: 'https://b.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })

            store.setDiscoveredFields(id1, [
                { name: 'f1', path: 'f1', type: 'string', level: 0, datasourceId: id1 },
            ])
            store.setDiscoveredFields(id2, [
                { name: 'f2', path: 'f2', type: 'string', level: 0, datasourceId: id2 },
            ])

            store.setDiscoveredFields(id1, [
                { name: 'f1-new', path: 'f1-new', type: 'number', level: 1, datasourceId: id1 },
            ])

            expect(store.discoveredFields).toHaveLength(2)
            // id2 field should still be first (it wasn't touched)
            expect(store.discoveredFields.find(f => f.datasourceId === id2)).toBeDefined()
            expect(store.discoveredFields.find(f => f.datasourceId === id1)?.name).toBe('f1-new')
        })
    })

    // ── setDatasourceTestResult ────────────────

    describe('setDatasourceTestResult', () => {
        it('on success: clears lastError and populates discoveredFields', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            store.datasources[0].lastError = 'Previous error'
            store.isDirty = false

            store.setDatasourceTestResult(id, {
                success: true,
                fields: [
                    { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: id },
                ],
            })

            expect(store.datasources[0].lastError).toBeNull()
            expect(store.discoveredFields).toHaveLength(1)
            expect(store.discoveredFields[0].name).toBe('email')
            expect(store.isDirty).toBe(true)
        })

        it('on failure: sets lastError and removes stale discoveredFields', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            store.setDiscoveredFields(id, [
                { name: 'stale', path: 'stale', type: 'string', level: 0, datasourceId: id },
            ])
            store.isDirty = false

            store.setDatasourceTestResult(id, {
                success: false,
                error: 'Connection refused',
            })

            expect(store.datasources[0].lastError).toBe('Connection refused')
            expect(store.discoveredFields).toHaveLength(0)
            expect(store.isDirty).toBe(true)
        })

        it('uses fallback error message when none provided on failure', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })

            store.setDatasourceTestResult(id, { success: false })

            expect(store.datasources[0].lastError).toBe('Unknown error')
        })

        it('no-ops for a non-existent datasource id', () => {
            expect(() =>
                store.setDatasourceTestResult('non-existent', { success: true }),
            ).not.toThrow()
            expect(store.isDirty).toBe(false)
        })
    })

    // ── loadTemplate ──────────────────────────

    describe('loadTemplate — datasource restoration', () => {
        it('restores datasources and discoveredFields from config', () => {
            const template = makeTemplate({
                config: {
                    datasources: [
                        { id: 'ds-1', name: 'API', url: 'https://api.example.com', method: 'GET', headers: {}, auth: { type: 'none' }, timeout: 10000, lastError: null },
                    ],
                    discoveredFields: [
                        { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: 'ds-1' },
                    ],
                } as unknown as Record<string, unknown>,
            })

            store.loadTemplate(template)

            expect(store.datasources).toHaveLength(1)
            expect(store.datasources[0].name).toBe('API')
            expect(store.discoveredFields).toHaveLength(1)
            expect(store.discoveredFields[0].name).toBe('email')
        })

        it('handles legacy templates without datasources key gracefully', () => {
            const template = makeTemplate({ config: {} })

            expect(() => store.loadTemplate(template)).not.toThrow()
            expect(store.datasources).toEqual([])
            expect(store.discoveredFields).toEqual([])
        })

        // ── 7.1 Backward compatibility ────────────

        it('7.1 loadTemplate with legacy data (no datasources) — config missing datasources key', () => {
            const template = makeTemplate({
                config: {
                    discoveredFields: [
                        { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: 'ds-1' },
                    ],
                } as unknown as Record<string, unknown>,
            })

            store.loadTemplate(template)

            // datasources should default to empty array
            expect(store.datasources).toEqual([])
            // discoveredFields should still be loaded
            expect(store.discoveredFields).toHaveLength(1)
        })

        it('7.1 loadTemplate with legacy data (no discoveredFields) — config missing discoveredFields key', () => {
            const template = makeTemplate({
                config: {
                    datasources: [
                        { id: 'ds-1', name: 'API', url: 'https://api.example.com', method: 'GET', headers: {}, auth: { type: 'none' }, timeout: 10000, lastError: null },
                    ],
                } as unknown as Record<string, unknown>,
            })

            store.loadTemplate(template)

            // datasources should still be loaded
            expect(store.datasources).toHaveLength(1)
            // discoveredFields should default to empty array
            expect(store.discoveredFields).toEqual([])
        })
    })

    // ── saveTemplate ──────────────────────────

    describe('saveTemplate — datasource persistence', () => {
        it('includes datasources and discoveredFields when non-empty', () => {
            const id = store.addDatasource({
                name: 'My API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            store.setDiscoveredFields(id, [
                { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: id },
            ])

            const payload = store.saveTemplate()
            const config = payload.config as unknown as Record<string, unknown>

            expect(config.datasources).toBeDefined()
            expect(Array.isArray(config.datasources)).toBe(true)
            expect((config.datasources as DatasourceConfig[])).toHaveLength(1)

            expect(config.discoveredFields).toBeDefined()
            expect(Array.isArray(config.discoveredFields)).toBe(true)
            expect((config.discoveredFields as DiscoveredField[])).toHaveLength(1)
        })

        it('omits datasources and discoveredFields when empty arrays', () => {
            // No datasources added — should not appear in config
            const payload = store.saveTemplate()
            const config = payload.config as unknown as Record<string, unknown>

            expect(config.datasources).toBeUndefined()
            expect(config.discoveredFields).toBeUndefined()
        })

        // ── 7.1 Backward compatibility ────────────

        it('7.1 saveTemplate omits empty datasources from payload config', () => {
            // Add datasources but leave discoveredFields empty
            store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })

            const payload = store.saveTemplate()
            const config = payload.config as unknown as Record<string, unknown>

            // datasources should be present (non-empty)
            expect(config.datasources).toBeDefined()
            expect(Array.isArray(config.datasources)).toBe(true)
            // discoveredFields should be absent (empty)
            expect(config.discoveredFields).toBeUndefined()
        })

        it('7.1 saveTemplate omits discoveredFields when empty', () => {
            const payload = store.saveTemplate()
            const config = payload.config as unknown as Record<string, unknown>

            expect(config.datasources).toBeUndefined()
            expect(config.discoveredFields).toBeUndefined()
        })
    })

    // ── 7.2 Edge cases ─────────────────────────

    describe('7.2 Edge cases', () => {
        // UUID format already tested in addDatasource block above
        // The following edge cases are covered by existing tests and are
        // explicitly re-stated here for regression tracking.

        it('7.2 addDatasource generates valid UUID v4', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            expect(id).toMatch(
                /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
            )
        })

        it('7.2 removeDatasource cascades to discoveredFields', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            store.setDiscoveredFields(id, [
                { name: 'f1', path: 'f1', type: 'string', level: 0, datasourceId: id },
            ])
            expect(store.discoveredFields).toHaveLength(1)

            store.removeDatasource(id)
            expect(store.datasources).toHaveLength(0)
            expect(store.discoveredFields).toHaveLength(0)
        })

        it('7.2 updateDatasource merges partial config without clearing other fields', () => {
            const id = store.addDatasource({
                name: 'Original', url: 'https://api.example.com', method: 'GET',
                headers: { 'X-Custom': 'val' }, auth: { type: 'none' }, timeout: 10000,
            })
            store.isDirty = false

            store.updateDatasource(id, { name: 'Updated', timeout: 30000 })

            expect(store.datasources[0].name).toBe('Updated')
            expect(store.datasources[0].timeout).toBe(30000)
            // Other fields preserve their values
            expect(store.datasources[0].url).toBe('https://api.example.com')
            expect(store.datasources[0].method).toBe('GET')
            expect(store.datasources[0].headers).toEqual({ 'X-Custom': 'val' })
            expect(store.isDirty).toBe(true)
        })

        it('7.2 removeDatasource preserves other datasources', () => {
            const id1 = store.addDatasource({
                name: 'API 1', url: 'https://a.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 5000,
            })
            const id2 = store.addDatasource({
                name: 'API 2', url: 'https://b.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 5000,
            })
            expect(store.datasources).toHaveLength(2)

            store.removeDatasource(id1)

            expect(store.datasources).toHaveLength(1)
            expect(store.datasources[0].id).toBe(id2)
        })

        it('7.2 setDiscoveredFields replaces existing fields for same datasource', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })

            store.setDiscoveredFields(id, [
                { name: 'old', path: 'old', type: 'string', level: 0, datasourceId: id },
            ])
            expect(store.discoveredFields).toHaveLength(1)

            store.setDiscoveredFields(id, [
                { name: 'new', path: 'new', type: 'number', level: 1, datasourceId: id },
            ])
            expect(store.discoveredFields).toHaveLength(1)
            expect(store.discoveredFields[0].name).toBe('new')
        })

        it('7.2 setDatasourceTestResult failure stores error and removes fields', () => {
            const id = store.addDatasource({
                name: 'API', url: 'https://api.example.com', method: 'GET',
                headers: {}, auth: { type: 'none' }, timeout: 10000,
            })
            store.setDiscoveredFields(id, [
                { name: 'stale', path: 'stale', type: 'string', level: 0, datasourceId: id },
            ])
            expect(store.discoveredFields).toHaveLength(1)

            store.setDatasourceTestResult(id, { success: false, error: 'Connection timeout' })

            expect(store.datasources[0].lastError).toBe('Connection timeout')
            expect(store.discoveredFields).toHaveLength(0)
            expect(store.isDirty).toBe(true)
        })
    })

    // ── Bands ───────────────────────────────────

    describe('Designer Store — Bands', () => {
        let store: ReturnType<typeof useDesignerStore>

        beforeEach(() => {
            setActivePinia(createPinia())
            store = useDesignerStore()
        })

        describe('initial state', () => {
            it('initialises with DEFAULT_BANDS in page.bands', () => {
                expect(store.page.bands).toBeDefined()
                expect(Array.isArray(store.page.bands)).toBe(true)
            })

            it('has all 7 band types present', () => {
                const types = store.page.bands!.map(b => b.type)
                expect(types).toEqual([
                    'title', 'pageHeader', 'columnHeader', 'detail',
                    'columnFooter', 'summary', 'pageFooter',
                ])
            })

            it('initialises selectedBandId as null', () => {
                expect(store.selectedBandId).toBeNull()
            })

            it('initialises all bands with enabled=true', () => {
                for (const band of store.page.bands!) {
                    expect(band.enabled).toBe(true)
                }
            })

            it('hydrates enabled from template data, defaulting to true', () => {
                const template = {
                    id: 2, name: 'Test', slug: 'test', description: null,
                    page: {
                        bands: [
                            { id: 'title', type: 'title', anchor: 'top', label: 'Title', height: 20, elements: [] },
                            { id: 'detail', type: 'detail', anchor: 'fill', label: 'Detail', height: 120, enabled: false, elements: [] },
                        ],
                    } as unknown as Record<string, unknown>,
                    config: {},
                    is_active: true, element_count: 0,
                    created_at: '', updated_at: '',
                }
                store.loadTemplate(template)

                const title = store.page.bands!.find(b => b.id === 'title')!
                const detail = store.page.bands!.find(b => b.id === 'detail')!
                // title has no enabled in template → defaults to true
                expect(title.enabled).toBe(true)
                // detail has enabled: false → stays false
                expect(detail.enabled).toBe(false)
            })
        })

        describe('selectBand', () => {
            it('sets selectedBandId to the given band id', () => {
                store.selectBand('detail')
                expect(store.selectedBandId).toBe('detail')
            })

            it('sets selectedBandId to null when called with null', () => {
                store.selectBand('title')
                store.selectBand(null)
                expect(store.selectedBandId).toBeNull()
            })
        })

        describe('setBandHeight', () => {
            it('updates the band height and sets isDirty', () => {
                const detailBand = store.page.bands!.find(b => b.type === 'detail')!
                const originalHeight = detailBand.height

                store.setBandHeight(detailBand.id, 250)

                // Clamped to printable area: 297 - 10(top) - 10(bottom) - sum(other 6 bands)
                // other bands: 20+12+10+10+12+20 = 84
                // max = 277 - 84 = 193
                expect(detailBand.height).toBe(193)
                expect(detailBand.height).not.toBe(originalHeight)
                expect(store.isDirty).toBe(true)
            })

            it('clamps to printable area limit when other bands fill most of the page', () => {
                const titleBand = store.page.bands!.find(b => b.type === 'title')!
                // Set title to max possible: 277 - (12+10+120+10+12+20) = 277 - 184 = 93
                store.setBandHeight(titleBand.id, 999)
                expect(titleBand.height).toBe(93)
            })

            it('does not clamp disabled bands against the total', () => {
                const summaryBand = store.page.bands!.find(b => b.type === 'summary')!
                const pageHeaderBand = store.page.bands!.find(b => b.type === 'pageHeader')!
                // Disable pageHeader — it should not count toward "other bands"
                pageHeaderBand.enabled = false
                // other enabled bands: 20+10+120+10+12+20 = 192 (pageHeader excluded)
                // max for title: 277 - (10+120+10+12+20) = 277 - 172 = 105
                store.setBandHeight(summaryBand.id, 999)
                expect(summaryBand.height).toBe(105)
            })

            it('allows height down to 0 when empty', () => {
                const titleBand = store.page.bands!.find(b => b.type === 'title')!

                store.setBandHeight(titleBand.id, 0)

                expect(titleBand.height).toBe(0)
            })

            it('clamps negative height to 0', () => {
                const titleBand = store.page.bands!.find(b => b.type === 'title')!

                store.setBandHeight(titleBand.id, -5)

                expect(titleBand.height).toBe(0)
            })

            it('no-ops gracefully for a non-existent band id', () => {
                expect(() => store.setBandHeight('non-existent', 100)).not.toThrow()
            })
        })

        describe('updateBand', () => {
            it('updates datasource and collection binding metadata', () => {
                const detailBand = store.page.bands!.find(b => b.type === 'detail')!

                store.updateBand(detailBand.id, {
                    datasourceId: 'ds-1',
                    collectionPath: 'orders',
                })

                expect(detailBand.datasourceId).toBe('ds-1')
                expect(detailBand.collectionPath).toBe('orders')
                expect(store.isDirty).toBe(true)
            })

            it('toggles enabled/disabled state', () => {
                const detailBand = store.page.bands!.find(b => b.type === 'detail')!
                expect(detailBand.enabled).toBe(true)

                store.updateBand(detailBand.id, { enabled: false })
                expect(detailBand.enabled).toBe(false)
                expect(store.isDirty).toBe(true)

                store.updateBand(detailBand.id, { enabled: true })
                expect(detailBand.enabled).toBe(true)
            })
        })

        describe('loadTemplate — bands', () => {
            it('restores bands from page data when present', () => {
                const customBands: ReportBand[] = [
                    { id: 'detail', type: 'detail', anchor: 'fill', label: 'Detail', height: 300, enabled: true, children: [] },
                    { id: 'summary', type: 'summary', anchor: 'bottom', label: 'Summary', height: 50, enabled: true, children: [] },
                ]

                const template = makeTemplate({
                    page: {
                        bands: customBands,
                        elements: [],
                    } as unknown as Record<string, unknown>,
                })

                store.loadTemplate(template)

                expect(store.page.bands).toHaveLength(2)
                // Band heights are clamped to printable area (A4: 277mm with 10mm margins)
                expect(store.page.bands![0].height).toBe(277)
                expect(store.page.bands![1].type).toBe('summary')
            })

            it('hydrates loaded band elements with missing designer defaults', () => {
                const template = makeTemplate({
                    page: {
                        bands: [
                            {
                                id: 'detail',
                                type: 'detail',
                                anchor: 'fill',
                                label: 'Detail',
                                height: 120,
                                children: [
                                    {
                                        type: 'text',
                                        x: 10,
                                        y: 5,
                                        width: 100,
                                        height: 15,
                                        content: { type: 'text', variable: 'id' },
                                    },
                                ],
                            },
                        ],
                    } as unknown as Record<string, unknown>,
                })

                store.loadTemplate(template)

                const element = store.page.bands![0].children[0] as DesignerElement
                expect(element.id).toBeTruthy()
                expect(element.visible).toBe(true)
                expect(element.locked).toBe(false)
                expect(element.styles).toBeDefined()
            })

            it('creates default bands for legacy templates without bands', () => {
                const template = makeTemplate({
                    page: {
                        children: [
                            { id: 'el-1', type: 'text', x: 10, y: 10, width: 100, height: 20 },
                        ],
                    } as unknown as Record<string, unknown>,
                })

                store.loadTemplate(template)

                // Bands should be created with defaults
                expect(store.page.bands).toBeDefined()
                expect(store.page.bands!.length).toBe(7)
                // Legacy elements placed in detail band
                expect(store.page.bands!.find(b => b.id === 'detail')!.children).toHaveLength(1)
            })

            it('assigns legacy elements to detail band when no bands present', () => {
                const template = makeTemplate({
                    page: {
                        children: [
                            { id: 'el-1', type: 'text', x: 10, y: 10, width: 100, height: 20 },
                        ],
                    } as unknown as Record<string, unknown>,
                })

                store.loadTemplate(template)

                const detailBand = store.page.bands!.find(b => b.id === 'detail')!
                expect(detailBand.children).toHaveLength(1)
                expect(detailBand.children[0].id).toBe('el-1')
            })
        })

        describe('saveTemplate — bands', () => {
            it('includes bands with nested elements in the page payload', () => {
                const payload = store.saveTemplate()
                const page = payload.page as unknown as Record<string, unknown>

                expect(page.bands).toBeDefined()
                expect(Array.isArray(page.bands)).toBe(true)
                expect((page.bands as ReportBand[])).toHaveLength(7)
                // Elements are inside bands, not at top-level
                expect(page.children).toBeUndefined()
                // Each band has an elements array
                for (const band of (page.bands as ReportBand[])) {
                    expect(Array.isArray(band.children)).toBe(true)
                }
            })

            it('omits bands from payload when page.bands is empty', () => {
                const store2 = useDesignerStore()
                // Manually clear bands
                store2.page.bands = []

                const payload = store2.saveTemplate()
                const page = payload.page as unknown as Record<string, unknown>

                expect(page.bands).toBeUndefined()
            })

            it('stores elements inside the correct band', () => {
                // Add a text element to the detail band
                store.addElement('text', { x: 30, y: 40 })

                const payload = store.saveTemplate()
                const page = payload.page as unknown as Record<string, unknown>

                const detailBand = (page.bands as ReportBand[]).find(b => b.id === 'detail')!
                expect(detailBand.children).toHaveLength(1)
                expect(detailBand.children[0].x).toBe(30)
                expect(detailBand.children[0].y).toBe(40)
            })
        })
    })

    // ── Page margins ────────────────────────────

    describe('Designer Store — Page margins', () => {
        let store: ReturnType<typeof useDesignerStore>

        beforeEach(() => {
            setActivePinia(createPinia())
            store = useDesignerStore()
        })

        describe('setMargin', () => {
            it('updates the given margin side and sets isDirty', () => {
                expect(store.page.margin.top).toBe(10)

                store.setMargin('top', 15)

                expect(store.page.margin.top).toBe(15)
                expect(store.isDirty).toBe(true)
            })

            it('allows margin of 0 mm', () => {
                store.setMargin('top', 0)

                expect(store.page.margin.top).toBe(0)
            })

            it('enforces a minimum content area of 30 mm', () => {
                // A4 is 210mm wide. Setting left=104 leaves 96mm content — ok
                store.setMargin('left', 104)
                expect(store.page.margin.left).toBe(104)

                // Setting right=104 would leave 2mm content (< 30mm) — should revert
                store.setMargin('right', 104)

                expect(store.page.margin.right).toBe(10) // unchanged (reverted)
                expect(store.page.margin.left).toBe(104) // left stays
            })

            it('does not affect other margin sides', () => {
                store.setMargin('top', 20)

                expect(store.page.margin.top).toBe(20)
                expect(store.page.margin.right).toBe(10)
                expect(store.page.margin.bottom).toBe(10)
                expect(store.page.margin.left).toBe(10)
            })
        })
    })
})
