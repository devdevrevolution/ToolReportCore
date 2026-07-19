// ──────────────────────────────────────────────
// Toolreport Designer — Pinia Store (Options API)
// ──────────────────────────────────────────────

import { defineStore } from 'pinia'
import type {
    ElementType,
    DesignerElement,
    DesignerPage,
    DesignerStyles,
    ElementContent,
    ContainerContent,
    DatasourceConfig,
    DiscoveredField,
    ReportBand,
    BandType,
    LayoutType,
    CompositeNodeType,
    CompositeNode,
    CompositeRoot,
    VBoxNode,
    HBoxNode,
    ImageNode,
    BandChild,
    ClipboardEntry,
} from '@/types/designer'
import type { PdfTemplate, CreateTemplatePayload } from '@/api/types'

// ── Paper size presets (width × height in mm) ──

export const PAPER_SIZES: Record<string, { width: number; height: number }> = {
    A4: { width: 210, height: 297 },
    A3: { width: 297, height: 420 },
    Letter: { width: 215.9, height: 279.4 },
    Legal: { width: 215.9, height: 355.6 },
    Tabloid: { width: 279.4, height: 431.8 },
}

export const PAPER_SIZE_NAMES = Object.keys(PAPER_SIZES)

/**
 * Calculate the absolute Y position of a band for a given page height.
 * Bottom bands anchor from the content bottom so the layout looks correct
 * even when the design page height differs from the full paper height.
 */
function calcBandYPos(
    bands: ReportBand[],
    bandId: string,
    marginTop: number,
    pageHeight: number,
    marginBottom: number,
): number {
    const band = bands.find(b => b.id === bandId)
    if (!band) return 0

    const contentBottom = pageHeight - marginBottom
    const anchor = band.anchor

    if (anchor === 'top') {
        let y = marginTop
        for (const b of bands) {
            if (b.anchor !== 'top') continue
            if (b.id === band.id) return y
            y += b.height
        }
    }

    if (anchor === 'fill') {
        const topHeight = bands
            .filter(b => b.anchor === 'top')
            .reduce((s, b) => s + b.height, 0)
        return marginTop + topHeight
    }

    if (anchor === 'bottom') {
        const bottomBands = bands.filter(b => b.anchor === 'bottom')
        let fromBottom = contentBottom
        for (let i = bottomBands.length - 1; i >= 0; i--) {
            const b = bottomBands[i]
            fromBottom -= b.height
            if (b.id === band.id) return fromBottom
        }
    }

    return marginTop
}

// ── Helper: flatten all children across all bands ─

export function isDesignerChild(child: BandChild): child is DesignerElement {
    return 'content' in child
}

export function isCompositeRoot(child: BandChild): child is CompositeRoot {
    return 'node' in child
}

function allElements(bands: ReportBand[]): DesignerElement[] {
    return bands.flatMap(b => flattenChildren(b.children.filter(isDesignerChild)))
}

function flattenChildren(children: DesignerElement[]): DesignerElement[] {
    const result: DesignerElement[] = []
    for (const el of children) {
        result.push(el)
        if (el.type === 'container') {
            const content = el.content as ContainerContent
            if (content.children && content.children.length > 0) {
                result.push(...flattenChildren(content.children))
            }
        }
    }
    return result
}

function deepCloneChildren(children: DesignerElement[]): DesignerElement[] {
    return children.map(child => {
        const clone: DesignerElement = JSON.parse(JSON.stringify(child))
        clone.id = crypto.randomUUID()
        if (clone.type === 'container') {
            const content = clone.content as ContainerContent
            content.children = deepCloneChildren(content.children)
        }
        return clone
    })
}

function removeFromContainerChildren(elements: DesignerElement[], idSet: Set<string>): void {
    for (const el of elements) {
        if (el.type === 'container') {
            const content = el.content as ContainerContent
            content.children = content.children.filter(child => !idSet.has(child.id))
            // Recursively handle nested containers
            removeFromContainerChildren(content.children, idSet)
        }
    }
}

/**
 * Recursively remove a composite node by id from a tree.
 * Searches children of VBox/HBox nodes.
 */
function removeCompositeNodeFromTree(root: CompositeNode, id: string): boolean {
    if ('children' in root && root.children) {
        const idx = root.children.findIndex((c: CompositeNode) => c.id === id)
        if (idx !== -1) {
            root.children.splice(idx, 1)
            return true
        }
        for (const child of root.children) {
            if (removeCompositeNodeFromTree(child, id)) return true
        }
    }
    // Only search into table cell children, don't try to remove row/cell nodes directly
    if (root.type === 'Table' && root.rows) {
        for (const row of root.rows) {
            for (const cell of row.cells) {
                if (removeCompositeNodeFromTree(cell.child, id)) return true
            }
        }
    }
    return false
}

// ── Helper: find an element across all bands ────

function findElementInBands(
    bands: ReportBand[],
    id: string,
): {
    element: DesignerElement
    band: ReportBand
    index: number
    parentContainerId: string | null
    containerChildIndex: number | null
} | null {
    // First, search top-level DesignerElement children in each band
    for (const band of bands) {
        const idx = band.children.findIndex(el => el.id === id && isDesignerChild(el))
        if (idx !== -1) {
            return {
                element: band.children[idx] as DesignerElement,
                band,
                index: idx,
                parentContainerId: null,
                containerChildIndex: null,
            }
        }
    }

    // Then, recursively search container children
    for (const band of bands) {
        const designerChildren = band.children.filter(isDesignerChild)
        const found = findInContainerChildren(designerChildren, id)
        if (found) {
            return { ...found, band, index: -1 }
        }
    }

    return null
}

function findInContainerChildren(
    elements: DesignerElement[],
    id: string,
): {
    element: DesignerElement
    parentContainerId: string
    containerChildIndex: number
} | null {
    for (const el of elements) {
        if (el.type === 'container') {
            const content = el.content as ContainerContent
            const idx = content.children.findIndex(child => child.id === id)
            if (idx !== -1) {
                return { element: content.children[idx], parentContainerId: el.id, containerChildIndex: idx }
            }
            // Recurse into nested containers
            const found = findInContainerChildren(content.children, id)
            if (found) return found
        }
    }
    return null
}

// ── Helper: snapshot all bands (with children) for history ──

function serializeBands(bands: ReportBand[]): string {
    return JSON.stringify(bands.map(b => ({
        ...b,
        children: b.children ?? [],
    })))
}

// ── State interface ───────────────────────────

interface DesignerState {
    selectedElementIds: string[]
    selectedBandId: string | null
    selectedCompositeNodeId: string | null
    page: DesignerPage
    templateId: number | null
    templateName: string
    templateSlug: string
    templateDescription: string | null
    engine: 'dompdf' | 'pdf-engine'
    isDirty: boolean
    isLoading: boolean
    /** JSON snapshot of bands (with elements) at last save — for dirty detection */
    originalBands: string
    /** Undo/redo history — stack of JSON-serialized bands+snapshots */
    history: string[]
    /** Current position in history stack (-1 = no history, 0 = first) */
    historyIndex: number
    /** Validation error when band heights exceed printable area */
    bandHeightError: string | null
    /** When true, clicking and dragging on the canvas creates a container instead of selecting */
    containerDrawMode: boolean
    /** Element currently in inline edit mode (canvas drag is disabled for this element) */
    editingElementId: string | null
    /** External datasource configurations persisted in template config */
    datasources: DatasourceConfig[]
    /** Fields discovered by testing datasources (ephemeral store) */
    discoveredFields: DiscoveredField[]
    /** Clipboard for copy/paste of composite elements */
    clipboard: ClipboardEntry | null
}

// ── Defaults ──────────────────────────────────

function createDefaultBands(): ReportBand[] {
    return [
        { id: 'title', type: 'title', anchor: 'top', label: 'Title', height: 20, enabled: true, datasourceId: null, collectionPath: null, children: [] },
        { id: 'pageHeader', type: 'pageHeader', anchor: 'top', label: 'Page Header', height: 12, enabled: true, datasourceId: null, collectionPath: null, children: [] },
        { id: 'columnHeader', type: 'columnHeader', anchor: 'top', label: 'Column Header', height: 10, enabled: true, datasourceId: null, collectionPath: null, children: [] },
        { id: 'detail', type: 'detail', anchor: 'fill', label: 'Detail', height: 120, enabled: true, datasourceId: null, collectionPath: null, children: [] },
        { id: 'columnFooter', type: 'columnFooter', anchor: 'bottom', label: 'Column Footer', height: 10, enabled: true, datasourceId: null, collectionPath: null, children: [] },
        { id: 'summary', type: 'summary', anchor: 'bottom', label: 'Summary', height: 20, enabled: true, datasourceId: null, collectionPath: null, summaryPosition: 'afterDetail', children: [] },
        { id: 'pageFooter', type: 'pageFooter', anchor: 'bottom', label: 'Page Footer', height: 12, enabled: true, datasourceId: null, collectionPath: null, children: [] },
    ]
}

function createDefaultPage(): DesignerPage {
    return {
        width: 210,
        height: 297,
        orientation: 'portrait',
        paperSize: 'A4',
        margin: { top: 10, right: 10, bottom: 10, left: 10 },
        bands: createDefaultBands(),
    }
}

const DEFAULT_ELEMENT_POSITION = { x: 10, y: 10 } // Relative to content area / band top

const MIN_ELEMENT_SIZE = 5 // mm

// ── Helper functions (pure, outside the store) ─

function getDefaultStyles(): DesignerStyles {
    return {
        fontFamily: 'Helvetica',
        fontSize: 12,
        fontWeight: 'normal',
        fontStyle: 'normal',
        color: '#000000',
        textAlign: 'left',
        verticalAlign: 'top',
        lineHeight: 1.2,
        backgroundColor: null,
        border: null,
        borderRadius: 0,
        padding: { top: 0, right: 0, bottom: 0, left: 0 },
    }
}

function getElementDefaults(type: ElementType): Omit<DesignerElement, 'id'> {
    const styles = getDefaultStyles()

    const base = {
        x: DEFAULT_ELEMENT_POSITION.x,
        y: DEFAULT_ELEMENT_POSITION.y,
        rotation: 0,
        styles,
        visible: true,
        locked: false,
    }

    switch (type) {
        case 'text':
            return {
                ...base,
                type: 'text',
                width: 100, // Slightly smaller default to fit relative
                height: 15,
                content: { type: 'text', text: 'Text' } as ElementContent,
            }
        case 'image':
            return {
                ...base,
                type: 'image',
                width: 60,
                height: 40,
                content: { type: 'image', imageUrl: '' } as ElementContent,
            }
        case 'table':
            return {
                ...base,
                type: 'table',
                width: 120,
                height: 80,
                content: { type: 'table', columns: [] } as ElementContent,
            }
        case 'line':
            return {
                ...base,
                type: 'line',
                width: 80,
                height: 2,
                content: {
                    type: 'line',
                    orientation: 'horizontal',
                    lineWidth: 1,
                    lineStyle: 'solid',
                } as ElementContent,
            }
        case 'rectangle':
            return {
                ...base,
                type: 'rectangle',
                width: 80,
                height: 40,
                styles: {
                    ...base.styles,
                    border: { width: 0.5, color: '#cccccc', style: 'solid' },
                },
                content: { type: 'rectangle' } as ElementContent,
            }
        case 'barcode':
            return {
                ...base,
                type: 'barcode',
                width: 80,
                height: 25,
                content: {
                    type: 'barcode',
                    symbology: 'code128',
                    value: '',
                    showLabel: true,
                } as ElementContent,
            }
        case 'page_number':
            return {
                ...base,
                type: 'page_number',
                width: 40,
                height: 10,
                content: {
                    type: 'page_number',
                    format: 'Page {current} of {total}',
                    startAt: 1,
                } as ElementContent,
            }
        case 'container':
            return {
                ...base,
                type: 'container',
                width: 120,
                height: 80,
                content: {
                    type: 'container',
                    children: [],
                    layout: 'vertical',
                    gap: 2,
                    padding: 4,
                } as ElementContent,
            }
    }
}

function hydrateElement(element: Partial<DesignerElement>): DesignerElement {
    const type = element.type ?? 'text'
    const defaults = getElementDefaults(type)

    const hydrated = {
        ...defaults,
        ...element,
        id: element.id ?? crypto.randomUUID(),
        type,
        x: element.x ?? defaults.x,
        y: element.y ?? defaults.y,
        width: element.width ?? defaults.width,
        height: element.height ?? defaults.height,
        rotation: element.rotation ?? defaults.rotation,
        styles: {
            ...defaults.styles,
            ...(element.styles ?? {}),
        },
        content: {
            ...defaults.content,
            ...(element.content ?? {}),
        } as ElementContent,
        visible: element.visible ?? true,
        locked: element.locked ?? false,
    }

    // Recursively hydrate container children
    if (hydrated.type === 'container' && hydrated.content?.type === 'container') {
        const containerContent = hydrated.content as Partial<ContainerContent>
        hydrated.content = {
            ...hydrated.content,
            children: (containerContent.children ?? []).map(child => hydrateElement(child)),
        } as ElementContent
    }

    return hydrated
}

function hydrateBands(bands: ReportBand[]): ReportBand[] {
    return bands.map(band => ({
        ...band,
        enabled: band.enabled ?? true,
        datasourceId: band.datasourceId ?? null,
        collectionPath: band.collectionPath ?? null,
        children: (band.children ?? []).map(element => hydrateElement(element)),
    }))
}

/**
 * Compute a band height overflow error message, or null if bands fit.
 * Matches the backend validateBandHeights logic: only enabled bands count.
 */
function bandHeightOverflowError(page: DesignerPage): string | null {
    const bands = (page.bands ?? []).filter(b => b.enabled !== false)
    if (bands.length === 0) return null
    const totalHeight = bands.reduce((s, b) => s + b.height, 0)
    const printableHeight = page.height - page.margin.top - page.margin.bottom
    if (totalHeight <= printableHeight) return null
    const overflow = totalHeight - printableHeight
    return `Band heights exceed printable area: ${totalHeight}mm total vs ${printableHeight}mm available (overflow: ${overflow}mm). Reduce band heights or increase page size.`
}

/**
 * Recursively search a composite node tree for a node by id.
 * Handles VBox, HBox (children), and Table (rows → cells → child).
 */
function findCompositeNode(root: CompositeNode, id: string): CompositeNode | null {
    if (root.id === id) return root
    if ('children' in root && root.children) {
        for (const child of root.children) {
            const found = findCompositeNode(child, id)
            if (found) return found
        }
    }
    // Recurse into table cell children but don't expose row/cell nodes as selectable
    if (root.type === 'Table' && root.rows) {
        for (const row of root.rows) {
            for (const cell of row.cells) {
                const found = findCompositeNode(cell.child, id)
                if (found) return found
            }
        }
    }
    return null
}

/**
 * Read a node's dimension value. Shape uses w/h, everything else uses width/height.
 */
function getNodeSize(node: CompositeNode, axis: 'width' | 'height'): number {
    if (axis === 'width') {
        if (node.type === 'Shape') return (node as { w?: number }).w ?? 40
        return (node as { width?: number }).width ?? 40
    }
    if (node.type === 'Shape') return (node as { h?: number }).h ?? 20
    return (node as { height?: number }).height ?? 20
}

/**
 * Write a node's dimension value. Shape uses w/h, everything else uses width/height.
 */
function setNodeSize(node: CompositeNode, axis: 'width' | 'height', value: number): void {
    if (axis === 'width') {
        if (node.type === 'Shape') {
            (node as { w?: number }).w = value
        } else {
            (node as { width?: number }).width = value
        }
    } else {
        if (node.type === 'Shape') {
            (node as { h?: number }).h = value
        } else {
            (node as { height?: number }).height = value
        }
    }
}

/**
 * Find a node and its parent container by id. Returns null if not found.
 * Only searches inside containers (VBox/HBox) — root nodes have no parent constraint.
 */
function findNodeWithParent(
    root: CompositeNode,
    id: string,
): { node: CompositeNode; parent: (VBoxNode | HBoxNode) | null } | null {
    if ('children' in root && root.children) {
        for (const child of root.children) {
            if (child.id === id) {
                return { node: child, parent: root as VBoxNode | HBoxNode }
            }
            const found = findNodeWithParent(child, id)
            if (found) return found
        }
    }
    if (root.type === 'Table' && root.rows) {
        for (const row of root.rows) {
            for (const cell of row.cells) {
                if (cell.child.id === id) {
                    return { node: cell.child, parent: null }
                }
                const found = findNodeWithParent(cell.child, id)
                if (found) return found
            }
        }
    }
    return null
}

/**
 * Recursively collect node IDs that exceed their parent container's dimensions.
 */
function collectConflicts(node: CompositeNode, conflicts: Set<string>): void {
    if ('children' in node && node.children) {
        const isHBox = node.type === 'HBox'
        const mainAxis = isHBox ? 'width' : 'height'
        const crossAxis = isHBox ? 'height' : 'width'

        const parentMain = getNodeSize(node, mainAxis)
        const parentCross = getNodeSize(node, crossAxis)
        const siblingsSum = node.children.reduce((s, c) => s + getNodeSize(c, mainAxis), 0)

        for (const child of node.children) {
            const childMain = getNodeSize(child, mainAxis)
            const childCross = getNodeSize(child, crossAxis)

            const mainOverflow = childMain + (siblingsSum - childMain) > parentMain

            // Labels without explicit cross-axis dimension render at 100% of
            // parent (CSS w-full / h-full) — they never overflow on the cross axis.
            const isAutoCross = child.type === 'Label' && childCross === (crossAxis === 'width' ? 40 : 20)
            const crossOverflow = !isAutoCross && childCross > parentCross

            if (mainOverflow || crossOverflow) {
                conflicts.add(child.id)
            }

            // Recurse into nested containers
            collectConflicts(child, conflicts)
        }
    }

    // Also recurse into table cells
    if (node.type === 'Table' && node.rows) {
        for (const row of node.rows) {
            for (const cell of row.cells) {
                collectConflicts(cell.child, conflicts)
            }
        }
    }
}

/**
 * Recursively propagate a new width to child nodes inside a VBox.
 * Direct HBox, Label and Image children match the VBox width.
 * Does NOT recurse into HBox — their children stay as-is (overflow = conflict).
 */
function propagateWidthToChildren(node: CompositeNode, width: number): void {
    if (!('children' in node) || !node.children) return
    for (const child of node.children) {
        if (child.type === 'Label' || child.type === 'HBox' || child.type === 'Image') {
            child.width = width
        }
        // Only recurse into VBox — HBox children are left as-is (conflict)
        if (child.type === 'VBox') {
            propagateWidthToChildren(child, width)
        }
    }
}

/**
 * Recursively propagate a new height to child nodes inside an HBox.
 * Direct VBox, Label and Image children match the HBox height.
 * Does NOT recurse into VBox — their children stay as-is (overflow = conflict).
 */
function propagateHeightToChildren(node: CompositeNode, height: number): void {
    if (!('children' in node) || !node.children) return
    for (const child of node.children) {
        if (child.type === 'Label' || child.type === 'VBox' || child.type === 'Image') {
            child.height = height
        }
        // Only recurse into HBox — VBox children are left as-is (conflict)
        if (child.type === 'HBox') {
            propagateHeightToChildren(child, height)
        }
    }
}

/**
 * Create a default composite node of the given type with a generated id.
 */
function createCompositeNode(type: CompositeNodeType, id: string): CompositeNode {
    switch (type) {
        case 'VBox':
            return { id, type: 'VBox', width: 80, height: 60, children: [] } as VBoxNode
        case 'HBox':
            return { id, type: 'HBox', width: 100, height: 30, children: [] } as HBoxNode
        case 'Label':
            return { id, type: 'Label', text: 'Label' } as CompositeNode
        case 'Shape':
            return { id, type: 'Shape', shapeType: 'rect', w: 40, h: 20 } as CompositeNode
        case 'Table':
            return { id, type: 'Table', columnWidths: [60, 60], rows: [] } as CompositeNode
        case 'Image':
            return { id, type: 'Image', shapeType: 'rect', objectFit: 'contain', width: 40, height: 30 } as ImageNode
    }
}

/**
 * Find a CompositeRoot by id across all bands' `children` arrays.
 */
function findCompositeRoot(bands: ReportBand[], rootId: string): CompositeRoot | null {
    for (const band of bands) {
        if (!band.children) continue
        const root = band.children.find((r): r is CompositeRoot => isCompositeRoot(r) && r.id === rootId)
        if (root) return root
    }
    return null
}

/**
 * Deep-clone a CompositeRoot with all-new UUIDs for the root and every
 * nested node. Does NOT mutate the original.
 */
function deepCloneCompositeRoot(root: CompositeRoot): CompositeRoot {
    function cloneNode(node: CompositeNode): CompositeNode {
        const cloned: any = { ...node, id: crypto.randomUUID() }
        if ('children' in cloned && Array.isArray(cloned.children)) {
            cloned.children = cloned.children.map((child: CompositeNode) => cloneNode(child))
        }
        return cloned as CompositeNode
    }
    return {
        ...root,
        id: crypto.randomUUID(),
        node: cloneNode(root.node),
    }
}

/**
 * Find the band that owns a given CompositeRoot id.
 */
function findBandByRootId(bands: ReportBand[], rootId: string): ReportBand | null {
    for (const band of bands) {
        if (!band.children) continue
        if (band.children.some(r => r.id === rootId)) return band
    }
    return null
}

/**
 * Migrate legacy `band.content` (single composite root) to the new
 * `band.children` array of absolutely-positioned CompositeRoot entries.
 * Bands already using `children` are left untouched (forward-compatible).
 */
function migrateLegacyContent(bands: ReportBand[]): void {
    for (const band of bands) {
        if (!band.content) continue
        // If band already has elements (v3), skip migration — content is stale
        if (band.children && band.children.length > 0) continue

        if ('children' in band.content && Array.isArray(band.content.children)) {
            // Container root: lift each child as a top-level root
            let stackedY = 0
            band.children = band.content.children.map((child: CompositeNode) => {
                const height = compositeNodeHeight(child)
                const root: CompositeRoot = {
                    id: crypto.randomUUID(),
                    x: 0,
                    y: stackedY,
                    node: child,
                }
                stackedY += height
                return root
            })
        } else {
            // Leaf root: wrap as single root at origin
            band.children = [{
                id: crypto.randomUUID(),
                x: 0,
                y: 0,
                node: band.content,
            }]
        }

        // Drop legacy field so it's not rendered twice
        band.content = undefined
    }
}

/**
 * Rough height estimate (mm) for a composite node, used for stacking
 * during legacy migration. Flow children contribute their own heights;
 * leaves use sensible defaults.
 */
function compositeNodeHeight(node: CompositeNode): number {
    if ('children' in node && node.children) {
        return node.children.reduce((sum, child) => sum + compositeNodeHeight(child), 0) + 3
    }
    switch (node.type) {
        case 'Label':
            return 6
        case 'Shape':
            return (node as { h?: number }).h ?? 20
        case 'Image':
            return (node as { height?: number }).height ?? 20
        case 'Table':
            return 20
        default:
            return 10
    }
}

/**
 * Rough width estimate (mm) for a composite node, used for clamping
 * roots during drag/resize.
 */
function compositeNodeWidth(node: CompositeNode): number {
    if ('children' in node && node.children) {
        return Math.max(...node.children.map(compositeNodeWidth), 0)
    }
    switch (node.type) {
        case 'Label':
            return 40
        case 'Shape':
            return (node as { w?: number }).w ?? 40
        case 'Image':
            return (node as { width?: number }).width ?? 40
        case 'Table':
            return 120
        default:
            return 40
    }
}

// ── Store ─────────────────────────────────────

export const useDesignerStore = defineStore('store/designer', {
    state: (): DesignerState => ({
        selectedElementIds: [],
        selectedBandId: null,
        selectedCompositeNodeId: null,
        page: createDefaultPage(),
        templateId: null,
        templateName: 'Untitled',
        templateSlug: '',
        templateDescription: null,
        engine: 'dompdf',
        isDirty: false,
        isLoading: false,
        bandHeightError: null,
        containerDrawMode: false,
        editingElementId: null,
        originalBands: '[]',
        history: [],
        historyIndex: -1,
        datasources: [],
        discoveredFields: [],
        clipboard: null,
    }),

    getters: {
        /** Primary selected element (first in selectedElementIds, for backward compat) */
        selectedElementId: (state): string | null => {
            return state.selectedElementIds[0] ?? null
        },

        selectedElement: (state): DesignerElement | null => {
            const id = state.selectedElementIds[0] ?? null
            if (id === null) return null
            const bands = state.page.bands
            if (!bands) return null
            const found = findElementInBands(bands, id)
            return found?.element ?? null
        },

        /** All currently selected elements */
        selectedElements: (state): DesignerElement[] => {
            const bands = state.page.bands
            if (!bands || state.selectedElementIds.length === 0) return []
            const all = allElements(bands)
            return all.filter(el => state.selectedElementIds.includes(el.id))
        },

        /** Check if an element id is currently selected */
        isSelected:
            (state) =>
            (id: string): boolean => {
                return state.selectedElementIds.includes(id)
            },

        visibleElements: (state): DesignerElement[] => {
            const bands = state.page.bands
            if (!bands) return []
            return allElements(bands).filter((el) => el.visible)
        },

        elementCount: (state): number => {
            const bands = state.page.bands
            if (!bands) return 0
            return allElements(bands).length
        },

        /**
         * Whether the selected element is a direct child of a container.
         * Used by PropertiesPanel to conditionally show the positionMode selector.
         */
        isChildOfContainer: (state): boolean => {
            const selectedId = state.selectedElementIds[0] ?? null
            if (!selectedId || !state.page.bands) return false
            return state.page.bands.some(band =>
                (band.children ?? []).some(el =>
                    isDesignerChild(el) && el.type === 'container'
                    && (el.content as ContainerContent).children?.some(child => child.id === selectedId)
                )
            )
        },

        /**
         * Get the absolute Y position (from page top, in mm) of a band.
         * Uses the full page height — bottom bands anchor from the real
         * paper bottom. For design-mode positioning see `designBandYPos`.
         */
        bandYPos: (state) => (bandId: string): number => {
            const bands = state.page.bands ?? []
            const { top, bottom } = state.page.margin
            return calcBandYPos(bands, bandId, top, state.page.height, bottom)
        },

        /**
         * The rendered height of a band.
         * For 'fill' bands (detail) this is computed as the remaining space.
         */
        bandRenderHeight: (state) => (bandId: string): number => {
            const bands = state.page.bands ?? []
            const band = bands.find(b => b.id === bandId)
            if (!band) return 0

            if (band.anchor !== 'fill') return band.height

            const contentTop = state.page.margin.top
            const contentBottom = state.page.height - state.page.margin.bottom

            const topHeight = bands
                .filter(b => b.anchor === 'top')
                .reduce((s, b) => s + b.height, 0)
            const bottomHeight = bands
                .filter(b => b.anchor === 'bottom')
                .reduce((s, b) => s + b.height, 0)

            return Math.max(0, (contentBottom - contentTop) - topHeight - bottomHeight)
        },

        // ── Design-mode helpers ─────────────────────

        /**
         * Canvas height: sum of all enabled bands + margins.
         * The full paper height is IMAGINARY (not shown in the canvas).
         * Bands must fit within the printable area (page.height - margins);
         * setBandHeight enforces this clamping at the store level.
         * Disabled bands are excluded from layout by pageBands in the canvas.
         */
        designPageHeight: (state): number => {
            const bands = (state.page.bands ?? []).filter(b => b.enabled !== false)
            const totalBands = bands.reduce((s, b) => s + b.height, 0)
            return state.page.margin.top + totalBands + state.page.margin.bottom
        },

        /**
         * Y position in design mode — only enabled bands count.
         * Disabled bands are excluded from layout (same as pageBands in canvas).
         */
        designBandYPos: (state) => (bandId: string): number => {
            const bands = (state.page.bands ?? []).filter(b => b.enabled !== false)
            let y = state.page.margin.top
            for (const b of bands) {
                if (b.id === bandId) return y
                y += b.height
            }
            return y
        },

        /**
         * Band height in design mode — always the stored height.
         * Fill bands show at their configured size; they don't expand
         * to fill remaining space (iReport behavior).
         */
        designBandRenderHeight: (state) => (bandId: string): number => {
            const bands = state.page.bands ?? []
            const band = bands.find(b => b.id === bandId)
            return band?.height ?? 0
        },

        canUndo: (state): boolean => {
            return state.historyIndex >= 0
        },

        canRedo: (state): boolean => {
            return state.historyIndex < state.history.length - 1
        },

        /**
         * Whether the current band layout fits within the printable area.
         * Only enabled bands count toward the total (same as backend).
         */
        bandHeightValid: (state): boolean => {
            return bandHeightOverflowError(state.page) === null
        },

        /**
         * Layout type derived from the rendering engine.
         * - 'dompdf' → 'bands-absolute' (elements with x/y/w/h)
         * - 'pdf-engine' → 'bands-composite' (composite node tree)
         */
        layoutType: (state): LayoutType => {
            return state.engine === 'pdf-engine' ? 'bands-composite' : 'bands-absolute'
        },

        /** The currently selected band object (or null). */
        selectedBand: (state): ReportBand | null => {
            if (!state.selectedBandId || !state.page.bands) return null
            return state.page.bands.find(b => b.id === state.selectedBandId) ?? null
        },

        /** The currently selected composite root (or inner node) — or null. */
        selectedCompositeNode: (state): CompositeRoot | CompositeNode | null => {
            const id = state.selectedCompositeNodeId
            if (!id || !state.page.bands) return null

            // 1) Match a root in band.children
            for (const band of state.page.bands) {
                if (!band.children) continue
                const root = band.children.filter(isCompositeRoot).find(r => r.id === id)
                if (root) return root
            }

            // 2) Match an inner node inside a root's tree
            for (const band of state.page.bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    const found = findCompositeNode(root.node, id)
                    if (found) return found
                }
            }

            // 3) Legacy: search band.content (pre-migration)
            for (const band of state.page.bands) {
                if (!band.content) continue
                const found = findCompositeNode(band.content, id)
                if (found) return found
            }
            return null
        },

        /** The CompositeRoot wrapper if the selected id matches a root — or null. */
        selectedCompositeRoot: (state): CompositeRoot | null => {
            const id = state.selectedCompositeNodeId
            if (!id || !state.page.bands) return null

            for (const band of state.page.bands) {
                if (!band.children) continue
                const root = band.children.filter(isCompositeRoot).find(r => r.id === id)
                if (root) return root
            }
            return null
        },

        /** The inner CompositeNode for the current selection — unwraps root.node if needed. */
        selectedCompositeInnerNode: (state): CompositeNode | null => {
            const id = state.selectedCompositeNodeId
            if (!id || !state.page.bands) return null

            // 1) Match a root → return its inner node
            for (const band of state.page.bands) {
                if (!band.children) continue
                const root = band.children.filter(isCompositeRoot).find(r => r.id === id)
                if (root) return root.node
            }

            // 2) Match an inner node directly
            for (const band of state.page.bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    const found = findCompositeNode(root.node, id)
                    if (found) return found
                }
            }

            // 3) Legacy
            for (const band of state.page.bands) {
                if (!band.content) continue
                const found = findCompositeNode(band.content, id)
                if (found) return found
            }
            return null
        },

        /** Set of node IDs that exceed their parent container's dimensions. */
        compositeConflicts: (state): Set<string> => {
            const conflicts = new Set<string>()
            if (!state.page.bands) return conflicts

            for (const band of state.page.bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    collectConflicts(root.node, conflicts)
                }
            }
            return conflicts
        },

        /** Whether the clipboard contains an element compatible with the current engine. */
        hasCompatibleClipboard: (state): boolean => {
            // Check in-memory first
            if (state.clipboard) {
                return state.clipboard.sourceEngine === state.engine
                    && Date.now() - state.clipboard.timestamp <= 24 * 60 * 60 * 1000
            }
            // Fallback: check localStorage
            try {
                const raw = localStorage.getItem('pdf-designer-clipboard')
                if (!raw) return false
                const entry: ClipboardEntry = JSON.parse(raw)
                return entry.sourceEngine === state.engine
                    && Date.now() - entry.timestamp <= 24 * 60 * 60 * 1000
            } catch {
                return false
            }
        },
    },

    actions: {
        // ── History ────────────────────────────────

        /**
         * Snapshot the current elements array for undo.
         * Called automatically by single-shot actions.
         * Components should call this before starting a
         * drag/resize sequence (moveElement / resizeElement).
         */
        /** Restore `page.bands` (with nested elements) from a history snapshot */
        restoreBands(snapshot: string): void {
            const restored = JSON.parse(snapshot) as ReportBand[]
            // Merge restored elements back into the current page.bands
            if (this.page.bands) {
                // Update each band's elements
                for (const restoredBand of restored) {
                    const existing = this.page.bands.find(b => b.id === restoredBand.id)
                    if (existing) {
                        existing.children = restoredBand.children ?? []
                    }
                }
            } else {
                // Fallback: no bands yet, assign directly
                this.page.bands = restored
            }
        },

        pushHistory(): void {
            // Truncate any redone entries beyond current index
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1)
            }

            const bands = this.page.bands
            this.history.push(bands ? serializeBands(bands) : '[]')
            this.historyIndex = this.history.length - 1

            // Cap history at 100 entries to avoid memory bloat
            if (this.history.length > 100) {
                this.history.shift()
                this.historyIndex--
            }
        },

        /**
         * Undo the last element change.
         */
        undo(): void {
            if (!this.canUndo) return

            const snapshot = this.history[this.historyIndex]
            if (snapshot === undefined) return

            // Save current state at the same index so redo can find it later
            const bands = this.page.bands
            this.history[this.historyIndex] = bands ? serializeBands(bands) : '[]'

            this.historyIndex--
            this.restoreBands(snapshot)
            this.isDirty = true
        },

        /**
         * Redo the last undone element change.
         */
        redo(): void {
            if (!this.canRedo) return

            this.historyIndex++
            const snapshot = this.history[this.historyIndex]
            if (snapshot === undefined) {
                this.historyIndex--
                return
            }

            this.restoreBands(snapshot)
            this.isDirty = true
        },

        // ── Mutations ──────────────────────────────

        /** Get the band that contains an element by id */
        elementBand(id: string): ReportBand | null {
            const bands = this.page.bands
            if (!bands) return null
            return bands.find(b => (b.children ?? []).some(el => el.id === id)) ?? null
        },

        /**
         * Add a new element of the given type to a specific band.
         * `bandId` defaults to the selected band, or 'detail' if none selected.
         * Returns the new element's UUID.
         */
        addElement(
            type: ElementType,
            position?: { x: number; y: number },
            bandId?: string,
        ): string {
            this.pushHistory()
            const id = crypto.randomUUID()
            const defaults = getElementDefaults(type)
            const targetBandId = bandId ?? this.selectedBandId ?? 'detail'

            const bands = this.page.bands
            if (!bands) {
                throw new Error('[designer] Cannot add element — no bands in page')
            }

            const band = bands.find(b => b.id === targetBandId)
            if (!band) {
                throw new Error(`[designer] Cannot add element — band "${targetBandId}" not found`)
            }

            if (!band.children) {
                band.children = []
            }

            // Calculate position relative to band (if absolute position provided)
            let x = position?.x ?? DEFAULT_ELEMENT_POSITION.x
            let y = position?.y ?? DEFAULT_ELEMENT_POSITION.y

            // If a specific band was targeted by drop, position is likely absolute to page.
            // We should convert it to relative if it's within the band.
            // NOTE: Component handles calculating absolute position for drop events.
            // Here we assume if 'position' is passed, it might be absolute.
            // Actually, we'll keep the logic simple: if bandId is provided,
            // the caller (component) should have already adjusted coordinates
            // or we'll do it here.

            band.children.push({
                ...defaults,
                id,
                x,
                y,
            })
            this.isDirty = true

            return id
        },

        /**
         * Remove an element by id. Clears selection if the removed
         * element was currently selected.
         */
        removeElement(id: string): void {
            const bands = this.page.bands
            if (!bands) return

            const found = findElementInBands(bands, id)
            if (!found) return

            this.pushHistory()

            if (found.parentContainerId) {
                // Element is inside a container — remove from container's children
                const container = findElementInBands(bands, found.parentContainerId)
                if (container && container.element.type === 'container') {
                    const content = container.element.content as ContainerContent
                    content.children.splice(found.containerChildIndex!, 1)
                }
            } else {
                // Top-level element — remove from band
                found.band.children.splice(found.index, 1)
            }

            this.selectedElementIds = this.selectedElementIds.filter(eid => eid !== id)

            this.isDirty = true
        },

        /**
         * Partially update an element's properties by merging `patch`.
         */
        updateElement(id: string, patch: Partial<DesignerElement>): void {
            const bands = this.page.bands
            if (!bands) return

            const found = findElementInBands(bands, id)
            if (!found) return

            Object.assign(found.element, patch)
            this.isDirty = true
        },

        /**
         * Set the currently selected element id (or null to clear).
         * Clears any multi-selection.
         */
        selectElement(id: string | null): void {
            this.selectedElementIds = id !== null ? [id] : []
        },

        /**
         * Toggle an element in/out of the current selection (Cmd+click).
         */
        toggleElementSelection(id: string): void {
            const idx = this.selectedElementIds.indexOf(id)
            if (idx === -1) {
                this.selectedElementIds = [...this.selectedElementIds, id]
            } else {
                this.selectedElementIds = [
                    ...this.selectedElementIds.slice(0, idx),
                    ...this.selectedElementIds.slice(idx + 1),
                ]
            }
        },

        /**
         * Replace the entire selection with a set of ids.
         */
        selectElements(ids: string[]): void {
            this.selectedElementIds = ids
        },

        /**
         * Clear the entire selection.
         */
        clearSelection(): void {
            this.selectedElementIds = []
        },

        /**
         * Enter inline edit mode for an element (disables canvas drag for it).
         */
        enterEditMode(id: string): void {
            this.editingElementId = id
        },

        /**
         * Exit inline edit mode.
         */
        exitEditMode(): void {
            this.editingElementId = null
        },

        /**
         * Remove multiple elements by id.
         */
        removeElements(ids: string[]): void {
            const bands = this.page.bands
            if (!bands) return
            this.pushHistory()

            const idSet = new Set(ids)

            // First, recursively remove from container children
            for (const band of bands) {
                const designerChildren = (band.children ?? []).filter(isDesignerChild)
                removeFromContainerChildren(designerChildren, idSet)
            }

            // Then remove from top-level band children
            for (const band of bands) {
                band.children = (band.children ?? []).filter(el => !idSet.has(el.id))
            }

            this.selectedElementIds = this.selectedElementIds.filter(eid => !idSet.has(eid))
            this.isDirty = true
        },

        /**
         * Move multiple elements by the same delta (dx, dy), clamped to their band bounds.
         */
        moveElements(ids: string[], dx: number, dy: number): void {
            const bands = this.page.bands
            if (!bands) return

            for (const id of ids) {
                const found = findElementInBands(bands, id)
                if (!found) continue

                const bHeight = found.band.height
                const contentWidth = this.page.width - this.page.margin.left - this.page.margin.right

                const newX = Math.max(0, Math.min(contentWidth - found.element.width, found.element.x + dx))
                const newY = Math.max(0, Math.min(bHeight - found.element.height, found.element.y + dy))

                found.element.x = newX
                found.element.y = newY
            }

            this.isDirty = true
        },

        /**
         * Move an element to an absolute position within its band.
         * If the element needs to cross bands, use moveElementToBand.
         */
        moveElement(id: string, x: number, y: number): void {
            const bands = this.page.bands
            if (!bands) return

            const found = findElementInBands(bands, id)
            if (!found) return

            found.element.x = x
            found.element.y = y
            this.isDirty = true
        },

        /**
         * Move an element from its current band to another band.
         * Preserves its absolute position on the page by adjusting relative Y.
         * No-op if already in the target band.
         */
        moveElementToBand(elementId: string, targetBandId: string, absoluteY?: number): void {
            const bands = this.page.bands
            if (!bands) return

            const source = findElementInBands(bands, elementId)
            if (!source) return
            if (source.band.id === targetBandId) return

            const targetBand = bands.find(b => b.id === targetBandId)
            if (!targetBand) return

            this.pushHistory()
            const [el] = source.band.children.splice(source.index, 1)

            // If absoluteY is provided, use it to calculate new relative Y.
            // Otherwise, we'd need bandYPos logic here (which is currently in the component).
            // For now, we'll let the component pass absoluteY or adjust it after move.
            if (absoluteY !== undefined) {
                // This will be set by the component
                el.y = absoluteY
            }

            targetBand.children.push(el)
            this.isDirty = true
        },

        /**
         * Resize an element enforcing a minimum of 5 mm in each dimension.
         */
        resizeElement(id: string, width: number, height: number): void {
            const bands = this.page.bands
            if (!bands) return

            const found = findElementInBands(bands, id)
            if (!found) return

            let minW = MIN_ELEMENT_SIZE
            let minH = MIN_ELEMENT_SIZE

            // When resizing a container, constrain minimum dimensions so
            // no absolute child is clipped by the new container bounds.
            if (found.element.type === 'container') {
                const content = found.element.content as ContainerContent
                for (const child of content.children ?? []) {
                    if (child.positionMode !== 'fill') {
                        minW = Math.max(minW, child.x + child.width)
                        minH = Math.max(minH, child.y + child.height)
                    }
                }
            }

            found.element.width = Math.max(minW, width)
            found.element.height = Math.max(minH, height)
            this.isDirty = true
        },

        /**
         * Reorder an element within its band from one index to another.
         */
        reorderElement(fromIndex: number, toIndex: number, bandId?: string): void {
            const bands = this.page.bands
            if (!bands) return

            // If bandId is provided, use that band; otherwise use the containing band
            // of the element referenced by fromIndex — but we need the element id.
            // Simpler: callers pass bandId explicitly, or we find it.
            const targetBand = bandId
                ? bands.find(b => b.id === bandId)
                : null
            if (!targetBand) return

            const els = targetBand.children
            if (!els) return
            if (fromIndex < 0 || fromIndex >= els.length || toIndex < 0 || toIndex >= els.length) {
                return
            }

            this.pushHistory()
            const [el] = els.splice(fromIndex, 1)
            els.splice(toIndex, 0, el)
            this.isDirty = true
        },

        /**
         * Reorder a band within the page bands array.
         */
        reorderBand(fromIndex: number, toIndex: number): void {
            const bands = this.page.bands
            if (!bands) return
            if (fromIndex < 0 || fromIndex >= bands.length || toIndex < 0 || toIndex >= bands.length) {
                return
            }

            this.pushHistory()
            const [band] = bands.splice(fromIndex, 1)
            bands.splice(toIndex, 0, band)
            this.isDirty = true
        },

        /**
         * Deep-clone the element by id, offset it by (10, 10), and
         * select the new duplicate. Places the clone in the same band
         * as the source. Returns the new element's UUID.
         */
        duplicateElement(id: string): string {
            const bands = this.page.bands
            if (!bands) {
                throw new Error('[designer] Cannot duplicate — no bands')
            }

            const source = findElementInBands(bands, id)
            if (!source) {
                throw new Error(`[designer] Cannot duplicate — element ${id} not found`)
            }

            this.pushHistory()
            const newId = crypto.randomUUID()
            const clone: DesignerElement = JSON.parse(JSON.stringify(source.element))
            clone.id = newId
            clone.x = source.element.x + 10
            clone.y = source.element.y + 10

            // Deep clone container children with new UUIDs
            if (clone.type === 'container') {
                const content = clone.content as ContainerContent
                content.children = deepCloneChildren(content.children)
            }

            source.band.children.push(clone)
            this.selectedElementIds = [newId]
            this.isDirty = true

            return newId
        },

        // ── Container child management ─────────────

        /**
         * Add a child element to a container.
         * Accepts optional x/y for position relative to the container's content area.
         * Returns the new child's UUID.
         */
        addChildElement(containerId: string, type: ElementType, x?: number, y?: number): string {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) throw new Error('[designer] Cannot add child — no bands')

            const found = findElementInBands(bands, containerId)
            if (!found) throw new Error(`[designer] Container ${containerId} not found`)
            if (found.element.type !== 'container') throw new Error(`[designer] Element ${containerId} is not a container`)

            const id = crypto.randomUUID()
            const defaults = getElementDefaults(type)
            const child: DesignerElement = {
                ...defaults,
                id,
                x: x ?? defaults.x,
                y: y ?? defaults.y,
            }

            const content = found.element.content as ContainerContent
            content.children.push(child)
            this.isDirty = true
            return id
        },

        /**
         * Remove a child element from a container by id.
         */
        removeChildElement(containerId: string, childId: string): void {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) return

            const found = findElementInBands(bands, containerId)
            if (!found || found.element.type !== 'container') return

            const content = found.element.content as ContainerContent
            const idx = content.children.findIndex(c => c.id === childId)
            if (idx === -1) return

            content.children.splice(idx, 1)
            this.selectedElementIds = this.selectedElementIds.filter(eid => eid !== childId)
            this.isDirty = true
        },

        /**
         * Update a child element's properties via partial patch.
         */
        updateChildElement(containerId: string, childId: string, patch: Partial<DesignerElement>): void {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) return

            const found = findElementInBands(bands, containerId)
            if (!found || found.element.type !== 'container') return

            const content = found.element.content as ContainerContent
            const child = content.children.find(c => c.id === childId)
            if (!child) return

            Object.assign(child, patch)
            this.isDirty = true
        },

        /**
         * Populate the store from an API template resource.
         * Handles all layout versions:
         * - v3 (current): elements inside bands (`page.bands[].children`)
         * - v2: bands + flat elements (`page.bands` + `page.children`)
         * - v1 (legacy): no bands, flat elements in page or config
         */
        loadTemplate(template: PdfTemplate): void {
            this.pushHistory()

            // Read engine from template
            this.engine = (template.engine ?? 'dompdf') as 'dompdf' | 'pdf-engine'

            const pageData = template.page as Record<string, unknown>
            const configData = template.config as Record<string, unknown> | undefined
            const margins = (pageData.margins ?? pageData.margin) as Record<string, number> | undefined
            const loadedBands = pageData?.bands
                ? hydrateBands(pageData.bands as ReportBand[])
                : undefined
            const defaultPage = createDefaultPage()

            // Determine if loaded bands already have elements inside (v3)
            const bandsHaveElements = loadedBands?.some(b => Array.isArray(b.children) && b.children.length > 0)

            this.page = {
                width: (pageData.width as number) ?? defaultPage.width,
                height: (pageData.height as number) ?? defaultPage.height,
                orientation: (pageData.orientation as DesignerPage['orientation']) ?? defaultPage.orientation,
                paperSize: (pageData.paper_size as string) ?? defaultPage.paperSize,
                margin: {
                    top: margins?.top ?? defaultPage.margin.top,
                    right: margins?.right ?? defaultPage.margin.right,
                    bottom: margins?.bottom ?? defaultPage.margin.bottom,
                    left: margins?.left ?? defaultPage.margin.left,
                },
                bands: loadedBands,
            }

            // ── Element migration ────────────────────
            // v3: elements already inside bands — nothing to do
            // v2: bands exist + flat page.children → distribute into bands
            // v1: no bands, flat elements → create default bands, assign all to detail

            // PdfEngine templates keep bands.content as-is — skip migration
            if (this.engine !== 'pdf-engine') {
                if (!loadedBands || loadedBands.length === 0) {
                    // v1 legacy: no bands at all — create defaults and put everything in detail
                    const loadedElements = (pageData?.children as DesignerElement[]) ?? []

                    // Give every band an empty elements array
                    const freshBands = createDefaultBands()

                    // Temporary assign to page for bandYPos to work
                    this.page.bands = freshBands

                    if (loadedElements.length > 0) {
                        // Assign all legacy elements to the detail band
                        const detailTop = this.bandYPos('detail')
                        const marginLeft = this.page.margin.left
                        for (const el of loadedElements) {
                            // Strip legacy bandId if present
                            const { bandId: _, ...clean } = el as DesignerElement & { bandId?: string }
                            // Convert absolute coordinates to relative
                            clean.y = clean.y - detailTop
                            clean.x = clean.x - marginLeft
                            freshBands.find(b => b.id === 'detail')!.children.push(clean)
                        }
                    }

                    this.page.bands = freshBands
                } else if (!bandsHaveElements) {
                    // v2: bands exist but empty — distribute flat elements from page.children
                    const loadedElements = (pageData?.children as DesignerElement[]) ?? []

                    for (const el of loadedElements) {
                        const legacyBandId = (el as unknown as Record<string, string>)['bandId'] ?? 'detail'
                        const targetBand = this.page.bands!.find(b => b.id === legacyBandId)
                        const { bandId: _, ...clean } = el as DesignerElement & { bandId?: string }
                        if (targetBand) {
                            // Convert absolute coordinates to relative
                            const bandTop = this.bandYPos(targetBand.id)
                            const marginLeft = this.page.margin.left
                            clean.y = clean.y - bandTop
                            clean.x = clean.x - marginLeft
                            targetBand.children.push(clean)
                        } else {
                            // Fallback: put in first band if target band doesn't exist
                            const fallbackBand = this.page.bands![0]
                            const bandTop = this.bandYPos(fallbackBand.id)
                            const marginLeft = this.page.margin.left
                            clean.y = clean.y - bandTop
                            clean.x = clean.x - marginLeft
                            fallbackBand.children.push(clean)
                        }
                    }
                }
                // v3: elements already in bands — nothing to do
            }
            // pdf-engine: bands.content is already in the right format — keep as-is
            // Migrate legacy `content` (single root) to `children` (array of roots with x/y)
            if (this.engine === 'pdf-engine' && this.page.bands) {
                migrateLegacyContent(this.page.bands)
            }

            this.templateId = template.id
            this.templateName = template.name
            this.templateSlug = template.slug
            this.templateDescription = template.description
            this.datasources = (configData?.datasources as DatasourceConfig[]) ?? []
            this.discoveredFields = (configData?.discoveredFields as DiscoveredField[]) ?? []

            // Re-clamp band heights — loaded bands may exceed the loaded page's printable area
            this.clampBandHeights()

            this.originalBands = serializeBands(this.page.bands ?? [])
            this.bandHeightError = bandHeightOverflowError(this.page)
            this.isDirty = false
            this.isLoading = false
        },

        /**
         * Build a create/update payload from the current store state.
         * Elements now live inside page.bands[].children — no top-level page.children.
         *
         * Maps internal DesignerPage (margin, orientation) to API format (margins, orientation).
         * Snapshots bands for future dirty-checking.
         *
         * Auto-generates `slug` from `name` if slug is empty.
         */
        saveTemplate(): CreateTemplatePayload {
            // Validate band heights before saving — reject overflow
            const error = bandHeightOverflowError(this.page)
            if (error) {
                this.bandHeightError = error
                throw new Error(error)
            }
            this.bandHeightError = null

            // Snapshot bands for dirty detection
            this.originalBands = serializeBands(this.page.bands ?? [])
            this.isDirty = false

            // Coerce undefined/null → empty string before any operations
            const rawName = this.templateName ?? ''
            const rawSlug = this.templateSlug ?? ''

            // Auto-fill slug from name if empty
            if (!rawSlug || rawSlug.trim() === '') {
                this.templateSlug = rawName
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    || 'untitled'
            }

            // Ensure name is never blank
            const name = rawName.trim() || 'Untitled'

            // Build config — only persist datasources/discoveredFields when non-empty,
            // so legacy templates without them stay clean.
            const config: Record<string, unknown> = {}
            if (this.datasources.length > 0) {
                config.datasources = this.datasources
            }
            if (this.discoveredFields.length > 0) {
                config.discoveredFields = this.discoveredFields
            }

            const page: Record<string, unknown> = {
                width: this.page.width,
                height: this.page.height,
                orientation: this.page.orientation,
                paper_size: this.page.paperSize,
                margins: { ...this.page.margin },
                // NO top-level elements — they live inside bands now
            }

            // Serialize bands — children only, no content
            if (this.page.bands && this.page.bands.length > 0) {
                page.bands = this.page.bands.map(b => {
                    const band: Record<string, unknown> = { ...b }
                    const allChildren = (b.children ?? []) as BandChild[]

                    if (this.engine === 'dompdf' || !this.engine) {
                        // DOMPDF: only DesignerElement children
                        band.children = allChildren.filter(isDesignerChild)
                    }
                    // pdf-engine: keep all BandChild types (CompositeRoot + DesignerElement)

                    // Never save content — children is the single source of truth
                    delete band.content
                    delete band.elements

                    return band
                })
            }

            return {
                name,
                slug: this.templateSlug,
                description: this.templateDescription,
                page: page as unknown as Record<string, unknown>,
                config: config as unknown as Record<string, unknown>,
                engine: this.engine,
            }
        },

        // ── Bands ──────────────────────────────────

        /**
         * Set the height of a band.
         * Minimum is 0 — the resize handler stops at the content boundary.
         */
        setBandHeight(bandId: string, height: number): void {
            if (!this.page.bands) return
            const band = this.page.bands.find(b => b.id === bandId)
            if (!band) return

            // Calculate max height: printable area minus other enabled bands
            const printableHeight = this.page.height - this.page.margin.top - this.page.margin.bottom
            const enabled = this.page.bands.filter(b => b.enabled !== false)
            const otherBandsHeight = enabled
                .filter(b => b.id !== bandId)
                .reduce((s, b) => s + b.height, 0)
            const maxAllowed = printableHeight - otherBandsHeight

            band.height = Math.max(0, Math.min(height, maxAllowed))
            this.isDirty = true
            this.bandHeightError = bandHeightOverflowError(this.page)
        },

        /**
         * Re-clamp all enabled band heights to fit within the printable area.
         * Called after page dimensions or margin changes that shrink the available space.
         * Bands are clamped in order; later bands get the remaining space.
         */
        clampBandHeights(): void {
            if (!this.page.bands) return
            const printableHeight = this.page.height - this.page.margin.top - this.page.margin.bottom
            let used = 0
            for (const band of this.page.bands.filter(b => b.enabled !== false)) {
                const remaining = printableHeight - used
                band.height = Math.max(0, Math.min(band.height, remaining))
                used += band.height
            }
            this.bandHeightError = bandHeightOverflowError(this.page)
        },

        /**
         * Partially update band metadata such as datasource/collection binding.
         */
        updateBand(bandId: string, patch: Partial<ReportBand>): void {
            if (!this.page.bands) return
            const band = this.page.bands.find(b => b.id === bandId)
            if (!band) return
            Object.assign(band, patch)
            this.isDirty = true
            this.bandHeightError = bandHeightOverflowError(this.page)
        },

        /**
         * Select a band by id (or null to deselect).
         */
        selectBand(bandId: string | null): void {
            this.selectedBandId = bandId
        },

        // ── Page margins ───────────────────────────

        /**
         * Set one page margin, enforcing minimums.
         * Minimum per side: 0mm. Content area must be ≥ 30mm.
         */
        setMargin(side: 'top' | 'right' | 'bottom' | 'left', value: number): void {
            const MIN_MARGIN = 0
            const MIN_CONTENT = 30 // mm — content can't collapse

            const clamped = Math.max(MIN_MARGIN, value)

            const m = { ...this.page.margin, [side]: clamped }

            // Ensure opposite margins still leave minimum content
            if (m.left + m.right > this.page.width - MIN_CONTENT) {
                m[side] = this.page.margin[side] // revert
                return
            }
            if (m.top + m.bottom > this.page.height - MIN_CONTENT) {
                m[side] = this.page.margin[side] // revert
                return
            }

            this.page.margin = m
            this.isDirty = true

            // Re-clamp band heights — larger margins shrink the printable area
            this.clampBandHeights()
        },

        // ── Page dimensions ──────────────────────────

        /**
         * Update page dimensions including paper size, orientation,
         * and custom width/height.
         *
         * - If `paperSize` is a known preset, width/height are auto-set
         *   (and orientation swaps them for landscape).
         * - If `paperSize` is 'Custom' or null, width/height are used as-is
         *   and orientation is just a label.
         */
        setPageDimensions(dims: {
            width: number
            height: number
            orientation: 'portrait' | 'landscape'
            paperSize: string | null
        }): void {
            this.pushHistory()

            const MIN_DIM = 50  // mm — smallest allowed page dimension

            let { width, height, orientation, paperSize } = dims

            // Apply preset dimensions if it's a known paper size
            if (paperSize && PAPER_SIZES[paperSize]) {
                const preset = PAPER_SIZES[paperSize]
                width = preset.width
                height = preset.height
                if (orientation === 'landscape') {
                    ;[width, height] = [height, width]
                }
            }

            this.page.width = Math.max(MIN_DIM, width)
            this.page.height = Math.max(MIN_DIM, height)
            this.page.orientation = orientation
            this.page.paperSize = paperSize ?? null
            this.isDirty = true

            // Re-clamp band heights — shrinking the page may push bands past printable area
            this.clampBandHeights()
        },

        // ── Datasources ────────────────────────────

        /**
         * Add a new datasource with a generated UUID.
         * Both `id` and `lastError` are managed internally.
         * Returns the new datasource's id.
         */
        addDatasource(config: Omit<DatasourceConfig, 'id' | 'lastError'>): string {
            const id = crypto.randomUUID()
            this.datasources.push({ ...config, id, lastError: null })
            this.isDirty = true
            return id
        },

        /**
         * Partially update a datasource by id.
         * Silently no-ops if the datasource doesn't exist.
         */
        updateDatasource(id: string, patch: Partial<DatasourceConfig>): void {
            const ds = this.datasources.find(d => d.id === id)
            if (!ds) return
            Object.assign(ds, patch)
            this.isDirty = true
        },

        /**
         * Remove a datasource by id and its associated discovered fields.
         * Silently no-ops if the datasource doesn't exist.
         */
        removeDatasource(id: string): void {
            const idx = this.datasources.findIndex(d => d.id === id)
            if (idx === -1) return
            this.datasources.splice(idx, 1)
            // Remove associated discovered fields
            this.discoveredFields = this.discoveredFields.filter(f => f.datasourceId !== id)
            this.isDirty = true
        },

        /**
         * Replace all discovered fields for a given datasource.
         * Old fields for that datasource are removed; new ones are added.
         */
        setDiscoveredFields(datasourceId: string, fields: DiscoveredField[]): void {
            // Remove old fields for this datasource, add new ones
            this.discoveredFields = [
                ...this.discoveredFields.filter(f => f.datasourceId !== datasourceId),
                ...fields,
            ]
            this.isDirty = true
        },

        /**
         * Mark a datasource with test results.
         * Called by components after calling the API.
         *
         * On success: clears lastError and populates discoveredFields.
         * On failure: sets lastError and removes stale discoveredFields.
         */
        setDatasourceTestResult(
            datasourceId: string,
            result: { success: boolean; fields?: DiscoveredField[]; error?: string },
        ): void {
            const ds = this.datasources.find(d => d.id === datasourceId)
            if (!ds) return

            if (result.success) {
                ds.lastError = null
                this.setDiscoveredFields(datasourceId, result.fields ?? [])
            } else {
                ds.lastError = result.error ?? 'Unknown error'
                // Remove stale fields on failure
                this.discoveredFields = this.discoveredFields.filter(
                    f => f.datasourceId !== datasourceId,
                )
            }
            this.isDirty = true
        },

        /**
         * Set the rendering engine for the template.
         * Used at template creation time — engine is immutable thereafter.
         */
        setEngine(engine: 'dompdf' | 'pdf-engine'): void {
            this.engine = engine
            this.isDirty = true
        },

        /**
         * Toggle container draw mode on/off.
         * When active, clicking and dragging on the canvas creates a container
         * element instead of the normal select/drag behavior.
         */
        toggleContainerDrawMode(): void {
            this.containerDrawMode = !this.containerDrawMode
        },

        // ── Composite nodes ──────────────────────────

        /**
         * Select a composite node by id (or null to deselect).
         * Also clears element selection when switching to composite mode.
         */
        selectCompositeNode(id: string | null): void {
            this.selectedCompositeNodeId = id
            if (this.engine === 'pdf-engine') {
                this.selectedElementIds = []
            }
        },

        /**
         * Add a composite node as a new absolutely-positioned root in the
         * given band's `children` array. Returns the new root id.
         */
        addCompositeNode(bandId: string, type: CompositeNodeType): string {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) throw new Error('[designer] Cannot add composite node — no bands')

            const band = bands.find(b => b.id === bandId)
            if (!band) throw new Error(`[designer] Band "${bandId}" not found`)

            // Ensure the band has a children array (new shape)
            if (!band.children) band.children = []

            const nodeId = crypto.randomUUID()
            const node = createCompositeNode(type, nodeId)

            // Labels at root level default to 10mm width
            if (type === 'Label') {
                (node as { width?: number }).width = 10
            }

            const rootId = crypto.randomUUID()

            const root: CompositeRoot = {
                id: rootId,
                x: 10,
                y: 10,
                node,
            }

            // Mirror node dimensions onto the root wrapper so the canvas
            // renders the correct size and the properties panel shows them.
            if (node.type === 'VBox' || node.type === 'HBox') {
                root.width = node.width
                root.height = node.height
            } else if (node.type === 'Label') {
                root.width = (node as { width?: number }).width
                // Default height based on fontSize (pt → mm approximation)
                if (!(node as { height?: number }).height) {
                    const fontSize = (node as { fontSize?: number }).fontSize ?? 12
                    ;(node as { height?: number }).height = Math.max(5, Math.round(fontSize * 0.5))
                }
                root.height = (node as { height?: number }).height
            } else if (node.type === 'Image') {
                root.width = (node as { width?: number }).width
                root.height = (node as { height?: number }).height
            }

            band.children.push(root)

            this.selectedCompositeNodeId = rootId
            this.selectedElementIds = []
            this.selectedBandId = bandId
            this.isDirty = true
            return rootId
        },

        /**
         * Add a composite node as a new absolutely-positioned root in the
         * given band's `children` array, with custom text for Label nodes.
         * Used by field drag-and-drop to create Label nodes with
         * `{{ field.path }}` binding syntax.
         */
        addCompositeNodeWithText(bandId: string, type: CompositeNodeType, text: string): string {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) throw new Error('[designer] Cannot add composite node — no bands')

            const band = bands.find(b => b.id === bandId)
            if (!band) throw new Error(`[designer] Band "${bandId}" not found`)

            if (!band.children) band.children = []

            const nodeId = crypto.randomUUID()
            const node = createCompositeNode(type, nodeId)

            // Set custom text for Label nodes
            if (type === 'Label') {
                (node as { text: string }).text = text
                ;(node as { width?: number }).width = 10
            }

            const rootId = crypto.randomUUID()

            const root: CompositeRoot = {
                id: rootId,
                x: 10,
                y: 10,
                node,
            }

            if (node.type === 'VBox' || node.type === 'HBox') {
                root.width = node.width
                root.height = node.height
            } else if (node.type === 'Label') {
                root.width = (node as { width?: number }).width
                if (!(node as { height?: number }).height) {
                    const fontSize = (node as { fontSize?: number }).fontSize ?? 12
                    ;(node as { height?: number }).height = Math.max(5, Math.round(fontSize * 0.5))
                }
                root.height = (node as { height?: number }).height
            } else if (node.type === 'Image') {
                root.width = (node as { width?: number }).width
                root.height = (node as { height?: number }).height
            }

            band.children.push(root)

            this.selectedCompositeNodeId = rootId
            this.selectedElementIds = []
            this.selectedBandId = bandId
            this.isDirty = true
            return rootId
        },

        /**
         * Add a child composite node to a parent VBox/HBox identified by
         * parentId. The parent is searched inside band.children[].node trees.
         * Returns the new child's id, or null if the parent is missing or
         * is not a container (so callers can handle invalid drops gracefully).
         */
        addChildCompositeNode(parentId: string, type: CompositeNodeType): string | null {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) return null

            let parent: CompositeNode | null = null
            for (const band of bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    const found = findCompositeNode(root.node, parentId)
                    if (found) {
                        parent = found
                        break
                    }
                }
                if (parent) break
            }
            if (!parent) return null
            if (!('children' in parent)) return null

            const id = crypto.randomUUID()
            const node = createCompositeNode(type, id)

            // Labels default to 10mm width unless inside a VBox
            if (type === 'Label' && parent.type !== 'VBox') {
                (node as { width?: number }).width = 10
            }

            // Clamp new child dimensions to fit inside the parent container
            if (parent.type === 'HBox' || parent.type === 'VBox') {
                const isHBox = parent.type === 'HBox'
                const mainAxis = isHBox ? 'width' : 'height'
                const crossAxis = isHBox ? 'height' : 'width'

                // Main axis: siblings + new child ≤ parent
                const siblingsSum = parent.children.reduce(
                    (sum, c) => sum + getNodeSize(c, mainAxis), 0,
                )
                const parentMain = getNodeSize(parent, mainAxis)
                const available = Math.max(5, parentMain - siblingsSum)
                const childMain = getNodeSize(node, mainAxis)
                if (childMain > available) {
                    setNodeSize(node, mainAxis, available)
                }

                // Cross axis: child ≤ parent
                const parentCross = getNodeSize(parent, crossAxis)
                const childCross = getNodeSize(node, crossAxis)
                if (childCross > parentCross) {
                    setNodeSize(node, crossAxis, parentCross)
                }

                // Image cross-axis dimension is locked to the parent's cross dimension.
                if (type === 'Image') {
                    if (parent.type === 'VBox') {
                        (node as ImageNode).width = getNodeSize(parent, 'width')
                    } else {
                        (node as ImageNode).height = getNodeSize(parent, 'height')
                    }
                }
            }

            parent.children.push(node)

            this.selectedCompositeNodeId = id
            this.isDirty = true
            return id
        },

        /**
         * Add a child composite node to a parent container with custom text
         * for Label nodes. Used by field drag-and-drop into VBox/HBox containers
         * to create Label nodes with `{{ field.path }}` binding syntax.
         */
        addChildCompositeNodeWithText(parentId: string, type: CompositeNodeType, text: string): string | null {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) return null

            let parent: CompositeNode | null = null
            for (const band of bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    const found = findCompositeNode(root.node, parentId)
                    if (found) {
                        parent = found
                        break
                    }
                }
                if (parent) break
            }
            if (!parent) return null
            if (!('children' in parent)) return null

            const id = crypto.randomUUID()
            const node = createCompositeNode(type, id)

            // Set custom text for Label nodes
            if (type === 'Label') {
                (node as { text: string }).text = text
                if (parent.type !== 'VBox') {
                    ;(node as { width?: number }).width = 10
                }
            }

            // Clamp new child dimensions to fit inside the parent container
            if (parent.type === 'HBox' || parent.type === 'VBox') {
                const isHBox = parent.type === 'HBox'
                const mainAxis = isHBox ? 'width' : 'height'
                const crossAxis = isHBox ? 'height' : 'width'

                const siblingsSum = parent.children.reduce(
                    (sum, c) => sum + getNodeSize(c, mainAxis), 0,
                )
                const parentMain = getNodeSize(parent, mainAxis)
                const available = Math.max(5, parentMain - siblingsSum)
                const childMain = getNodeSize(node, mainAxis)
                if (childMain > available) {
                    setNodeSize(node, mainAxis, available)
                }

                const parentCross = getNodeSize(parent, crossAxis)
                const childCross = getNodeSize(node, crossAxis)
                if (childCross > parentCross) {
                    setNodeSize(node, crossAxis, parentCross)
                }
            }

            parent.children.push(node)

            this.selectedCompositeNodeId = id
            this.isDirty = true
            return id
        },

        /**
         * Update a composite node or root wrapper via partial patch.
         * If nodeId matches a root wrapper, applies x/y/width/height to the root.
         * Otherwise searches inside band.children[].node trees for an inner node.
         */
        updateCompositeNode(nodeId: string, patch: Partial<CompositeNode>): void {
            const bands = this.page.bands
            if (!bands) return

            // 1) Check if nodeId matches a root wrapper
            for (const band of bands) {
                if (!band.children) continue
                const rootIdx = band.children.findIndex(c => c.id === nodeId && isCompositeRoot(c))
                if (rootIdx !== -1) {
                    const root = band.children[rootIdx] as CompositeRoot
                    if ('x' in patch) root.x = patch.x as number
                    if ('y' in patch) root.y = patch.y as number
                    if ('width' in patch) root.width = patch.width as number
                    if ('height' in patch) root.height = patch.height as number
                    this.isDirty = true
                    return
                }
            }

            // 2) Search inside root.node trees for an inner node
            for (const band of bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    // If nodeId matches the root's inner node, also sync root wrapper
                    if (root.node.id === nodeId) {
                        Object.assign(root.node, patch)
                        if ('width' in patch) root.width = patch.width as number | undefined
                        if ('height' in patch) root.height = patch.height as number | undefined
                        this.isDirty = true
                        return
                    }
                    const node = findCompositeNode(root.node, nodeId)
                    if (node) {
                        Object.assign(node, patch)
                        this.isDirty = true
                        return
                    }
                }
            }

            // Legacy: search band.content
            for (const band of bands) {
                if (!band.content) continue
                const node = findCompositeNode(band.content, nodeId)
                if (node) {
                    Object.assign(node, patch)
                    this.isDirty = true
                    return
                }
            }
        },

        /**
         * Update a CompositeRoot's absolute position (x, y) and/or size
         * (width, height). Used by the canvas drag/resize handlers.
         */
        updateCompositeRoot(rootId: string, patch: Partial<CompositeRoot>): void {
            const bands = this.page.bands
            if (!bands) return
            const root = findCompositeRoot(bands, rootId)
            if (!root) return
            if (patch.x !== undefined) root.x = patch.x
            if (patch.y !== undefined) root.y = patch.y
            if (patch.width !== undefined) root.width = patch.width
            if (patch.height !== undefined) root.height = patch.height
            this.isDirty = true
        },

        /**
         * Move a CompositeRoot to an absolute x/y within its band.
         * Clamps to the band's content area.
         */
        moveCompositeRoot(rootId: string, x: number, y: number): void {
            const bands = this.page.bands
            if (!bands) return
            const root = findCompositeRoot(bands, rootId)
            if (!root) return
            const band = findBandByRootId(bands, rootId)
            if (!band) return

            const contentWidth = this.page.width - this.page.margin.left - this.page.margin.right
            const bandHeight = band.height
            const rootW = root.width ?? compositeNodeWidth(root.node)
            const rootH = root.height ?? compositeNodeHeight(root.node)

            root.x = Math.max(0, Math.min(x, contentWidth - rootW))
            root.y = Math.max(0, Math.min(y, bandHeight - rootH))
            this.isDirty = true
        },

        /**
         * Resize a CompositeRoot (x, y, width, height) — used by the
         * canvas resize handles. Clamps to the band's content area.
         */
        resizeCompositeRoot(rootId: string, x: number, y: number, width: number, height: number): void {
            const bands = this.page.bands
            if (!bands) return
            const root = findCompositeRoot(bands, rootId)
            if (!root) return
            const band = findBandByRootId(bands, rootId)
            if (!band) return

            const contentWidth = this.page.width - this.page.margin.left - this.page.margin.right
            const bandHeight = band.height

            root.x = Math.max(0, Math.min(x, contentWidth - 5))
            root.y = Math.max(0, Math.min(y, bandHeight - 5))
            root.width = Math.max(5, Math.min(width, contentWidth - root.x))
            root.height = Math.max(5, Math.min(height, bandHeight - root.y))

            // Keep nodes in sync with their absolute root box.
            if (root.node.type === 'VBox' || root.node.type === 'HBox' || root.node.type === 'Label' || root.node.type === 'Image' || root.node.type === 'Shape') {
                const patch: Record<string, unknown> = {
                    width: root.width,
                    height: root.height,
                }
                // Shape uses w/h instead of width/height
                if (root.node.type === 'Shape') {
                    patch.w = root.width
                    patch.h = root.height
                    delete patch.width
                    delete patch.height
                }
                this.updateCompositeNode(root.node.id, patch as Partial<CompositeNode>)
            }

            // Propagate VBox width to child Labels, HBoxes and Images
            if (root.node.type === 'VBox') {
                propagateWidthToChildren(root.node, root.width)
            }

            // Propagate HBox height to child Labels, VBoxes and Images
            if (root.node.type === 'HBox') {
                propagateHeightToChildren(root.node, root.height)
            }

            this.isDirty = true
        },

        /**
         * Resize a nested composite node along one dimension.
         * Enforces parent-container constraints:
         * - Main axis: child + siblings ≤ parent (HBox main = width, VBox main = height)
         * - Cross axis: child ≤ parent cross dimension
         * The field updated depends on the node type:
         * - width → `width` on VBox/HBox/Label, `w` on Shape
         * - height → `height` on VBox/HBox, `h` on Shape
         * Label has no height and Table cannot be resized on the canvas.
         */
        resizeCompositeNode(nodeId: string, dimension: 'width' | 'height', value: number): void {
            const bands = this.page.bands
            if (!bands) return

            for (const band of bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    // Search root.node tree inline, tracking the parent container
                    const result = findNodeWithParent(root.node, nodeId)
                    if (!result) continue

                    const { node, parent } = result
                    let clamped = Math.max(5, value)

                    // Apply parent-container constraints when parent is a flow container
                    if (parent && (parent.type === 'HBox' || parent.type === 'VBox')) {
                        const isHBox = parent.type === 'HBox'
                        const mainAxis = isHBox ? 'width' : 'height'
                        const crossAxis = isHBox ? 'height' : 'width'

                        if (dimension === mainAxis) {
                            // Main axis: child + siblings ≤ parent
                            const siblingsSum = parent.children
                                .filter(c => c.id !== nodeId)
                                .reduce((sum, c) => sum + getNodeSize(c, mainAxis), 0)
                            const parentSize = getNodeSize(parent, mainAxis)
                            const maxForChild = Math.max(5, parentSize - siblingsSum)
                            clamped = Math.min(clamped, maxForChild)
                        } else {
                            // Cross axis: child ≤ parent cross dimension
                            const parentCross = getNodeSize(parent, crossAxis)
                            clamped = Math.min(clamped, parentCross)
                        }
                    }

                    if (dimension === 'width') {
                        if (node.type === 'Shape') {
                            const shape = node as { w?: number; h?: number; shapeType?: string }
                            shape.w = clamped
                            if (shape.shapeType === 'circle') {
                                shape.h = clamped
                            }
                        } else if (node.type === 'Image') {
                            const img = node as { width?: number; height?: number; shapeType?: string }
                            img.width = clamped
                            if (img.shapeType === 'circle') {
                                img.height = clamped
                            }
                        } else if (node.type === 'VBox' || node.type === 'HBox' || node.type === 'Label') {
                            (node as { width?: number }).width = clamped
                        }
                        // Propagate VBox width to child Labels, HBoxes and Images
                        if (node.type === 'VBox' && 'children' in node) {
                            propagateWidthToChildren(node, clamped)
                        }
                    } else {
                        if (node.type === 'Shape') {
                            const shape = node as { w?: number; h?: number; shapeType?: string }
                            shape.h = clamped
                            if (shape.shapeType === 'circle') {
                                shape.w = clamped
                            }
                        } else if (node.type === 'Image') {
                            const img = node as { width?: number; height?: number; shapeType?: string }
                            img.height = clamped
                            if (img.shapeType === 'circle') {
                                img.width = clamped
                            }
                        } else if (node.type === 'VBox' || node.type === 'HBox' || node.type === 'Label') {
                            (node as { height?: number }).height = clamped
                        }
                        // Propagate HBox height to child Labels, VBoxes and Images
                        if (node.type === 'HBox' && 'children' in node) {
                            propagateHeightToChildren(node, clamped)
                        }
                    }

                    this.isDirty = true
                    return
                }
            }
        },

        /**
         * Fit all children of a container to its dimensions.
         * Main axis: proportional shrink (factor = parent / sum_children).
         * Cross axis: clamp each child to parent cross dimension.
         */
        fitCompositeChildren(parentId: string): void {
            const bands = this.page.bands
            if (!bands) return
            this.pushHistory()

            for (const band of bands) {
                if (!band.children) continue
                for (const root of band.children.filter(isCompositeRoot)) {
                    const parent = findCompositeNode(root.node, parentId)
                    if (!parent || !('children' in parent)) continue
                    if (parent.type !== 'HBox' && parent.type !== 'VBox') continue

                    const isHBox = parent.type === 'HBox'
                    const mainAxis = isHBox ? 'width' : 'height'
                    const crossAxis = isHBox ? 'height' : 'width'

                    const parentMain = getNodeSize(parent, mainAxis)
                    const parentCross = getNodeSize(parent, crossAxis)
                    const children = (parent as VBoxNode | HBoxNode).children
                    const sumMain = children.reduce((s, c) => s + getNodeSize(c, mainAxis), 0)

                    // Proportional shrink on main axis
                    const factor = sumMain > parentMain ? parentMain / sumMain : 1

                    for (const child of children) {
                        if (factor < 1) {
                            const newMain = Math.max(5, Math.round(getNodeSize(child, mainAxis) * factor))
                            setNodeSize(child, mainAxis, newMain)
                        }

                        // Clamp cross axis
                        const childCross = getNodeSize(child, crossAxis)
                        if (childCross > parentCross) {
                            setNodeSize(child, crossAxis, parentCross)
                        }
                    }

                    this.isDirty = true
                    return
                }
            }
        },

        /**
         * Remove a composite node by id. If the id matches a root in
         * band.children, splices that root out. Otherwise searches
         * inside root.node trees for an inner node.
         */
        removeCompositeNode(nodeId: string): void {
            this.pushHistory()
            const bands = this.page.bands
            if (!bands) return

            // New shape: try removing a root from band.children
            for (const band of bands) {
                if (!band.children) continue
                const compositeChildren = band.children.filter(isCompositeRoot)
                const fullIdx = band.children.findIndex(c => c.id === nodeId && isCompositeRoot(c))
                if (fullIdx !== -1) {
                    band.children.splice(fullIdx, 1)
                    if (this.selectedCompositeNodeId === nodeId) {
                        this.selectedCompositeNodeId = null
                    }
                    this.isDirty = true
                    return
                }
                // Try removing an inner node from each root's tree
                for (const root of compositeChildren) {
                    if (removeCompositeNodeFromTree(root.node, nodeId)) {
                        if (this.selectedCompositeNodeId === nodeId) {
                            this.selectedCompositeNodeId = null
                        }
                        this.isDirty = true
                        return
                    }
                }
            }

            // Legacy: search band.content
            for (const band of bands) {
                if (!band.content) continue
                if ('children' in band.content) {
                    const idx = band.content.children.findIndex((c: CompositeNode) => c.id === nodeId)
                    if (idx !== -1) {
                        band.content.children.splice(idx, 1)
                        if (this.selectedCompositeNodeId === nodeId) {
                            this.selectedCompositeNodeId = null
                        }
                        this.isDirty = true
                        return
                    }
                    if (removeCompositeNodeFromTree(band.content, nodeId)) {
                        if (this.selectedCompositeNodeId === nodeId) {
                            this.selectedCompositeNodeId = null
                        }
                        this.isDirty = true
                        return
                    }
                }
            }
        },

        // ── Clipboard (copy/paste) ──────────────────

        /**
         * Copy a CompositeRoot to the clipboard (in-memory + localStorage).
         * The root is deep-cloned with new UUIDs so the copy is independent.
         */
        copyCompositeRoot(rootId: string): boolean {
            const bands = this.page.bands
            if (!bands) return false

            const root = findCompositeRoot(bands, rootId)
            if (!root) return false

            const clone = deepCloneCompositeRoot(root)
            this.clipboard = {
                element: clone,
                sourceTemplateId: this.templateId ?? 0,
                sourceEngine: this.engine,
                timestamp: Date.now(),
            }

            // Persist to localStorage
            try {
                localStorage.setItem('pdf-designer-clipboard', JSON.stringify(this.clipboard))
            } catch { /* quota exceeded or private mode */ }

            return true
        },

        /**
         * Paste the clipboard content into a band. Returns the new root's ID
         * or null if paste is not possible.
         *
         * @param targetBandId  Band to paste into. Falls back to selectedBand
         *                      or the first enabled band.
         */
        pasteCompositeRoot(targetBandId?: string): string | null {
            // 1) Read clipboard from state or localStorage
            let entry = this.clipboard
            if (!entry) {
                try {
                    const raw = localStorage.getItem('pdf-designer-clipboard')
                    if (raw) entry = JSON.parse(raw)
                } catch { /* corrupted */ }
            }
            if (!entry) return null

            // 2) Validate engine compatibility
            if (entry.sourceEngine !== this.engine) {
                return null // caller should show toast "No se puede pegar: engine distinto"
            }

            // 3) Discard stale clipboard (>24h)
            if (Date.now() - entry.timestamp > 24 * 60 * 60 * 1000) {
                this.clipboard = null
                try { localStorage.removeItem('pdf-designer-clipboard') } catch {}
                return null
            }

            // 4) Resolve target band
            const bands = this.page.bands
            if (!bands) return null
            let band: ReportBand | null = bands.find(b => b.id === targetBandId) ?? null
            if (!band) band = this.selectedBand
            if (!band) band = bands.find(b => b.type === 'detail') ?? null
            if (!band) return null

            // 5) Deep-clone the clipboard element with fresh UUIDs
            const clone = deepCloneCompositeRoot(entry.element)

            // 6) Offset position slightly so it doesn't overlap the original
            clone.x = Math.max(0, clone.x + 5)
            clone.y = Math.max(0, clone.y + 5)

            // 7) Ensure band has a children array
            if (!band.children) band.children = []

            // 8) Insert
            this.pushHistory()
            band.children.push(clone)
            this.selectedCompositeNodeId = clone.id
            this.selectedBandId = band.id
            this.isDirty = true

            return clone.id
        },

        /**
         * Clear the clipboard (in-memory + localStorage).
         */
        clearClipboard(): void {
            this.clipboard = null
            try { localStorage.removeItem('pdf-designer-clipboard') } catch {}
        },

        /**
         * Reset all state to defaults.
         */
        reset(): void {
            this.pushHistory()
            this.$reset()
        },
    },
})
