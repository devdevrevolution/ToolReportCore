<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { useImageElement } from './composables/useImageElement'

const props = defineProps<{ element: DesignerElement }>()
const {
  imageUrl,
  variable,
  hasValidUrl,
  imagePreviewContainerStyle,
  imagePreviewImgStyle,
  onImageError,
} = useImageElement(props.element)
</script>

<template>
  <div
    class="flex h-full w-full items-center justify-center bg-gray-50 text-xs text-gray-400"
    :style="imagePreviewContainerStyle"
  >
    <img
      v-if="hasValidUrl"
      :src="imageUrl"
      class="max-h-full max-w-full object-contain"
      :style="imagePreviewImgStyle"
      alt=""
      draggable="false"
      @error="onImageError"
    />
    <span
      v-else-if="variable"
      class="truncate px-1 font-mono text-blue-500"
    >
      🖼 {{ variable }}
    </span>
    <span v-else class="text-2xl">🖼</span>
  </div>
</template>
