<script setup lang="ts">
import { computed } from 'vue'
import type { ImageNode as ImageNodeType } from '../../types/designer'
import type { CompositeParentLayout } from '../canvas/CompositeNodeRenderer.vue'
import { useCompositeScale } from '../../composables/useCompositeScale'

const props = defineProps<{
    node: ImageNodeType
    parentLayout?: CompositeParentLayout
}>()

const { mmToPxStr } = useCompositeScale()

// ── URL resolution ──

const displayUrl = computed(() => props.node.url ?? '')

const isVariable = computed(() => displayUrl.value.includes('{{'))

const variableName = computed(() => {
    const match = displayUrl.value.match(/\{\{\s*(\w+)\s*\}\}/)
    return match ? match[1] : displayUrl.value
})

const hasValidUrl = computed(() => !!displayUrl.value && !isVariable.value)

// ── Shape rendering (mirrors ShapeNode.vue) ──

const dashArray = computed(() => {
    if (props.node.lineStyle === 'dashed') return '4,2'
    if (props.node.lineStyle === 'dotted') return '1,2'
    return undefined
})

/** Map objectFit to SVG preserveAspectRatio */
const preserveAspectRatio = computed(() => {
    switch (props.node.objectFit) {
        case 'cover':  return 'xMidYMid slice'
        case 'fill':   return 'none'
        case 'none':   return 'xMidYMid meet'
        case 'contain':
        default:       return 'xMidYMid meet'
    }
})

const clipId = computed(() => `clip-${props.node.id}`)

const svgContent = computed(() => {
    const stroke = props.node.strokeColor ?? '#333333'
    const strokeWidth = props.node.strokeWidth ?? 0
    const fill = props.node.fillColor ?? 'none'
    const shapeType = props.node.shapeType ?? 'rect'
    const w = props.node.width ?? 40
    const h = props.node.height ?? 30
    const borderRadius = props.node.borderRadius ?? 0

    const commonAttrs = {
        stroke,
        fill,
        'stroke-width': String(strokeWidth),
        'stroke-dasharray': dashArray.value,
    }

    // Build the shape element for fill + stroke
    let shapeEl: { tag: string; attrs: Record<string, string | number | undefined> }

    if (shapeType === 'circle') {
        const r = Math.min(w, h) / 2
        shapeEl = {
            tag: 'circle',
            attrs: { cx: w / 2, cy: h / 2, r, ...commonAttrs },
        }
    } else if (shapeType === 'ellipse') {
        shapeEl = {
            tag: 'ellipse',
            attrs: { cx: w / 2, cy: h / 2, rx: w / 2, ry: h / 2, ...commonAttrs },
        }
    } else {
        // rect
        const rx = borderRadius > 0 ? Math.min(borderRadius, w / 2, h / 2) : undefined
        shapeEl = {
            tag: 'rect',
            attrs: { x: 0, y: 0, width: w, height: h, rx, ry: rx, ...commonAttrs },
        }
    }

    // Build clipPath element (same shape, no stroke/fill)
    let clipEl: { tag: string; attrs: Record<string, string | number | undefined> }

    if (shapeType === 'circle') {
        const r = Math.min(w, h) / 2
        clipEl = { tag: 'circle', attrs: { cx: w / 2, cy: h / 2, r } }
    } else if (shapeType === 'ellipse') {
        clipEl = { tag: 'ellipse', attrs: { cx: w / 2, cy: h / 2, rx: w / 2, ry: h / 2 } }
    } else {
        const rx = borderRadius > 0 ? Math.min(borderRadius, w / 2, h / 2) : undefined
        clipEl = { tag: 'rect', attrs: { x: 0, y: 0, width: w, height: h, rx, ry: rx } }
    }

    // Separate padding: tighter on X (left/right), more room on Y (top/bottom)
    const padX = strokeWidth * 0.15
    const padY = strokeWidth * 0.3
    return {
        viewBox: `${-padX} ${-padY} ${w + padX * 2} ${h + padY * 2}`,
        width: '100%',
        height: '100%',
        shapeEl,
        clipEl,
        w,
        h,
    }
})

// ── Wrapper sizing ──

const wrapperStyle = computed<Record<string, string>>(() => {
    const s: Record<string, string> = {}
    const w = props.node.width
    const h = props.node.height
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
    // Show background only when there's no fill (so the wrapper is visible)
    const hasFill = props.node.fillColor && props.node.fillColor !== 'none' && props.node.fillColor !== ''
    if (!hasFill) {
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

function onImageError(event: Event): void {
    ;(event.target as HTMLImageElement).style.display = 'none'
}
</script>

<template>
    <div :class="wrapperClasses" :style="wrapperStyle">
        <svg
            v-if="hasValidUrl || isVariable"
            :key="node.shapeType"
            xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink"
            :viewBox="svgContent.viewBox"
            :width="svgContent.width"
            :height="svgContent.height"
            :preserveAspectRatio="svgPreserveAspectRatio"
            class="block max-w-full max-h-full"
            style="overflow: visible"
        >
            <defs>
                <clipPath :id="clipId">
                    <component :is="svgContent.clipEl.tag" v-bind="svgContent.clipEl.attrs" />
                </clipPath>
            </defs>

            <!-- Fill + stroke shape -->
            <component :is="svgContent.shapeEl.tag" v-bind="svgContent.shapeEl.attrs" />

            <!-- Image clipped to shape -->
            <image
                v-if="hasValidUrl"
                :href="displayUrl"
                :x="0"
                :y="0"
                :width="svgContent.w"
                :height="svgContent.h"
                :preserveAspectRatio="preserveAspectRatio"
                :clip-path="`url(#${clipId})`"
                :opacity="node.opacity ?? 1"
                draggable="false"
                @error="onImageError"
            />

            <!-- Variable label (centered in shape) -->
            <text
                v-else-if="isVariable"
                :x="svgContent.w / 2"
                :y="svgContent.h / 2"
                text-anchor="middle"
                dominant-baseline="central"
                class="fill-blue-500 font-mono"
                font-size="3"
            >
                🖼 {{ variableName }}
            </text>
        </svg>

        <!-- No URL placeholder (no shape, just dashed box) -->
        <div
            v-else
            class="flex flex-col items-center justify-center gap-1 border-2 border-dashed border-gray-300 bg-gray-100"
            :style="{
                width: node.width !== undefined ? mmToPxStr(node.width) : '100%',
                height: node.height !== undefined ? mmToPxStr(node.height) : '80px',
            }"
        >
            <span class="text-2xl leading-none text-gray-400">🖼</span>
            <span class="text-[10px] font-medium text-gray-400">Image</span>
            <span v-if="node.width && node.height" class="font-mono text-[9px] text-gray-300">
                {{ node.width }}×{{ node.height }}mm
            </span>
        </div>

    </div>
</template>
