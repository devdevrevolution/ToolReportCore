// ──────────────────────────────────────────────
// Toolreport Designer — API Client
// ──────────────────────────────────────────────

import axios, { type AxiosInstance } from 'axios'
import { applyAuthInterceptor, applyErrorInterceptor } from './interceptors'
import type {
    ApiConfig,
    ApiClient,
    QueryParams,
    CreateTemplatePayload,
    UpdateTemplatePayload,
    GeneratePayload,
    PdfTemplate,
    PdfDocument,
    PaginatedResponse,
    DatasourceTestRequest,
    DatasourceTestResult,
    TemplateVar,
    CreateTemplateVarPayload,
    UpdateTemplateVarPayload,
} from './types'

/**
 * Creates a fully-configured `ApiClient` bound to the given `config`.
 *
 * The returned object exposes every endpoint the Vue designer needs and
 * handles auth injection + error normalisation transparently.
 */
/**
 * Runtime guard that rejects non-positive-number IDs before they reach
 * the HTTP layer.  Prevents silent "templates/undefined" in the URL.
 */
function assertId(id: number, method: string): asserts id is number {
    if (typeof id !== 'number' || !Number.isFinite(id) || id <= 0) {
        throw new TypeError(
            `[API] ${method}() requires a valid positive number, got ${JSON.stringify(id)}`,
        )
    }
}

export function createApiClient(config: ApiConfig): ApiClient {
    const client: AxiosInstance = axios.create({
        baseURL: config.baseURL.replace(/\/+$/, ''),
        timeout: config.timeout ?? 30_000,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
    })

    // Apply interceptors — auth first, error last so it wraps all errors
    applyAuthInterceptor(client, () => config.authToken)
    applyErrorInterceptor(client)

    const templatesBase = '/templates'

    return {
        // ── Templates ──────────────────────────────

        async getTemplates(params?: QueryParams): Promise<PaginatedResponse<PdfTemplate>> {
            const { data } = await client.get(templatesBase, { params })
            return data
        },

        async getTemplate(id: number): Promise<PdfTemplate> {
            const { data } = await client.get(`${templatesBase}/${id}`)
            // Laravel wraps resources in { data: {...} }
            return (data as { data: PdfTemplate }).data
        },

        async createTemplate(payload: CreateTemplatePayload): Promise<PdfTemplate> {
            const { data } = await client.post(templatesBase, payload)
            // Laravel wraps resources in { data: {...} }
            return (data as { data: PdfTemplate }).data
        },

        async updateTemplate(
            id: number,
            payload: UpdateTemplatePayload,
        ): Promise<PdfTemplate> {
            assertId(id, 'updateTemplate')
            const { data } = await client.put(`${templatesBase}/${id}`, payload)
            // Laravel wraps resources in { data: {...} }
            return (data as { data: PdfTemplate }).data
        },

        async deleteTemplate(id: number): Promise<void> {
            assertId(id, 'deleteTemplate')
            await client.delete(`${templatesBase}/${id}`)
        },

        // ── Documents ──────────────────────────────

        async generatePdf(
            templateId: number,
            payload: GeneratePayload,
        ): Promise<PdfDocument> {
            assertId(templateId, 'generatePdf')
            const { data } = await client.post(
                `${templatesBase}/${templateId}/generate`,
                payload,
            )
            // Laravel wraps resources in { data: {...} }
            return (data as { data: PdfDocument }).data
        },

        async getDocuments(templateId: number): Promise<PdfDocument[]> {
            assertId(templateId, 'getDocuments')
            const { data } = await client.get(
                `${templatesBase}/${templateId}/documents`,
            )
            // Laravel's paginated response: { data: [...], meta: {...} }
            return (data as { data: PdfDocument[] }).data
        },

        getDownloadUrl(documentId: number): string {
            assertId(documentId, 'getDownloadUrl')
            return `${client.defaults.baseURL}/documents/${documentId}/download`
        },

        // ── Datasources ────────────────────────────

        async testDatasource(
            templateId: number,
            request: DatasourceTestRequest,
        ): Promise<DatasourceTestResult> {
            assertId(templateId, 'testDatasource')
            const { data } = await client.post(
                `${templatesBase}/${templateId}/datasources/test`,
                request,
            )
            // Backend returns: { success, fields, error, status } — no Laravel resource wrapper
            return data as DatasourceTestResult
        },

        // ── Template Variables ───────────────────

        async getTemplateVars(templateId: number): Promise<TemplateVar[]> {
            assertId(templateId, 'getTemplateVars')
            const { data } = await client.get(
                `${templatesBase}/${templateId}/template-vars`,
            )
            // Laravel wraps resources in { data: [...] }
            return (data as { data: TemplateVar[] }).data
        },

        async createTemplateVar(
            templateId: number,
            payload: CreateTemplateVarPayload,
        ): Promise<TemplateVar> {
            assertId(templateId, 'createTemplateVar')
            const { data } = await client.post(
                `${templatesBase}/${templateId}/template-vars`,
                payload,
            )
            return (data as { data: TemplateVar }).data
        },

        async updateTemplateVar(
            templateId: number,
            templateVarId: number,
            payload: UpdateTemplateVarPayload,
        ): Promise<TemplateVar> {
            assertId(templateId, 'updateTemplateVar')
            assertId(templateVarId, 'updateTemplateVar')
            const { data } = await client.put(
                `${templatesBase}/${templateId}/template-vars/${templateVarId}`,
                payload,
            )
            return (data as { data: TemplateVar }).data
        },

        async deleteTemplateVar(
            templateId: number,
            templateVarId: number,
        ): Promise<void> {
            assertId(templateId, 'deleteTemplateVar')
            assertId(templateVarId, 'deleteTemplateVar')
            await client.delete(
                `${templatesBase}/${templateId}/template-vars/${templateVarId}`,
            )
        },
    }
}
