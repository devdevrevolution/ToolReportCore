<script setup lang="ts">
import { ref, computed } from 'vue'
import type { DesignerElement, ElementType } from '@/types/designer'
import { useDesignerStore } from '@/stores/designer'
import { useContainerElement } from './composables/useContainerElement'

const props = defineProps<{ element: DesignerElement }>()
const store = useDesignerStore()
const { children, isEmpty, addChild, removeChild } = useContainerElement(props.element)

const ADD_CHILD_TYPES: { type: ElementType; label: string }[] = [
  { type: 'text', label: 'Text' },
  { type: 'image', label: 'Image' },
  { type: 'table', label: 'Table' },
  { type: 'line', label: 'Line' },
  { type: 'rectangle', label: 'Rectangle' },
  { type: 'barcode', label: 'Barcode' },
]

const TYPE_LABELS: Record<string, string> = {
  text: 'Text',
  image: 'Image',
  table: 'Table',
  line: 'Line',
  rectangle: 'Rectangle',
  barcode: 'Barcode',
  page_number: 'Page Number',
  container: 'Container',
}

const TYPE_ICONS: Record<string, string> = {
  text: 'T',
  image: '\uD83D\uDDBC',
  table: '\u229E',
  line: '\u2501',
  rectangle: '\u25A0',
  barcode: '\u258C\u258C',
  page_number: '\u00B6',
  container: '\u25AD',
}

const selectedType = ref<ElementType>('text')

function onAddChild(): void {
  addChild(selectedType.value)
}

function onRemoveChild(childId: string): void {
  removeChild(childId)
}

function onSelectChild(childId: string): void {
  store.selectElement(childId)
}

function togglePositionMode(childId: string, currentMode: string | undefined): void {
  const newMode = currentMode === 'fill' ? 'absolute' : 'fill'
  store.updateChildElement(props.element.id, childId, { positionMode: newMode } as Partial<DesignerElement>)
}
</script>

<template>
  <div class="space-y-3">
    <!-- Children section header -->
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-gray-700">
        Children
        <span class="ml-1 text-gray-400">({{ children.length }})</span>
      </span>
    </div>

    <!-- Empty state -->
    <div v-if="isEmpty" class="text-center text-[10px] text-gray-400">
      No children
    </div>

    <!-- Child list -->
    <div v-for="child in children" :key="child.id" class="space-y-1">
      <div
        class="flex cursor-pointer items-center gap-2 rounded border border-gray-200 px-2 py-1.5 text-xs hover:bg-gray-50"
        :class="{ 'ring-1 ring-blue-400': store.isSelected(child.id) }"
        @click="onSelectChild(child.id)"
      >
        <span class="text-sm">{{ TYPE_ICONS[child.type] ?? '?' }}</span>
        <span class="flex-1 truncate text-gray-700">{{ TYPE_LABELS[child.type] ?? child.type }}</span>

        <!-- Position mode badge -->
        <button
          class="rounded px-1.5 py-0.5 text-[10px] font-medium"
          :class="child.positionMode === 'fill'
            ? 'bg-purple-100 text-purple-700'
            : 'bg-gray-100 text-gray-500'"
          :title="child.positionMode === 'fill' ? 'Fill mode — click to switch to Absolute' : 'Absolute mode — click to switch to Fill'"
          @click.stop="togglePositionMode(child.id, child.positionMode)"
        >
          {{ child.positionMode === 'fill' ? 'Fill' : 'Absolute' }}
        </button>

        <!-- Remove button -->
        <button
          class="rounded px-1 text-gray-400 hover:bg-red-100 hover:text-red-600"
          title="Remove child"
          @click.stop="onRemoveChild(child.id)"
        >
          ✕
        </button>
      </div>
    </div>

    <!-- Add child controls -->
    <div class="flex gap-1">
      <select
        v-model="selectedType"
        class="flex-1 rounded border border-gray-300 px-2 py-1 text-[11px] focus:border-blue-500 focus:outline-none"
      >
        <option v-for="opt in ADD_CHILD_TYPES" :key="opt.type" :value="opt.type">
          {{ opt.label }}
        </option>
      </select>
      <button
        class="rounded border border-gray-300 bg-gray-50 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-100"
        @click="onAddChild"
      >
        + Add
      </button>
    </div>
  </div>
</template>
