// ──────────────────────────────────────────────
// Toolreport Designer — API Client Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Mock } from 'vitest'

// ── Hoisted mocks ─────────────────────────────

const mockPost = vi.fn()
const mockGet = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()
const mockUse = vi.fn()

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
            post: mockPost,
            get: mockGet,
            put: mockPut,
            delete: mockDelete,
            interceptors: {
                request: { use: mockUse },
                response: { use: mockUse },
            },
            defaults: { baseURL: 'http://localhost' },
        })),
    },
}))

// ── Subject ────────────────────────────────────

import { createApiClient } from '../client'
import type { ApiConfig, DatasourceTestRequest, DatasourceTestResult } from '../types'

const TEST_CONFIG: ApiConfig = { baseURL: 'http://localhost/api' }

describe('ApiClient — testDatasource', () => {
    let client: ReturnType<typeof createApiClient>

    beforeEach(() => {
        vi.clearAllMocks()
        client = createApiClient(TEST_CONFIG)
    })

    it('POSTs to /templates/{id}/datasources/test', async () => {
        const request: DatasourceTestRequest = {
            datasource: {
                url: 'https://api.example.com/data',
                method: 'GET',
                headers: { Accept: 'application/json' },
                auth: { type: 'none' },
                timeout: 10000,
            },
        }

        const apiResult: DatasourceTestResult = {
            success: true,
            fields: [
                { name: 'email', path: 'email', type: 'string', level: 0, datasourceId: 'ds-1' },
            ],
            status: 200,
        }

        // Backend returns directly: { success, fields, status }
        mockPost.mockResolvedValueOnce({ data: apiResult })

        const result = await client.testDatasource(1, request)

        expect(mockPost).toHaveBeenCalledTimes(1)
        expect(mockPost).toHaveBeenCalledWith('/templates/1/datasources/test', request)
        expect(result).toEqual(apiResult)
    })

    it('returns the backend response directly (no Laravel resource wrapper)', async () => {
        const request: DatasourceTestRequest = {
            datasource: { url: 'https://example.com', method: 'GET' },
        }

        const apiResult: DatasourceTestResult = {
            success: false,
            error: 'Connection refused',
            status: 503,
        }

        mockPost.mockResolvedValueOnce({ data: apiResult })

        const result = await client.testDatasource(1, request)

        expect(result.success).toBe(false)
        expect(result.error).toBe('Connection refused')
        expect(result.status).toBe(503)
    })

    it('rejects non-positive templateId (assertId)', async () => {
        const request: DatasourceTestRequest = {
            datasource: { url: 'https://example.com', method: 'GET' },
        }

        await expect(client.testDatasource(0, request)).rejects.toThrow(TypeError)
        await expect(client.testDatasource(-1, request)).rejects.toThrow(TypeError)
        await expect(client.testDatasource(NaN, request)).rejects.toThrow(TypeError)

        expect(mockPost).not.toHaveBeenCalled()
    })

    it('propagates HTTP errors from the API', async () => {
        const request: DatasourceTestRequest = {
            datasource: { url: 'https://example.com', method: 'GET' },
        }

        mockPost.mockRejectedValueOnce(new Error('Network Error'))

        await expect(client.testDatasource(1, request)).rejects.toThrow('Network Error')
    })
})
