<script setup lang="ts">
import type { LabelNode } from '@/types/designer'
import PropertyGroup from '@/components/layout/PropertyGroup.vue'
import { usePropertyHelpers } from '@/composables/usePropertyHelpers'

const props = defineProps<{
    node: LabelNode
}>()

const emit = defineEmits<{
    update: [id: string, patch: Partial<LabelNode>]
}>()

const { parseDimension } = usePropertyHelpers()

function update(patch: Partial<LabelNode>) {
    emit('update', props.node.id, patch)
}
</script>

<template>
    <PropertyGroup title="Dimensions" collapsible>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Width (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.width"
                    min="1"
                    placeholder="auto"
                    @change="update({ width: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Height (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.height"
                    min="1"
                    placeholder="auto"
                    @change="update({ height: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
        </div>
    </PropertyGroup>

    <PropertyGroup title="Text" collapsible>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Content</label>
            <textarea
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                rows="3"
                :value="props.node.text"
                @input="update({ text: ($event.target as HTMLTextAreaElement).value })"
            />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Font size (pt)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.fontSize ?? 12"
                    min="6"
                    max="200"
                    @change="update({ fontSize: Number(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Weight</label>
                <select
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.style ?? 'normal'"
                    @change="update({ style: ($event.target as HTMLSelectElement).value })"
                >
                    <option value="normal">Normal</option>
                    <option value="bold">Bold</option>
                </select>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Color</label>
            <input
                type="color"
                class="h-[30px] w-full rounded border border-gray-300 px-1 py-0.5 focus:border-blue-500 focus:outline-none"
                :value="props.node.color ?? '#000000'"
                @input="update({ color: ($event.target as HTMLInputElement).value })"
            />
        </div>
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                :checked="props.node.wrap !== false"
                @change="update({ wrap: ($event.target as HTMLInputElement).checked })"
            />
            <label class="text-xs font-medium text-gray-700">Wrap text</label>
        </div>
    </PropertyGroup>
</template>
