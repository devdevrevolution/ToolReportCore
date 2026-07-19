import { ref, shallowRef, computed, type Ref } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement } from '@/types/designer'

const DEFAULT_SNAP = 1
const MIN_ELEMENT_SIZE = 5

export type ResizeHandleCorner = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w'

function snapToGrid(value: number, gridSize: number): number {
    return Math.round(value / gridSize) * gridSize
}

function clamp(val: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, val))
}

export interface ElementDragOptions {
    findElementById?: (id: string) => DesignerElement | undefined
    getElementBand?: (id: string) => { id: string; height: number; elements?: DesignerElement[] } | undefined
    bandRenderHeight?: (band: { height: number }) => number
    snapGridSize?: Ref<number>
}

export function useElementDrag(
    scale: Ref<number>,
    options?: ElementDragOptions,
) {
    const snapSize = computed(() => options?.snapGridSize?.value ?? DEFAULT_SNAP)
    const store = useDesignerStore()

    // ── Drag state (module-level in closure) ────

    let dragElementIds: string[] = []
    let dragStartClientX = 0
    let dragStartClientY = 0
    let dragOrigPositions = new Map<string, { x: number; y: number }>()

    // ── Reactive drag preview ──

    const isDragging = ref(false)
    const dragPreviewPositions = shallowRef<Record<string, { x: number; y: number }>>({})

    // ── Resize state (module-level in closure) ──

    let resizeElementId: string | null = null
    let resizeHandle: ResizeHandleCorner | null = null
    let resizeStartClientX = 0
    let resizeStartClientY = 0
    let resizeOrigX = 0
    let resizeOrigY = 0
    let resizeOrigW = 0
    let resizeOrigH = 0

    // ── Helpers (with dependency injection) ─────

    const findEl = options?.findElementById ?? ((id: string): DesignerElement | undefined => {
        const bands = store.page.bands ?? []
        for (const band of bands) {
            const el = (band.children ?? []).find(e => e.id === id)
            if (el) return el as DesignerElement
        }
        return undefined
    })

    const getBand = options?.getElementBand ?? ((id: string) => {
        const bands = store.page.bands ?? []
        return bands.find(b => (b.children ?? []).some(el => el.id === id))
    })

    const bandH = options?.bandRenderHeight ?? ((band: { height: number }) => band.height)

    // ── Element selection + drag start ──────────

    function onElementMouseDown(e: MouseEvent, el: DesignerElement): void {
        if (el.locked) return

        // Exit edit mode when clicking a different element
        if (store.editingElementId && store.editingElementId !== el.id) {
            store.exitEditMode()
        }

        // Skip canvas drag entirely when this element is in inline edit mode
        if (store.editingElementId === el.id) return

        if (e.metaKey || e.ctrlKey) {
            store.toggleElementSelection(el.id)
            return
        }

        const isAlreadySelected = store.isSelected(el.id)
        if (!isAlreadySelected) {
            store.selectElement(el.id)
        }

        const dragIds = store.selectedElementIds.length > 0
            ? store.selectedElementIds
            : [el.id]

        store.pushHistory()

        const dragOrigins = new Map<string, { x: number; y: number }>()
        const bands = store.page.bands ?? []
        for (const id of dragIds) {
            for (const band of bands) {
                const found = (band.children ?? []).find(e => e.id === id)
                if (found) {
                    dragOrigins.set(id, { x: found.x, y: found.y })
                    break
                }
            }
        }

        dragElementIds = dragIds
        dragStartClientX = e.clientX
        dragStartClientY = e.clientY
        dragOrigPositions = dragOrigins

        document.addEventListener('mousemove', onDocumentMouseMove, { passive: false })
        document.addEventListener('mouseup', onDocumentMouseUp)
    }

    // ── Drag to reposition ──────────────────────

    function onDocumentMouseMove(e: MouseEvent): void {
        if (dragElementIds.length === 0) return

        e.preventDefault()

        const dx = (e.clientX - dragStartClientX) / scale.value
        const dy = (e.clientY - dragStartClientY) / scale.value

        const preview: Record<string, { x: number; y: number }> = {}

        for (const id of dragElementIds) {
            const orig = dragOrigPositions.get(id)
            if (!orig) continue

            let newX = snapToGrid(orig.x + dx, snapSize.value)
            let newY = snapToGrid(orig.y + dy, snapSize.value)

            const el = findEl(id)
            if (!el) continue

            const band = getBand(id)
            if (!band) continue

            const bHeight = bandH(band)
            const contentWidth = store.page.width - store.page.margin.left - store.page.margin.right

            newX = clamp(newX, 0, contentWidth - el.width)
            newY = clamp(newY, 0, bHeight - el.height)

            preview[id] = { x: newX, y: newY }
        }

        isDragging.value = true
        dragPreviewPositions.value = preview
    }

    function cancelDrag(): void {
        dragElementIds = []
        dragOrigPositions = new Map()
        isDragging.value = false
        dragPreviewPositions.value = {}

        document.removeEventListener('mousemove', onDocumentMouseMove)
        document.removeEventListener('mouseup', onDocumentMouseUp)
    }

    function onDocumentMouseUp(): void {
        if (dragElementIds.length === 0) return

        const preview = dragPreviewPositions.value
        for (const [id, pos] of Object.entries(preview)) {
            store.moveElement(id, pos.x, pos.y)
        }

        dragElementIds = []
        dragOrigPositions = new Map()
        isDragging.value = false
        dragPreviewPositions.value = {}

        document.removeEventListener('mousemove', onDocumentMouseMove)
        document.removeEventListener('mouseup', onDocumentMouseUp)
    }

    // ── Resize handles ──────────────────────────

    function onResizeStart(e: MouseEvent, el: DesignerElement, handle: { corner: ResizeHandleCorner }): void {
        e.stopPropagation()

        store.pushHistory()

        resizeElementId = el.id
        resizeHandle = handle.corner
        resizeStartClientX = e.clientX
        resizeStartClientY = e.clientY
        resizeOrigX = el.x
        resizeOrigY = el.y
        resizeOrigW = el.width
        resizeOrigH = el.height

        document.addEventListener('mousemove', onResizeMouseMove)
        document.addEventListener('mouseup', onResizeMouseUp)
    }

    function onResizeMouseMove(e: MouseEvent): void {
        if (!resizeElementId || !resizeHandle) return

        const dx = (e.clientX - resizeStartClientX) / scale.value
        const dy = (e.clientY - resizeStartClientY) / scale.value

        let newX = resizeOrigX
        let newY = resizeOrigY
        let newW = resizeOrigW
        let newH = resizeOrigH

        const handle = resizeHandle

        if (handle.includes('e')) {
            newW = Math.max(MIN_ELEMENT_SIZE, resizeOrigW + dx)
        }
        if (handle.includes('w')) {
            newW = Math.max(MIN_ELEMENT_SIZE, resizeOrigW - dx)
            newX = resizeOrigX + (resizeOrigW - newW)
        }
        if (handle.includes('s')) {
            newH = Math.max(MIN_ELEMENT_SIZE, resizeOrigH + dy)
        }
        if (handle.includes('n')) {
            newH = Math.max(MIN_ELEMENT_SIZE, resizeOrigH - dy)
            newY = resizeOrigY + (resizeOrigH - newH)
        }

        newW = snapToGrid(newW, snapSize.value)
        newH = snapToGrid(newH, snapSize.value)
        newX = snapToGrid(newX, snapSize.value)
        newY = snapToGrid(newY, snapSize.value)

        const resizeEl = findEl(resizeElementId)
        const band = getBand(resizeElementId)

        if (resizeEl && band) {
            const bHeight = bandH(band)
            const contentWidth = store.page.width - store.page.margin.left - store.page.margin.right

            newX = clamp(newX, 0, contentWidth - newW)
            newY = clamp(newY, 0, bHeight - newH)

            if (newY + newH > bHeight) newH = bHeight - newY
            if (newX + newW > contentWidth) newW = contentWidth - newX

            newH = Math.max(MIN_ELEMENT_SIZE, newH)
            newW = Math.max(MIN_ELEMENT_SIZE, newW)

            newW = snapToGrid(newW, snapSize.value)
            newH = snapToGrid(newH, snapSize.value)
        }

        store.updateElement(resizeElementId, {
            x: newX,
            y: newY,
            width: newW,
            height: newH,
        })
    }

    function onResizeMouseUp(): void {
        resizeElementId = null
        resizeHandle = null

        document.removeEventListener('mousemove', onResizeMouseMove)
        document.removeEventListener('mouseup', onResizeMouseUp)
    }

    // ── Cleanup for component unmount ───────────

    function teardown(): void {
        cancelDrag()
        document.removeEventListener('mousemove', onResizeMouseMove)
        document.removeEventListener('mouseup', onResizeMouseUp)
    }

    return {
        isDragging,
        dragPreviewPositions,
        onElementMouseDown,
        onResizeStart,
        cancelDrag,
        teardown,
    }
}
