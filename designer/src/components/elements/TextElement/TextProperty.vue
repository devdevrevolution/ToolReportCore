<script setup lang="ts">
import { ref } from 'vue'
import type { DesignerElement } from '@/types/designer'
import { useTextElement } from './composables/useTextElement'
import FieldSelector from '@/components/modals/FieldSelector.vue'
import ExpressionBuilder from '@/components/modals/ExpressionBuilder.vue'

const props = defineProps<{ element: DesignerElement }>()
const { content, variable, updateContent, expressionPreview } = useTextElement(props.element)

const showFieldSelector = ref(false)
const showExpressionBuilder = ref(false)

const onFieldSelected = (path: string) => {
  updateContent('variable', path)
  showFieldSelector.value = false
}

const onExpressionApply = (expression: string) => {
  updateContent('variable', expression)
  showExpressionBuilder.value = false
}
</script>

<template>
  <textarea
    class="input h-20 w-full"
    :value="content.text"
    @input="updateContent('text', ($event.target as HTMLTextAreaElement).value)"
  />
  <div>
    <label class="block text-xs text-gray-500">Expression</label>
    <div class="mt-0.5 flex gap-1">
      <input
        type="text"
        class="input flex-1 font-mono text-[11px]"
        :value="content.variable ?? ''"
        placeholder="e.g. price | currency('$')"
        @input="updateContent('variable', ($event.target as HTMLInputElement).value || undefined)"
      />
      <button
        class="flex-shrink-0 rounded border border-blue-300 bg-blue-50 px-2 text-xs text-blue-600 hover:bg-blue-100"
        title="Expression builder"
        @click="showExpressionBuilder = true"
      >
        fx
      </button>
      <button
        class="flex-shrink-0 rounded border border-gray-300 bg-gray-50 px-2 text-xs text-gray-600 hover:bg-gray-100"
        title="Select a field"
        @click="showFieldSelector = true"
      >
        ↳
      </button>
    </div>
    <!-- Mini expression preview -->
    <div v-if="variable" class="mt-1 rounded bg-gray-900 px-2 py-1 font-mono text-[10px] text-green-400">
      {{ expressionPreview(content.variable ?? '') }}
    </div>
  </div>

  <FieldSelector
    v-if="showFieldSelector"
    @select="onFieldSelected"
    @close="showFieldSelector = false"
  />

  <ExpressionBuilder
    v-if="showExpressionBuilder"
    :model-value="content.variable ?? ''"
    :current-text="content.text"
    @apply="onExpressionApply"
    @close="showExpressionBuilder = false"
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
