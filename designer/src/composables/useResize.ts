import { ref, onUnmounted, type Ref } from 'vue'

export type ResizeDirection = 'horizontal' | 'vertical' | 'both'

export interface UseResizeOptions {
    /** Drag axis: 'horizontal' (col-resize), 'vertical' (row-resize), 'both' (nwse-resize). Default 'both'. */
    direction?: ResizeDirection
    /** Scale factor to convert px delta → model units (e.g. px/mm). Default 1. */
    scale?: number | Ref<number>
    /** Called on each mousemove with the computed delta in model units. */
    apply: (delta: { dx: number; dy: number }) => void
    /** Called once on mousedown (e.g. pushHistory). */
    onStart?: () => void
    /** Called once on mouseup (e.g. pushHistory). */
    onEnd?: () => void
}

const CURSOR_MAP: Record<ResizeDirection, string> = {
    horizontal: 'col-resize',
    vertical: 'row-resize',
    both: 'nwse-resize',
}

/**
 * Reusable mouse-drag resize composable.
 *
 * Handles the full lifecycle: mousedown → mousemove → mouseup,
 * including cursor lock, user-select suppression, and listener cleanup.
 *
 * The consumer provides an `apply` callback that receives the computed
 * delta in model units — all clamping, corner logic, and store writes
 * happen there.
 */
export function useResize(options: UseResizeOptions) {
    const {
        direction = 'both',
        scale = 1,
        apply,
        onStart,
        onEnd,
    } = options

    const isResizing = ref(false)
    let startX = 0
    let startY = 0

    function resolveScale(): number {
        return typeof scale === 'number' ? scale : scale.value
    }

    function onMouseMove(e: MouseEvent): void {
        if (!isResizing.value) return
        const s = resolveScale() || 1
        const dx = (e.clientX - startX) / s
        const dy = (e.clientY - startY) / s
        apply({ dx, dy })
    }

    function onMouseUp(): void {
        isResizing.value = false
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
        document.body.style.cursor = ''
        document.body.style.userSelect = ''
        onEnd?.()
    }

    function onResizeStart(e: MouseEvent): void {
        isResizing.value = true
        startX = e.clientX
        startY = e.clientY
        document.addEventListener('mousemove', onMouseMove)
        document.addEventListener('mouseup', onMouseUp)
        document.body.style.cursor = CURSOR_MAP[direction]
        document.body.style.userSelect = 'none'
        onStart?.()
    }

    onUnmounted(() => {
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
    })

    return { onResizeStart, isResizing }
}
