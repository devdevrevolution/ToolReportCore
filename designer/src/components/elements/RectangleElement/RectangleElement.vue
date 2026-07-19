<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { useRectangleElement } from './composables/useRectangleElement'

const props = defineProps<{ element: DesignerElement }>()
const { hasColorVariable, colorVariable } = useRectangleElement(props.element)
</script>

<template>
  <div class="h-full w-full">
    <div
      v-if="hasColorVariable"
      class="flex h-full w-full items-center justify-center text-[9px] text-gray-400"
    >
      🎨 {{ colorVariable }}
    </div>
  </div>
</template>
