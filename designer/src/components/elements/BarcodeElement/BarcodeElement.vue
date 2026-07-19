<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'

defineProps<{ element: DesignerElement }>()
</script>

<template>
  <div class="flex h-full w-full items-center justify-center font-mono text-gray-500">
    ▌▌ Barcode
  </div>
</template>
