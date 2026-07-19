<script setup lang="ts">
import { computed, provide } from 'vue'
import type { HBoxNode as HBoxNodeType } from '../../types/designer'
import CompositeNodeRenderer from '../canvas/CompositeNodeRenderer.vue'
import type { CompositeParentLayout } from '../canvas/CompositeNodeRenderer.vue'
import { useCompositeScale } from '../../composables/useCompositeScale'

const props = defineProps<{
    node: HBoxNodeType
    parentLayout?: CompositeParentLayout
}>()

provide('compositeParentLayout', 'HBox' as const)

const { mmToPxStr } = useCompositeScale()

// Cross-axis stretch: HBox parent → stretch height only
const stretchClass = computed(() => {
    if (props.parentLayout === 'HBox') return 'h-full'
    return ''
})
</script>

<template>
    <div
        class="flex flex-row items-stretch overflow-hidden rounded border border-gray-200 bg-white"
        :class="stretchClass"
        :style="{
            width: node.width !== undefined ? mmToPxStr(node.width) : undefined,
            height: node.height !== undefined ? mmToPxStr(node.height) : undefined,
        }"
    >
        <div
            v-for="(child, idx) in node.children"
            :key="idx"
            class="flex-shrink-0"
        >
            <CompositeNodeRenderer :node="child" />
        </div>
    </div>
</template>
