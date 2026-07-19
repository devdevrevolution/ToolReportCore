<script setup lang="ts">
import { computed } from 'vue'
import type { LabelNode as LabelNodeType } from '../../types/designer'
import type { CompositeParentLayout } from '../canvas/CompositeNodeRenderer.vue'
import { useCompositeScale } from '../../composables/useCompositeScale'

const props = defineProps<{
    node: LabelNodeType
    parentLayout?: CompositeParentLayout
}>()

const { mmToPxStr, ptToPx } = useCompositeScale()

const isWrap = computed(() => props.node.wrap !== false)

const labelStyle = computed<Record<string, string>>(() => {
    const s: Record<string, string> = {
        overflow: 'hidden',
    }

    // Width: explicit mm value, or fill parent
    if (props.node.width !== undefined) {
        s.width = mmToPxStr(props.node.width)
    } else {
        s.width = '100%'
    }

    // Height: explicit mm value or auto
    if (props.node.height !== undefined) {
        s.height = mmToPxStr(props.node.height)
    }

    // Font
    if (props.node.fontSize) {
        s.fontSize = `${ptToPx(props.node.fontSize)}px`
    }
    if (props.node.fontFamily) {
        s.fontFamily = props.node.fontFamily
    }
    if (props.node.style) {
        const st = props.node.style.toUpperCase()
        if (st.includes('B')) s.fontWeight = 'bold'
        if (st.includes('I')) s.fontStyle = 'italic'
        if (st.includes('U')) s.textDecoration = 'underline'
    }
    if (props.node.color) {
        s.color = props.node.color
    }

    // Wrap / no-wrap
    if (isWrap.value) {
        s.whiteSpace = 'pre-line'
        s.overflowWrap = 'break-word'
    } else {
        s.whiteSpace = 'nowrap'
        s.textOverflow = 'ellipsis'
    }

    return s
})
</script>

<template>
    <div
        class="rounded bg-gray-50 text-xs leading-tight"
        :style="labelStyle"
    >
        {{ node.text }}
    </div>
</template>
