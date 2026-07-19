// ──────────────────────────────────────────────
// Toolreport Designer — Type Definitions
// ──────────────────────────────────────────────

/**
 * PDF preview mode.
 */
export type PreviewMode = 'iframe' | 'modal'

/**
 * Base designer configuration.
 */
export interface DesignerConfig {
    /** Base URL for API requests */
    baseUrl?: string
    /** Axios request timeout in milliseconds */
    timeout?: number
    /** Preview mode */
    previewMode?: PreviewMode
}

// ── Re-export from API layer (barrel) ─────────

export type {
    ApiConfig,
    ApiClient,
    PdfTemplate,
    PdfDocument,
} from '../api/types'

// ── Re-export from designer model (barrel) ────

export type {
    ElementType,
    DesignerStyles,
    TextContent,
    ImageContent,
    TableContent,
    TableColumn,
    TableCellContent,
    CellMerge,
    LineContent,
    BarcodeContent,
    PageNumberContent,
    ContainerContent,
    ElementContent,
    DesignerElement,
    DesignerPage,
    DatasourceAuth,
    DatasourceConfig,
    DiscoveredField,
} from './designer'
