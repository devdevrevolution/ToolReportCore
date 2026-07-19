<script setup lang="ts">
import { computed, provide } from 'vue'
import type { VBoxNode as VBoxNodeType } from '../../types/designer'
import CompositeNodeRenderer from '../canvas/CompositeNodeRenderer.vue'
import type { CompositeParentLayout } from '../canvas/CompositeNodeRenderer.vue'
import { useCompositeScale } from '../../composables/useCompositeScale'

const props = defineProps<{
    node: VBoxNodeType
    parentLayout?: CompositeParentLayout
}>()

provide('compositeParentLayout', 'VBox' as const)

const { mmToPxStr } = useCompositeScale()

// Cross-axis stretch: when parent is VBox, stretch width to fill parent
const stretchClass = computed(() => props.parentLayout === 'VBox' ? 'w-full' : '')
</script>

<template>
    <div
        class="flex flex-col items-stretch overflow-hidden rounded border border-gray-200 bg-white"
        :class="stretchClass"
        :style="{
            width: node.width !== undefined ? mmToPxStr(node.width) : undefined,
            height: node.height !== undefined ? mmToPxStr(node.height) : undefined,
            gap: mmToPxStr(node.padding ?? 0),
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
