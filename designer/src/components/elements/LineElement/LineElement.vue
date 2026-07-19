<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { useLineElement } from './composables/useLineElement'

const props = defineProps<{ element: DesignerElement }>()
const { orientation } = useLineElement(props.element)
</script>

<template>
  <div class="flex h-full w-full items-center justify-center text-gray-400">
    {{ orientation === 'horizontal' ? '━━━' : '┃' }}
  </div>
</template>
