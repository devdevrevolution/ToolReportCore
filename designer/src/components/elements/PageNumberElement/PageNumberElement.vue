<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'

defineProps<{ element: DesignerElement }>()
</script>

<template>
  <div class="flex h-full w-full items-center justify-center text-gray-500">
    📄 Page #
  </div>
</template>
