// ──────────────────────────────────────────────
// Toolreport Designer — useDragMove composable tests
// ──────────────────────────────────────────────

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useDragMove } from '../useDragMove'

function createMouseEvent(type: string, overrides: Partial<MouseEvent> = {}): MouseEvent {
    return new MouseEvent(type, {
        bubbles: true,
        cancelable: true,
        clientX: 0,
        clientY: 0,
        ...overrides,
    })
}

describe('useDragMove', () => {
    let mousemoveHandler: ((e: MouseEvent) => void) | null = null
    let mouseupHandler: (() => void) | null = null
    let addSpy: ReturnType<typeof vi.fn>
    let removeSpy: ReturnType<typeof vi.fn>
    let origCursor: string
    let origUserSelect: string

    beforeEach(() => {
        mousemoveHandler = null
        mouseupHandler = null
        addSpy = vi.fn((event: string, handler: EventListener) => {
            if (event === 'mousemove') mousemoveHandler = handler as (e: MouseEvent) => void
            if (event === 'mouseup') mouseupHandler = handler as () => void
        })
        removeSpy = vi.fn()
        vi.spyOn(document, 'addEventListener').mockImplementation(addSpy as unknown as typeof document.addEventListener)
        vi.spyOn(document, 'removeEventListener').mockImplementation(removeSpy as unknown as typeof document.removeEventListener)
        origCursor = document.body.style.cursor
        origUserSelect = document.body.style.userSelect
    })

    afterEach(() => {
        vi.restoreAllMocks()
        document.body.style.cursor = origCursor
        document.body.style.userSelect = origUserSelect
    })

    it('starts with isDragging = false', () => {
        const { isDragging } = useDragMove({ apply: vi.fn() })
        expect(isDragging.value).toBe(false)
    })

    it('sets isDragging to true on drag start', () => {
        const { onDragStart, isDragging } = useDragMove({ apply: vi.fn() })
        onDragStart(createMouseEvent('mousedown', { clientX: 100, clientY: 200 }), 80, 180)
        expect(isDragging.value).toBe(true)
    })

    it('calls apply with computed position on mousemove', () => {
        const apply = vi.fn()
        const { onDragStart } = useDragMove({ apply })

        onDragStart(createMouseEvent('mousedown', { clientX: 100, clientY: 200 }), 80, 180)

        expect(mousemoveHandler).toBeTruthy()
        mousemoveHandler!(createMouseEvent('mousemove', { clientX: 110, clientY: 210 }))

        // offsetX = 100 - 80 = 20, offsetY = 200 - 180 = 20
        // x = 110 - 20 = 90, y = 210 - 20 = 190
        expect(apply).toHaveBeenCalledWith({ x: 90, y: 190 })
    })

    it('calls onStart on drag start', () => {
        const onStart = vi.fn()
        const { onDragStart } = useDragMove({ apply: vi.fn(), onStart })

        onDragStart(createMouseEvent('mousedown', { clientX: 50, clientY: 50 }), 0, 0)
        expect(onStart).toHaveBeenCalledOnce()
    })

    it('sets cursor to grabbing on drag start', () => {
        const { onDragStart } = useDragMove({ apply: vi.fn() })
        onDragStart(createMouseEvent('mousedown', { clientX: 50, clientY: 50 }), 0, 0)
        expect(document.body.style.cursor).toBe('grabbing')
        expect(document.body.style.userSelect).toBe('none')
    })

    it('resets state on mouseup', () => {
        const onEnd = vi.fn()
        const { onDragStart, isDragging } = useDragMove({ apply: vi.fn(), onEnd })

        onDragStart(createMouseEvent('mousedown', { clientX: 50, clientY: 50 }), 0, 0)
        expect(mouseupHandler).toBeTruthy()

        mouseupHandler!()

        expect(isDragging.value).toBe(false)
        expect(document.body.style.cursor).toBe('')
        expect(document.body.style.userSelect).toBe('')
        expect(onEnd).toHaveBeenCalledOnce()
    })

    it('does not call apply after mouseup', () => {
        const apply = vi.fn()
        const { onDragStart } = useDragMove({ apply })

        onDragStart(createMouseEvent('mousedown', { clientX: 50, clientY: 50 }), 0, 0)

        mouseupHandler!()

        apply.mockClear()
        mousemoveHandler!(createMouseEvent('mousemove', { clientX: 100, clientY: 100 }))
        expect(apply).not.toHaveBeenCalled()
    })
})
