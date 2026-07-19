<script setup lang="ts">
import { ref } from 'vue'
import type { DesignerElement, TableColumn } from '@/types/designer'
import { useTableElement } from './composables/useTableElement'
import FieldSelector from '@/components/modals/FieldSelector.vue'
import ExpressionBuilder from '@/components/modals/ExpressionBuilder.vue'
import TableColumnEditor from './modals/TableColumnEditor.vue'
import TableRowEditor from './modals/TableRowEditor.vue'

const props = defineProps<{ element: DesignerElement }>()
const {
  content,
  columns,
  rows,
  merges,
  hasVariable,
  hasRows,
  rowCount,
  mergeCount,
  expressionPreview,
  updateContent,
  onColumnEditorSave,
  onRowEditorSave,
} = useTableElement(props.element)

const showFieldSelector = ref(false)
const showExpressionBuilder = ref(false)
const showColumnEditor = ref(false)
const showRowEditor = ref(false)

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
  <div class="space-y-2">
    <!-- Data binding (variable) -->
    <div>
      <label class="block text-xs text-gray-500">Data source (variable)</label>
      <div class="mt-0.5 flex gap-1">
        <input
          type="text"
          class="input flex-1"
          :value="content.variable ?? ''"
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
      <div
        v-if="hasVariable"
        class="mt-1 rounded bg-gray-900 px-2 py-1 font-mono text-[10px] text-green-400"
      >
        {{ expressionPreview(content.variable ?? '') }}
      </div>
    </div>

    <!-- Columns summary + configure button -->
    <div class="space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-gray-600">Columns</span>
        <button
          class="rounded px-2 py-0.5 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-50"
          @click="showColumnEditor = true"
        >
          Configure
        </button>
      </div>

      <!-- Mini summary of configured columns -->
      <div
        v-if="columns.length === 0"
        class="rounded border border-dashed border-gray-200 py-3 text-center text-[10px] text-gray-400"
      >
        No columns configured.
      </div>

      <div
        v-for="col in columns.slice(0, 3)"
        :key="col.id"
        class="flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-2 py-1.5"
      >
        <span class="text-[10px] font-medium text-gray-700 min-w-0 flex-1 truncate">
          {{ col.header || col.key || 'Untitled' }}
        </span>
        <span class="text-[9px] text-gray-400 font-mono truncate max-w-[80px]">
          {{ col.key || '—' }}
        </span>
      </div>

      <div
        v-if="columns.length > 3"
        class="text-[10px] text-gray-400 text-center"
      >
        + {{ columns.length - 3 }} more
      </div>
    </div>

    <!-- Rows summary + edit button -->
    <div v-if="columns.length > 0" class="space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-gray-600">Rows</span>
        <button
          class="rounded px-2 py-0.5 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-50"
          @click="showRowEditor = true"
        >
          {{ hasRows ? 'Edit Rows' : 'Add Rows' }}
        </button>
      </div>

      <div
        v-if="!hasRows"
        class="rounded border border-dashed border-gray-200 py-3 text-center text-[10px] text-gray-400"
      >
        No static rows. Add data or use variable binding.
      </div>

      <div
        v-if="hasRows"
        class="flex items-center gap-2 rounded border border-gray-200 bg-green-50 px-2 py-1.5"
      >
        <span class="text-[10px] font-medium text-gray-700">
          {{ rowCount }} row{{ rowCount !== 1 ? 's' : '' }}
        </span>
        <span v-if="mergeCount > 0" class="ml-auto text-[9px] text-blue-500 font-mono">
          {{ mergeCount }} merge{{ mergeCount !== 1 ? 's' : '' }}
        </span>
      </div>
    </div>
  </div>

  <FieldSelector
    v-if="showFieldSelector"
    @select="onFieldSelected"
    @close="showFieldSelector = false"
  />

  <TableColumnEditor
    v-if="showColumnEditor"
    :columns="content.columns"
    :variable="content.variable ?? ''"
    @save="onColumnEditorSave"
    @close="showColumnEditor = false"
  />

  <TableRowEditor
    v-if="showRowEditor"
    :columns="columns"
    :rows="rows"
    :merges="merges"
    :element-styles="element.styles"
    @save="onRowEditorSave"
    @close="showRowEditor = false"
  />

  <ExpressionBuilder
    v-if="showExpressionBuilder"
    :model-value="''"
    :current-text="''"
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
