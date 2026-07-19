<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { usePageNumberElement } from './composables/usePageNumberElement'
import TextInput from '@/components/inputs/TextInput.vue'
import NumberInput from '@/components/inputs/NumberInput.vue'

const props = defineProps<{ element: DesignerElement }>()
const { content, updateContent } = usePageNumberElement(props.element)
</script>

<template>
  <TextInput
    label="Format"
    :model-value="content.format"
    @update:model-value="updateContent('format', $event)"
  />
  <NumberInput
    label="Start At"
    :model-value="content.startAt"
    @update:model-value="updateContent('startAt', $event)"
    :min="1"
  />
</template>
