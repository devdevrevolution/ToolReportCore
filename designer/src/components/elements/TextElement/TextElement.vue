<script setup lang="ts">
import { computed } from 'vue'
import type { DesignerElement } from '@/types/designer'
import { useTextElement } from './composables/useTextElement'

const props = defineProps<{ element: DesignerElement; scale?: number }>()
const scaleRef = computed(() => props.scale)
const { textPreviewHtml, textPreviewStyle } = useTextElement(props.element, scaleRef)
</script>

<template>
  <div class="h-full w-full" :style="textPreviewStyle">
    <div class="w-full min-w-0 whitespace-pre-line break-words overflow-hidden" v-html="textPreviewHtml"></div>
  </div>
</template>
