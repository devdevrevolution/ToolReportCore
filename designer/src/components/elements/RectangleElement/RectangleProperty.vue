<script setup lang="ts">
import { ref } from 'vue'
import type { DesignerElement } from '@/types/designer'
import { useRectangleElement } from './composables/useRectangleElement'
import FieldSelector from '@/components/modals/FieldSelector.vue'

const props = defineProps<{ element: DesignerElement }>()
const { content, updateContent } = useRectangleElement(props.element)

const showFieldSelector = ref(false)

const onFieldSelected = (path: string) => {
  updateContent('colorVariable', path)
  showFieldSelector.value = false
}
</script>

<template>
  <div>
    <label class="block text-xs text-gray-500">Color Variable</label>
    <div class="mt-0.5 flex gap-1">
      <input
        type="text"
        class="input flex-1 font-mono text-[11px]"
        :value="content.colorVariable ?? ''"
        placeholder="e.g. status.color"
        @input="updateContent('colorVariable', ($event.target as HTMLInputElement).value || undefined)"
      />
      <button
        class="flex-shrink-0 rounded border border-gray-300 bg-gray-50 px-2 text-xs text-gray-600 hover:bg-gray-100"
        title="Select a field"
        @click="showFieldSelector = true"
      >
        ↳
      </button>
    </div>
  </div>

  <FieldSelector
    v-if="showFieldSelector"
    @select="onFieldSelected"
    @close="showFieldSelector = false"
  />
</template>

<style scoped>
.input {
  display: block;
  width: 100%;
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  line-height: 1rem;
}
.input:focus {
  border-color: #3b82f6;
  outline: 1px solid #3b82f6;
  outline-offset: -1px;
}
</style>
