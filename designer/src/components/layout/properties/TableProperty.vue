<script setup lang="ts">
import type { TableNode } from '@/types/designer'
import PropertyGroup from '@/components/layout/PropertyGroup.vue'

defineProps<{
    node: TableNode
}>()
</script>

<template>
    <PropertyGroup title="Table" collapsible>
        <div class="space-y-1 text-xs text-gray-500">
            <div>{{ node.rows.length }} rows</div>
            <div>{{ node.columnWidths.length }} columns</div>
        </div>
    </PropertyGroup>
</template>
