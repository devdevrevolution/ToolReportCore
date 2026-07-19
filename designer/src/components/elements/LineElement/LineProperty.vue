<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { useLineElement } from './composables/useLineElement'
import SelectInput from '@/components/inputs/SelectInput.vue'
import NumberInput from '@/components/inputs/NumberInput.vue'

const props = defineProps<{ element: DesignerElement }>()
const { content, updateContent } = useLineElement(props.element)
</script>

<template>
  <SelectInput
    label="Orientation"
    :options="['horizontal', 'vertical']"
    :model-value="content.orientation"
    @update:model-value="updateContent('orientation', $event)"
  />
  <NumberInput
    label="Line Width"
    :model-value="content.lineWidth"
    @update:model-value="updateContent('lineWidth', $event)"
  />
  <SelectInput
    label="Style"
    :options="['solid', 'dashed', 'dotted']"
    :model-value="content.lineStyle"
    @update:model-value="updateContent('lineStyle', $event)"
  />
</template>
