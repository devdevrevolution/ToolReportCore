<script setup lang="ts">
import type { HBoxNode } from '@/types/designer'
import PropertyGroup from '@/components/layout/PropertyGroup.vue'
import { usePropertyHelpers } from '@/composables/usePropertyHelpers'

const props = defineProps<{
    node: HBoxNode
}>()

const emit = defineEmits<{
    update: [id: string, patch: Partial<HBoxNode>]
}>()

const { parseDimension } = usePropertyHelpers()

function update(patch: Partial<HBoxNode>) {
    emit('update', props.node.id, patch)
}
</script>

<template>
    <PropertyGroup title="Container" collapsible>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Children</label>
            <div class="flex h-[30px] items-center rounded border border-gray-200 bg-gray-50 px-2 text-xs text-gray-600">
                {{ node.children.length }}
            </div>
        </div>
    </PropertyGroup>

    <PropertyGroup title="Dimensions" collapsible>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Width (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="node.width ?? ''"
                    min="0"
                    @change="update({ width: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Height (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="node.height ?? ''"
                    min="0"
                    @change="update({ height: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
        </div>
    </PropertyGroup>
</template>
