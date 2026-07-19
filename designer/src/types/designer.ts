// ──────────────────────────────────────────────
// Toolreport Designer — Designer-specific Types
// These are the internal drag-and-drop editor model types,
// separate from the API types found in ../api/types.ts
// ──────────────────────────────────────────────

/** Supported element types in the designer canvas */
export type ElementType = 'text' | 'image' | 'table' | 'line' | 'rectangle' | 'barcode' | 'page_number' | 'container'

/** Visual styling properties shared by all elements */
export interface DesignerStyles {
    fontFamily: string
    fontSize: number
    fontWeight: 'normal' | 'bold'
    fontStyle: 'normal' | 'italic'
    color: string
    textAlign: 'left' | 'center' | 'right' | 'justify'
    verticalAlign: 'top' | 'middle' | 'bottom'
    lineHeight: number
    backgroundColor: string | null
    border: { width: number; color: string; style: string } | null
    borderRadius: number
    padding: { top: number; right: number; bottom: number; left: number }
}

// ── Per-element content interfaces ────────────

export interface TextContent {
    text: string
    variable?: string
}

export interface ImageContent {
    imageUrl: string
    altText?: string
    variable?: string
}

export interface TableColumn {
    /** Stable id for tracking in the editor (uuid) */
    id: string
    /** Dot-path binding for this column in each row item (e.g. "precio", "producto.nombre") */
    key: string
    /** Header label displayed in the table */
    header: string
    /** Column width in mm (optional) */
    width?: number
    /** Text alignment */
    align?: 'left' | 'center' | 'right'
    /** Style overrides for the column header (<th>), same shape as cell styles */
    headerStyle?: Partial<Omit<TableCellContent, 'text'>>
}

/**
 * Style overrides for an individual table cell.
 * All fields are optional — inherit from the element-level styles by default.
 */
export interface TableCellContent {
    /** Cell text content */
    text: string
    fontFamily?: string
    fontSize?: number
    fontWeight?: 'normal' | 'bold'
    fontStyle?: 'normal' | 'italic'
    color?: string
    textAlign?: 'left' | 'center' | 'right'
    backgroundColor?: string
    verticalAlign?: 'top' | 'middle' | 'bottom'
    padding?: { top: number; bottom: number; left: number; right: number }
    /** When true, text stays on one line and overflows with ellipsis (…). Defaults to false (wrap normally). */
    nowrap?: boolean
}

/**
 * A cell merge range, equivalent to an Excel merge cell entry.
 * The top-left cell (row, col) is the anchor — its text and style survive.
 * All other cells within the range are discarded and hidden during render.
 */
export interface CellMerge {
    /** Starting row index (0-based) */
    row: number
    /** Starting column index (0-based) */
    col: number
    /** Number of rows this merge spans */
    rowspan: number
    /** Number of columns this merge spans */
    colspan: number
}

export interface TableContent {
    columns: TableColumn[]
    /** Dot-path to the array in the data payload (e.g. "data.items") */
    variable?: string
    /** Static row data — keyed by column key, each value is a cell config */
    rows?: Record<string, TableCellContent>[]
    /** Cell merge definitions (colspan/rowspan) */
    merges?: CellMerge[]
    /** Whether to show the header row. Set false to use the table as a visual grid/layout. */
    showHeader?: boolean
}

export interface LineContent {
    orientation: 'horizontal' | 'vertical'
    lineWidth: number
    lineStyle: 'solid' | 'dashed' | 'dotted'
}

export interface BarcodeContent {
    symbology: 'code128' | 'code39' | 'ean13' | 'qr'
    value: string
    variable?: string
    showLabel: boolean
}

export interface RectangleContent {
    /** Dot-path to a data field whose value is used as backgroundColor (e.g. "status.color") */
    colorVariable?: string
}

export interface PageNumberContent {
    format: string
    startAt: number
}

export interface ContainerContent {
    children: DesignerElement[]
    layout: 'vertical' | 'horizontal'
    gap: number
    padding: number
}

/** Discriminated union of all element content types */
export type ElementContent =
    | ({ type: 'text' } & TextContent)
    | ({ type: 'image' } & ImageContent)
    | ({ type: 'table' } & TableContent)
    | ({ type: 'line' } & LineContent)
    | ({ type: 'rectangle' } & RectangleContent)
    | ({ type: 'barcode' } & BarcodeContent)
    | ({ type: 'page_number' } & PageNumberContent)
    | ({ type: 'container' } & ContainerContent)

/** A single element on the designer canvas */
export interface DesignerElement {
    id: string
    type: ElementType
    x: number
    y: number
    width: number
    height: number
    rotation: number
    positionMode?: 'absolute' | 'fill'
    styles: DesignerStyles
    content: ElementContent
    visible: boolean
    locked: boolean
}

/** Page configuration for the designer canvas */
export interface DesignerPage {
    width: number
    height: number
    orientation: 'portrait' | 'landscape'
    paperSize: string | null
    margin: {
        top: number
        right: number
        bottom: number
        left: number
    }
    /** Report bands (sections) within the page — optional for backward compat */
    bands?: ReportBand[]
    /** Layout mode discriminator — 'bands-absolute' for DomPDF, 'bands-composite' for PdfEngine */
    layoutType?: LayoutType
}

// ── Composite node types ──────────────────────

export type CompositeNodeType = 'VBox' | 'HBox' | 'Label' | 'Shape' | 'Table' | 'Image'

/** Per-side margin in mm. */
export interface NodeMargin {
    top: number
    right: number
    bottom: number
    left: number
}

export interface VBoxNode {
    id: string
    type: 'VBox'
    padding?: number
    width?: number
    height?: number
    margin?: NodeMargin
    children: CompositeNode[]
}

export interface HBoxNode {
    id: string
    type: 'HBox'
    width?: number
    height?: number
    margin?: NodeMargin
    children: CompositeNode[]
}

export interface LabelNode {
    id: string
    type: 'Label'
    text: string
    fontFamily?: string
    fontSize?: number
    style?: string
    color?: string
    /** Text-wrap width (mm). When set, the text wraps at this width. */
    width?: number
    /** Explicit height (mm). When set, overrides the intrinsic text height. */
    height?: number
    /** When false, text is single-line and overflow is hidden. Default: true. */
    wrap?: boolean
    margin?: NodeMargin
}

export interface ShapeNode {
    id: string
    type: 'Shape'
    shapeType: 'line' | 'rect' | 'circle' | 'ellipse'
    x1?: number; y1?: number; x2?: number; y2?: number
    w?: number; h?: number
    strokeColor?: string
    fillColor?: string
    strokeWidth?: number
    lineStyle?: 'solid' | 'dashed' | 'dotted'
    borderRadius?: number
    margin?: NodeMargin
}

export interface TableRowNode {
    id: string
    type: 'TableRow'
    cells: TableCellNode[]
}

export interface TableCellNode {
    id: string
    type: 'TableCell'
    child: CompositeNode
}

export interface TableNode {
    id: string
    type: 'Table'
    columnWidths: number[]
    rows: TableRowNode[]
    margin?: NodeMargin
}

export interface ImageNode {
    id: string
    type: 'Image'
    /** Image URL — supports {{variable}} interpolation (e.g. "https://...", "{{ logo }}", "https://cdn.co/{{ company }}/logo.png") */
    url?: string
    /** @deprecated Use url with {{ }} syntax instead. Kept for backward compat. */
    variable?: string
    /** Alt text for the image */
    altText?: string
    /** CSS object-fit behavior */
    objectFit?: 'contain' | 'cover' | 'fill' | 'none'
    /** Shape type for clipping/stroke (like Shape component) */
    shapeType?: 'rect' | 'circle' | 'ellipse'
    /** Border radius in mm (only for rect shapeType) */
    borderRadius?: number
    /** Background fill color behind the image — transparent PNGs show this color */
    fillColor?: string
    /** Stroke width in mm */
    strokeWidth?: number
    /** Stroke color as hex */
    strokeColor?: string
    /** Stroke line style */
    lineStyle?: 'solid' | 'dashed' | 'dotted'
    /** Opacity 0-1 */
    opacity?: number
    /** Width in mm (optional, auto if not set) */
    width?: number
    /** Height in mm (optional, auto if not set) */
    height?: number
    margin?: NodeMargin
}

export type CompositeNode = VBoxNode | HBoxNode | LabelNode | ShapeNode | TableNode | ImageNode

/**
 * A band root child — a composite node wrapped with an absolute-positioned
 * box (x/y in mm relative to the band content origin, width/height in mm).
 *
 * The inner `node` retains its original flow semantics. When rendered, the
 * compiler places the node at (band_x + box.x, band_y + box.y) and clips
 * its natural dimensions to box.width / box.height when provided.
 */
export interface CompositeRoot {
    id: string
    /** Absolute position (mm) relative to the band content top-left. */
    x: number
    /** Absolute position (mm) relative to the band content top-left. */
    y: number
    /** Display width (mm). When omitted, uses the node's natural width. */
    width?: number
    /** Display height (mm). When omitted, uses the node's natural height. */
    height?: number
    /** The composite node payload. */
    node: CompositeNode
}

export type LayoutType = 'bands-absolute' | 'bands-composite'

/** A positioned child inside a band — simple designer element (dompdf) or composite node root (pdf-engine) */
export type BandChild = DesignerElement | CompositeRoot

// ── Band (report section) types ─────────────────

/**
 * Band types matching iReport/JasperReports semantics.
 * Each band type has specific rendering behaviour at generation time:
 * - title / summary: rendered once
 * - pageHeader / pageFooter: repeated on every page
 * - columnHeader / columnFooter: before/after detail rows
 * - detail: repeated for each data row
 */
export type BandType =
    | 'title'
    | 'pageHeader'
    | 'columnHeader'
    | 'detail'
    | 'columnFooter'
    | 'pageFooter'
    | 'summary'

/**
 * How a band is anchored within the page content area.
 * - `top`: grows downward from the top — title, pageHeader, columnHeader
 * - `bottom`: grows upward from the bottom — columnFooter, pageFooter, summary
 * - `fill`: fills remaining space between top and bottom groups — detail
 */
export type BandAnchor = 'top' | 'bottom' | 'fill'

/** A named resizable section (band) in the report layout */
export interface ReportBand {
    /** Stable unique id (matches BandType for defaults) */
    id: string
    /** Semantic type */
    type: BandType
    /** How the band is anchored in the page */
    anchor: BandAnchor
    /** Human-readable label (e.g. "Page Header") */
    label: string
    /** Height in mm — user-resizable */
    height: number
    /** Whether the band is rendered in PDF output */
    enabled: boolean
    /** Datasource that owns the collection bound to this band */
    datasourceId?: string | null
    /** Dot path of the datasource collection repeated by this band */
    collectionPath?: string | null
    /**
     * Where the summary band appears on the last page.
     * - 'afterDetail': right after the last detail item
     * - 'pageBottom': at the bottom of the page (iReport style)
     */
    summaryPosition?: 'afterDetail' | 'pageBottom'
    /**
     * Positioned children within this band.
     * - dompdf engine: simple DesignerElement[]
     * - pdf-engine: CompositeRoot[]
     */
    children: BandChild[]
    /**
     * Composite node tree (pdf-engine mode, LEGACY shape) — single root
     * node for the band content. Auto-migrated to `children` on load by
     * the store. Kept for backward compatibility with saved templates.
     */
    content?: CompositeNode
}

// ── Datasource types ───────────────────────────

/** Authentication configuration for an external datasource */
export interface DatasourceAuth {
    type: 'none' | 'bearer'
    token?: string
}

/** Configuration for an external datasource (JSON API endpoint) */
export interface DatasourceConfig {
    id: string
    name: string
    url: string
    method: 'GET' | 'POST'
    headers: Record<string, string>
    auth: DatasourceAuth
    timeout: number
    /** Error message if the last test failed, null if not tested or successful */
    lastError: string | null
}

/** A single field discovered by introspecting a datasource response */
export interface DiscoveredField {
    /** Human-readable display name (e.g., "User Email") */
    name: string
    /** Dot-notation path (e.g., "data.users.0.email") */
    path: string
    /** JSON type: 'string' | 'number' | 'boolean' | 'array' | 'object' | 'null' */
    type: string
    /** Nesting depth (0 = root) */
    level: number
    /** Links back to the DatasourceConfig that produced this field */
    datasourceId: string
}

export interface ClipboardEntry {
    element: CompositeRoot
    sourceTemplateId: number
    sourceEngine: string
    timestamp: number
}

// ── Template Variable types ───────────────

/** Visibility classification for a template variable */
export type TemplateVarVisibility = 'public' | 'private'

/**
 * A template variable bound to a specific PDF template.
 *
 * - `public` vars can be sent by the client during PDF generation.
 * - `private` vars are server-side only (API tokens, secrets) and are never exposed to the client.
 */
export interface TemplateVar {
    id: number
    pdf_template_id: number
    name: string
    value: string | null
    visibility: TemplateVarVisibility
    is_required: boolean
    description: string | null
    created_at: string
    updated_at: string
}
