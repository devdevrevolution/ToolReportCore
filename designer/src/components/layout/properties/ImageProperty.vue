<script setup lang="ts">
import { computed } from 'vue'
import type { ImageNode } from '@/types/designer'
import PropertyGroup from '@/components/layout/PropertyGroup.vue'
import { usePropertyHelpers } from '@/composables/usePropertyHelpers'

interface CompositeParent {
    layout: 'VBox' | 'HBox'
    width: number
    height: number
}

const props = defineProps<{
    node: ImageNode
    parent?: CompositeParent | null
}>()

const emit = defineEmits<{
    update: [id: string, patch: Partial<ImageNode>]
}>()

const { parseDimension } = usePropertyHelpers()

function update(patch: Partial<ImageNode>) {
    emit('update', props.node.id, patch)
}

const shapeType = computed({
    get: () => props.node.shapeType ?? 'rect',
    set: (newType) => {
        const patch: Partial<ImageNode> = { shapeType: newType }
        // Sync width=height when switching to circle
        if (newType === 'circle') {
            const currentW = props.node.width ?? 40
            patch.width = currentW
            patch.height = currentW
        }
        update(patch)
    },
})

const circleDiameter = computed(() => {
    if (props.parent?.layout === 'VBox') {
        return props.node.width ?? props.parent.width
    }
    if (props.parent?.layout === 'HBox') {
        return props.node.height ?? props.parent.height
    }
    return props.node.width ?? 40
})
</script>

<template>
    <PropertyGroup title="Shape" collapsible>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Type</label>
            <select
                v-model="shapeType"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
            >
                <option value="rect">Rectangle</option>
                <option value="circle">Circle</option>
                <option value="ellipse">Ellipse</option>
            </select>
        </div>
        <!-- Circle: diameter (locked when inside a container) -->
        <div v-if="props.node.shapeType === 'circle'">
            <label class="mb-1 block text-xs font-medium" :class="props.parent ? 'text-gray-400' : 'text-gray-700'">Diameter (mm)</label>
            <input
                type="number"
                class="w-full rounded border px-2 py-1.5 text-xs focus:outline-none disabled:cursor-not-allowed"
                :class="props.parent ? 'border-gray-200 bg-gray-50 text-gray-500' : 'border-gray-300 focus:border-blue-500'"
                :value="circleDiameter"
                min="1"
                :disabled="!!props.parent"
                @change="update({ width: Number(($event.target as HTMLInputElement).value), height: Number(($event.target as HTMLInputElement).value) })"
            />
        </div>
        <!-- Rect / Ellipse: W + H (adapts to parent layout) -->
        <div v-if="props.parent?.layout === 'VBox'" class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-400">W (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-500 focus:outline-none disabled:cursor-not-allowed"
                    :value="props.node.width ?? props.parent.width"
                    min="1"
                    disabled
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">H (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.height ?? 30"
                    min="1"
                    placeholder="auto"
                    @change="update({ height: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
        </div>
        <div v-else-if="props.parent?.layout === 'HBox'" class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">W (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.width ?? 40"
                    min="1"
                    placeholder="auto"
                    @change="update({ width: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-400">H (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-500 focus:outline-none disabled:cursor-not-allowed"
                    :value="props.node.height ?? props.parent.height"
                    min="1"
                    disabled
                />
            </div>
        </div>
        <div v-else class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">W (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.width ?? 40"
                    min="1"
                    placeholder="auto"
                    @change="update({ width: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">H (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.height ?? 30"
                    min="1"
                    placeholder="auto"
                    @change="update({ height: parseDimension(($event.target as HTMLInputElement).value) })"
                />
            </div>
        </div>
        <!-- Border radius: rect only -->
        <div v-if="props.node.shapeType === 'rect'">
            <label class="mb-1 block text-xs font-medium text-gray-700">Border Radius (mm)</label>
            <input
                type="number"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                :value="props.node.borderRadius ?? 0"
                min="0"
                step="0.5"
                @change="update({ borderRadius: Number(($event.target as HTMLInputElement).value) })"
            />
        </div>
    </PropertyGroup>

    <PropertyGroup title="Stroke" collapsible :defaultOpen="false">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Width</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.strokeWidth ?? 0"
                    min="0"
                    step="0.5"
                    @change="update({ strokeWidth: Number(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Color</label>
                <input
                    type="color"
                    class="h-[30px] w-full cursor-pointer rounded border border-gray-300 px-1 py-0.5"
                    :value="props.node.strokeColor ?? '#000000'"
                    @input="update({ strokeColor: ($event.target as HTMLInputElement).value })"
                />
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Style</label>
            <select
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                :value="props.node.lineStyle ?? 'solid'"
                @change="update({ lineStyle: ($event.target as HTMLSelectElement).value as ImageNode['lineStyle'] })"
            >
                <option value="solid">Solid</option>
                <option value="dashed">Dashed</option>
                <option value="dotted">Dotted</option>
            </select>
        </div>
    </PropertyGroup>

    <PropertyGroup title="Fill" collapsible>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Fill Color</label>
            <input
                type="color"
                class="h-[30px] w-full cursor-pointer rounded border border-gray-300 px-1 py-0.5"
                :value="!props.node.fillColor || props.node.fillColor === 'none' ? '#ffffff' : props.node.fillColor"
                @input="update({ fillColor: ($event.target as HTMLInputElement).value })"
            />
        </div>
    </PropertyGroup>

    <PropertyGroup title="Image" collapsible>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">URL</label>
            <input
                type="text"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                placeholder="https://... or {{ variable }}"
                :value="props.node.url ?? ''"
                @input="update({ url: ($event.target as HTMLInputElement).value || undefined })"
            />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Object Fit</label>
            <select
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                :value="props.node.objectFit ?? 'contain'"
                @change="update({ objectFit: ($event.target as HTMLSelectElement).value as ImageNode['objectFit'] })"
            >
                <option value="contain">Contain</option>
                <option value="cover">Cover</option>
                <option value="fill">Fill</option>
                <option value="none">None</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Alt Text</label>
                <input
                    type="text"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.altText ?? ''"
                    @input="update({ altText: ($event.target as HTMLInputElement).value || undefined })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Opacity</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.opacity ?? 1"
                    min="0"
                    max="1"
                    step="0.1"
                    @change="update({ opacity: Number(($event.target as HTMLInputElement).value) })"
                />
            </div>
        </div>
    </PropertyGroup>
</template>
