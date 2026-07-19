<script setup lang="ts">
import { computed } from 'vue'
import type { ShapeNode } from '@/types/designer'
import PropertyGroup from '@/components/layout/PropertyGroup.vue'

const props = defineProps<{
    node: ShapeNode
}>()

const emit = defineEmits<{
    update: [id: string, patch: Partial<ShapeNode>]
}>()

function update(patch: Partial<ShapeNode>) {
    emit('update', props.node.id, patch)
}

const shapeType = computed({
    get: () => props.node.shapeType,
    set: (newType) => {
        const patch: Partial<ShapeNode> = { shapeType: newType }
        // Sync w=h when switching to circle
        if (newType === 'circle') {
            const currentW = props.node.w ?? 40
            patch.w = currentW
            patch.h = currentW
        }
        update(patch)
    },
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
                <option value="line">Line</option>
                <option value="circle">Circle</option>
                <option value="ellipse">Ellipse</option>
            </select>
        </div>
        <!-- Circle: diameter -->
        <div v-if="props.node.shapeType === 'circle'">
            <label class="mb-1 block text-xs font-medium text-gray-700">Diameter (mm)</label>
            <input
                type="number"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                :value="props.node.w ?? 40"
                min="1"
                @change="update({ w: Number(($event.target as HTMLInputElement).value), h: Number(($event.target as HTMLInputElement).value) })"
            />
        </div>
        <!-- Rect / Ellipse: W + H -->
        <div v-else-if="props.node.shapeType !== 'line'" class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">W (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.w ?? 40"
                    min="1"
                    @change="update({ w: Number(($event.target as HTMLInputElement).value) })"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">H (mm)</label>
                <input
                    type="number"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    :value="props.node.h ?? 20"
                    min="1"
                    @change="update({ h: Number(($event.target as HTMLInputElement).value) })"
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
                    :value="props.node.strokeWidth ?? 1"
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
                @change="update({ lineStyle: ($event.target as HTMLSelectElement).value as ShapeNode['lineStyle'] })"
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
                :value="props.node.fillColor === 'none' || !props.node.fillColor ? '#ffffff' : props.node.fillColor"
                @input="update({ fillColor: ($event.target as HTMLInputElement).value })"
            />
        </div>
    </PropertyGroup>
</template>
