import { ref, onUnmounted } from 'vue'

export interface UseDragMoveOptions {
    /** Called on each mousemove with the new position in px. */
    apply: (position: { x: number; y: number }) => void
    /** Called once on mousedown. */
    onStart?: () => void
    /** Called once on mouseup. */
    onEnd?: () => void
}

/**
 * Reusable mouse-drag move composable.
 *
 * Handles the full lifecycle: mousedown → mousemove → mouseup,
 * including cursor lock, user-select suppression, and listener cleanup.
 *
 * The consumer provides an `apply` callback that receives the computed
 * position — all clamping and store writes happen there.
 */
export function useDragMove(options: UseDragMoveOptions) {
    const { apply, onStart, onEnd } = options

    const isDragging = ref(false)
    let startX = 0
    let startY = 0
    let offsetX = 0
    let offsetY = 0

    function onMouseMove(e: MouseEvent): void {
        if (!isDragging.value) return
        const x = e.clientX - offsetX
        const y = e.clientY - offsetY
        apply({ x, y })
    }

    function onMouseUp(): void {
        isDragging.value = false
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
        document.body.style.cursor = ''
        document.body.style.userSelect = ''
        onEnd?.()
    }

    /**
     * Start dragging. Call with the mousedown event and the current
     * element position (top-left corner in viewport coords).
     */
    function onDragStart(e: MouseEvent, elementX: number, elementY: number): void {
        isDragging.value = true
        startX = e.clientX
        startY = e.clientY
        offsetX = e.clientX - elementX
        offsetY = e.clientY - elementY
        document.addEventListener('mousemove', onMouseMove)
        document.addEventListener('mouseup', onMouseUp)
        document.body.style.cursor = 'grabbing'
        document.body.style.userSelect = 'none'
        onStart?.()
    }

    onUnmounted(() => {
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
    })

    return { onDragStart, isDragging }
}
