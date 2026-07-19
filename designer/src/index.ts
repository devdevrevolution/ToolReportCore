// ──────────────────────────────────────────────
// Toolreport Designer — Public API
// ──────────────────────────────────────────────

export { default as PdfDesigner } from './components/layout/PdfDesigner.vue'
export { default as CompositeDesigner } from './components/layout/CompositeDesigner.vue'
export { default as CompositeToolbar } from './components/layout/CompositeToolbar.vue'
export { default as CompositePropertiesPanel } from './components/layout/CompositePropertiesPanel.vue'
export { default as DesignerShell } from './components/shell/DesignerShell.vue'
export { default as PreviewModal } from './components/modals/PreviewModal.vue'
export { router } from './router'

export { createApiClient } from './api/client'

// ── Store ─────────────────────────────────────

export { useDesignerStore } from './stores/designer'

// ── Composables ───────────────────────────────

export { useDesigner } from './composables/useDesigner'
export { useApi, provideApiConfig } from './composables/useApi'
export { usePreview } from './composables/usePreview'
export { useSave } from './composables/useSave'

// ── Types ─────────────────────────────────────

export * from './types'

export type {
    ApiError,
    ApiConfig,
    ApiClient,
    PdfTemplate,
    PdfDocument,
    QueryParams,
    PaginatedResponse,
    CreateTemplatePayload,
    UpdateTemplatePayload,
    GeneratePayload,
    DatasourceTestRequest,
    DatasourceTestResult,
} from './api/types'

export type {
    ElementType,
    DesignerStyles,
    TextContent,
    ImageContent,
    TableContent,
    LineContent,
    BarcodeContent,
    PageNumberContent,
    ElementContent,
    DesignerElement,
    DesignerPage,
    DatasourceAuth,
    DatasourceConfig,
    DiscoveredField,
} from './types/designer'