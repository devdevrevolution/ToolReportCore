<script setup lang="ts">
import { computed } from 'vue'
import type { ShapeNode as ShapeNodeType } from '../../types/designer'
import type { CompositeParentLayout } from '../canvas/CompositeNodeRenderer.vue'
import { useCompositeScale } from '../../composables/useCompositeScale'

const props = defineProps<{
    node: ShapeNodeType
    parentLayout?: CompositeParentLayout
}>()

const { mmToPxStr } = useCompositeScale()

const dashArray = computed(() => {
    if (props.node.lineStyle === 'dashed') return '4,2'
    if (props.node.lineStyle === 'dotted') return '1,2'
    return undefined
})

const svgAttrs = computed(() => {
    const stroke = props.node.strokeColor ?? '#333333'
    const strokeWidth = props.node.strokeWidth ?? 1
    const fill = props.node.fillColor ?? 'none'

    // ── Line ──
    if (props.node.shapeType === 'line') {
        const x1 = props.node.x1 ?? 0
        const y1 = props.node.y1 ?? 0
        const x2 = props.node.x2 ?? 60
        const y2 = props.node.y2 ?? 0
        const minX = Math.min(x1, x2) - strokeWidth
        const minY = Math.min(y1, y2) - strokeWidth
        const w = Math.abs(x2 - x1) + strokeWidth * 2
        const h = Math.abs(y2 - y1) + strokeWidth * 2

        return {
            viewBox: `${minX} ${minY} ${w} ${h}`,
            width: '100%',
            height: '100%',
            elements: [
                {
                    tag: 'line',
                    attrs: {
                        x1, y1, x2, y2,
                        stroke,
                        'stroke-width': String(strokeWidth),
                        'stroke-dasharray': dashArray.value,
                    },
                },
            ],
        }
    }

    // ── Rect / Circle / Ellipse ──
    const w = props.node.w ?? 60
    const h = props.node.h ?? 30
    const borderRadius = props.node.borderRadius ?? 0

    const commonAttrs = {
        stroke,
        fill,
        'stroke-width': String(strokeWidth),
        'stroke-dasharray': dashArray.value,
    }

    let element: { tag: string; attrs: Record<string, string | number | undefined> }

    if (props.node.shapeType === 'circle') {
        const r = Math.min(w, h) / 2
        element = {
            tag: 'circle',
            attrs: {
                cx: w / 2,
                cy: h / 2,
                r,
                ...commonAttrs,
            },
        }
    } else if (props.node.shapeType === 'ellipse') {
        element = {
            tag: 'ellipse',
            attrs: {
                cx: w / 2,
                cy: h / 2,
                rx: w / 2,
                ry: h / 2,
                ...commonAttrs,
            },
        }
    } else {
        // rect (default)
        const rx = borderRadius > 0 ? Math.min(borderRadius, w / 2, h / 2) : undefined
        element = {
            tag: 'rect',
            attrs: {
                x: 0,
                y: 0,
                width: w,
                height: h,
                rx,
                ry: rx,
                ...commonAttrs,
            },
        }
    }

    // Separate padding: tighter on X (left/right), more room on Y (top/bottom)
    const padX = strokeWidth * 0.15
    const padY = strokeWidth * 0.3
    return {
        viewBox: `${-padX} ${-padY} ${w + padX * 2} ${h + padY * 2}`,
        width: '100%',
        height: '100%',
        elements: [element],
    }
})

const wrapperStyle = computed<Record<string, string>>(() => {
    const s: Record<string, string> = {}
    const w = props.node.w
    const h = props.node.h
    const isCircle = props.node.shapeType === 'circle'

    if (props.parentLayout === 'VBox') {
        if (isCircle) {
            // Circle: use height for both dimensions, center horizontally
            const size = h ?? w ?? 30
            s.height = mmToPxStr(size)
            s.width = mmToPxStr(size)
            s.margin = '0 auto'
        } else {
            // VBox: stretch full width, explicit height (user-controlled)
            s.width = '100%'
            if (h !== undefined) s.height = mmToPxStr(h)
        }
    } else if (props.parentLayout === 'HBox') {
        if (isCircle) {
            // Circle: use width for both dimensions, center vertically
            const size = w ?? h ?? 30
            s.width = mmToPxStr(size)
            s.height = mmToPxStr(size)
            s.margin = 'auto 0'
        } else {
            // HBox: stretch full height, explicit width (user-controlled)
            s.height = '100%'
            if (w !== undefined) s.width = mmToPxStr(w)
        }
    } else {
        // No layout container: use explicit dimensions
        if (w !== undefined) s.width = mmToPxStr(w)
        if (h !== undefined) s.height = mmToPxStr(h)
    }

    return s
})

/** SVG scaling inside VBox/HBox: cover the allocated space without stretching. */
const svgPreserveAspectRatio = computed(() =>
    props.parentLayout ? 'xMidYMid slice' : undefined,
)

const wrapperClasses = computed(() => {
    // Use flex (not inline-flex) inside VBox/HBox so the wrapper stretches properly
    // overflow-visible so stroke isn't clipped by wrapper boundary
    const base = props.parentLayout
        ? ['relative', 'flex', 'items-center', 'justify-center', 'overflow-visible']
        : ['relative', 'inline-flex', 'items-center', 'justify-center', 'overflow-visible']
    // Show background only when shape has no fill (so the wrapper is visible)
    if (props.node.fillColor === undefined || props.node.fillColor === 'none' || props.node.fillColor === '') {
        base.push('bg-gray-50', 'rounded')
    }
    // For circles in VBox/HBox, don't add w-full/h-full — size is set inline
    const isCircle = props.node.shapeType === 'circle'
    if (!isCircle) {
        if (props.parentLayout === 'HBox') {
            base.push('h-full')
        } else if (props.parentLayout === 'VBox') {
            base.push('w-full')
        }
    }
    return base
})
</script>

<template>
    <div :class="wrapperClasses" :style="wrapperStyle">
        <svg
            :key="props.node.shapeType"
            xmlns="http://www.w3.org/2000/svg"
            :viewBox="svgAttrs.viewBox"
            :width="svgAttrs.width"
            :height="svgAttrs.height"
            :preserveAspectRatio="svgPreserveAspectRatio"
            class="block max-w-full max-h-full"
            style="overflow: visible"
        >
            <template v-for="(el, idx) in svgAttrs.elements" :key="idx">
                <component :is="el.tag" v-bind="el.attrs" />
            </template>
        </svg>
    </div>
</template>
