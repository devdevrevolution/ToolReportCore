<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { useBarcodeElement } from './composables/useBarcodeElement'
import SelectInput from '@/components/inputs/SelectInput.vue'
import TextInput from '@/components/inputs/TextInput.vue'

const props = defineProps<{ element: DesignerElement }>()
const { content, updateContent } = useBarcodeElement(props.element)
</script>

<template>
  <SelectInput
    label="Symbology"
    :options="['code128', 'code39', 'ean13', 'qr']"
    :model-value="content.symbology"
    @update:model-value="updateContent('symbology', $event)"
  />
  <TextInput
    label="Value"
    :model-value="content.value"
    @update:model-value="updateContent('value', $event)"
  />
  <TextInput
    label="Variable"
    :model-value="content.variable ?? ''"
    @update:model-value="updateContent('variable', $event || undefined)"
  />
  <label class="flex items-center gap-2 text-xs text-gray-500">
    <input
      type="checkbox"
      :checked="content.showLabel"
      @change="updateContent('showLabel', ($event.target as HTMLInputElement).checked)"
    />
    Show label
  </label>
</template>
