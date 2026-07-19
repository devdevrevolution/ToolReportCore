<template>
    <div>
        <!-- Node type header -->
        <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
            <span class="text-lg">{{ nodeIcon }}</span>
            <span class="text-sm font-medium text-gray-900">{{ node.type }}</span>
            <span class="ml-auto text-xs text-gray-400">{{ node.id.slice(0, 8) }}</span>
        </div>

        <!-- VBox properties -->
        <template v-if="node.type === 'VBox'">
            <PropertyGroup title="Container">
                <NumberInput
                    label="Padding"
                    :model-value="vbox.padding ?? 0"
                    @update:model-value="update({ padding: $event ?? 0 })"
                    :min="0"
                />
                <div class="mt-1 text-xs text-gray-400">
                    {{ vbox.children.length }} {{ vbox.children.length === 1 ? 'child' : 'children' }}
                </div>
            </PropertyGroup>
        </template>

        <!-- HBox properties -->
        <template v-else-if="node.type === 'HBox'">
            <PropertyGroup title="Container">
                <NumberInput
                    label="Width"
                    :model-value="hbox.width ?? 0"
                    @update:model-value="update({ width: $event ?? undefined })"
                    :min="0"
                />
                <div class="mt-1 text-xs text-gray-400">
                    {{ hbox.children.length }} {{ hbox.children.length === 1 ? 'child' : 'children' }}
                </div>
            </PropertyGroup>
        </template>

        <!-- Label properties -->
        <template v-else-if="node.type === 'Label'">
            <PropertyGroup title="Text">
                <TextInput
                    label="Content"
                    :model-value="label.text"
                    @update:model-value="update({ text: $event })"
                />
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <NumberInput
                        label="Width (mm)"
                        :model-value="label.width"
                        @update:model-value="update({ width: $event ?? undefined })"
                        :min="1"
                    />
                    <NumberInput
                        label="Height (mm)"
                        :model-value="label.height"
                        @update:model-value="update({ height: $event ?? undefined })"
                        :min="1"
                    />
                </div>
                <div class="mt-1 text-[11px] text-gray-400">Empty = auto.</div>
            </PropertyGroup>
            <PropertyGroup title="Typography">
                <div class="grid grid-cols-2 gap-2">
                    <NumberInput
                        label="Font Size"
                        :model-value="label.fontSize ?? 12"
                        @update:model-value="update({ fontSize: $event ?? 12 })"
                        :min="6"
                    />
                    <SelectInput
                        label="Weight"
                        :options="WEIGHT_OPTIONS"
                        :model-value="label.style ?? 'normal'"
                        @update:model-value="update({ style: $event })"
                    />
                </div>
                <div class="mt-2">
                    <ColorInput
                        label="Color"
                        :model-value="label.color ?? '#000000'"
                        @update:model-value="update({ color: $event || '#000000' })"
                    />
                </div>
            </PropertyGroup>
        </template>

        <!-- Shape properties -->
        <template v-else-if="node.type === 'Shape'">
            <PropertyGroup title="Shape">
                <SelectInput
                    label="Type"
                    :options="SHAPE_TYPE_OPTIONS"
                    :model-value="shape.shapeType"
                    @update:model-value="updateShapeType($event)"
                />
                <!-- Circle: single diameter input -->
                <div v-if="shape.shapeType === 'circle'" class="mt-2">
                    <NumberInput
                        label="Diameter"
                        :model-value="shape.w ?? 40"
                        @update:model-value="updateCircleDiameter($event)"
                        :min="1"
                    />
                </div>
                <!-- Rect / Ellipse: W + H -->
                <div v-else-if="shape.shapeType !== 'line'" class="mt-2 grid grid-cols-2 gap-2">
                    <NumberInput
                        label="Width"
                        :model-value="shape.w ?? 40"
                        @update:model-value="update({ w: $event ?? 40 })"
                        :min="1"
                    />
                    <NumberInput
                        label="Height"
                        :model-value="shape.h ?? 20"
                        @update:model-value="update({ h: $event ?? 20 })"
                        :min="1"
                    />
                </div>
                <!-- Border radius: only for rect -->
                <div v-if="shape.shapeType === 'rect'" class="mt-2">
                    <NumberInput
                        label="Border Radius"
                        :model-value="shape.borderRadius ?? 0"
                        @update:model-value="update({ borderRadius: $event ?? 0 })"
                        :min="0"
                    />
                </div>
            </PropertyGroup>
            <PropertyGroup title="Stroke">
                <ColorInput
                    label="Stroke Color"
                    :model-value="shape.strokeColor ?? '#000000'"
                    @update:model-value="update({ strokeColor: $event || '#000000' })"
                />
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <NumberInput
                        label="Stroke Width"
                        :model-value="shape.strokeWidth ?? 1"
                        @update:model-value="update({ strokeWidth: $event ?? 1 })"
                        :min="0"
                    />
                    <SelectInput
                        label="Style"
                        :options="LINE_STYLE_OPTIONS"
                        :model-value="shape.lineStyle ?? 'solid'"
                        @update:model-value="update({ lineStyle: $event as 'solid' | 'dashed' | 'dotted' })"
                    />
                </div>
            </PropertyGroup>
            <PropertyGroup title="Fill">
                <ColorInput
                    label="Fill Color"
                    :model-value="shape.fillColor ?? ''"
                    @update:model-value="update({ fillColor: $event ?? undefined })"
                    allow-clear
                />
            </PropertyGroup>
        </template>

        <!-- Table properties -->
        <template v-else-if="node.type === 'Table'">
            <PropertyGroup title="Table">
                <div class="space-y-1 text-xs text-gray-600">
                    <div>{{ table.rows.length }} rows</div>
                    <div>{{ table.columnWidths.length }} columns</div>
                </div>
            </PropertyGroup>
        </template>

        <!-- Image properties -->
        <template v-else-if="node.type === 'Image'">
            <PropertyGroup title="Shape">
                <SelectInput
                    label="Type"
                    :options="IMAGE_SHAPE_TYPE_OPTIONS"
                    :model-value="image.shapeType ?? 'rect'"
                    @update:model-value="updateImageShapeType($event)"
                />
                <!-- Circle: single diameter input -->
                <div v-if="image.shapeType === 'circle'" class="mt-2">
                    <NumberInput
                        label="Diameter"
                        :model-value="image.width ?? 40"
                        @update:model-value="updateImageCircleDiameter($event)"
                        :min="1"
                    />
                </div>
                <!-- Rect / Ellipse: W + H -->
                <div v-else class="mt-2 grid grid-cols-2 gap-2">
                    <NumberInput
                        label="Width"
                        :model-value="image.width ?? 40"
                        @update:model-value="update({ width: $event ?? 40 })"
                        :min="1"
                    />
                    <NumberInput
                        label="Height"
                        :model-value="image.height ?? 30"
                        @update:model-value="update({ height: $event ?? 30 })"
                        :min="1"
                    />
                </div>
                <!-- Border radius: only for rect -->
                <div v-if="image.shapeType === 'rect'" class="mt-2">
                    <NumberInput
                        label="Border Radius"
                        :model-value="image.borderRadius ?? 0"
                        @update:model-value="update({ borderRadius: $event ?? 0 })"
                        :min="0"
                    />
                </div>
            </PropertyGroup>
            <PropertyGroup title="Stroke">
                <ColorInput
                    label="Stroke Color"
                    :model-value="image.strokeColor ?? '#000000'"
                    @update:model-value="update({ strokeColor: $event || '#000000' })"
                />
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <NumberInput
                        label="Stroke Width"
                        :model-value="image.strokeWidth ?? 0"
                        @update:model-value="update({ strokeWidth: $event ?? 0 })"
                        :min="0"
                    />
                    <SelectInput
                        label="Style"
                        :options="LINE_STYLE_OPTIONS"
                        :model-value="image.lineStyle ?? 'solid'"
                        @update:model-value="update({ lineStyle: $event as 'solid' | 'dashed' | 'dotted' })"
                    />
                </div>
            </PropertyGroup>
            <PropertyGroup title="Fill">
                <ColorInput
                    label="Fill Color"
                    :model-value="image.fillColor ?? ''"
                    @update:model-value="update({ fillColor: $event ?? undefined })"
                    allow-clear
                />
            </PropertyGroup>
            <PropertyGroup title="Image">
                <TextInput
                    label="URL"
                    :model-value="image.url ?? ''"
                    @update:model-value="update({ url: $event || undefined })"
                />
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <SelectInput
                        label="Object Fit"
                        :options="OBJECT_FIT_OPTIONS"
                        :model-value="image.objectFit ?? 'contain'"
                        @update:model-value="update({ objectFit: $event as ImageNode['objectFit'] })"
                    />
                    <NumberInput
                        label="Opacity"
                        :model-value="image.opacity ?? 1"
                        @update:model-value="update({ opacity: $event ?? 1 })"
                        :min="0"
                        :max="1"
                        :step="0.1"
                    />
                </div>
            </PropertyGroup>
        </template>

        <!-- Margin (all node types) -->
        <PropertyGroup title="Margin">
            <div class="grid grid-cols-2 gap-2">
                <NumberInput
                    label="Top"
                    :model-value="nodeMargin.top"
                    @update:model-value="updateMargin('top', $event)"
                    :min="0"
                />
                <NumberInput
                    label="Right"
                    :model-value="nodeMargin.right"
                    @update:model-value="updateMargin('right', $event)"
                    :min="0"
                />
                <NumberInput
                    label="Bottom"
                    :model-value="nodeMargin.bottom"
                    @update:model-value="updateMargin('bottom', $event)"
                    :min="0"
                />
                <NumberInput
                    label="Left"
                    :model-value="nodeMargin.left"
                    @update:model-value="updateMargin('left', $event)"
                    :min="0"
                />
            </div>
        </PropertyGroup>

        <!-- Actions -->
        <PropertyGroup title="Actions">
            <button
                class="w-full rounded bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                @click="removeNode"
            >
                Remove
            </button>
        </PropertyGroup>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type {
    CompositeNode,
    VBoxNode,
    HBoxNode,
    LabelNode,
    ShapeNode,
    TableNode,
    ImageNode,
} from '@/types/designer'
import PropertyGroup from './PropertyGroup.vue'
import NumberInput from '../inputs/NumberInput.vue'
import TextInput from '../inputs/TextInput.vue'
import SelectInput from '../inputs/SelectInput.vue'
import ColorInput from '../inputs/ColorInput.vue'

const props = defineProps<{
    node: CompositeNode
}>()

const store = useDesignerStore()

// ── Typed accessors ────────────────────────────

const vbox = computed(() => props.node as VBoxNode)
const hbox = computed(() => props.node as HBoxNode)
const label = computed(() => props.node as LabelNode)
const shape = computed(() => props.node as ShapeNode)
const table = computed(() => props.node as TableNode)
const image = computed(() => props.node as ImageNode)

// ── Margin helpers ──────────────────────────────

const nodeMargin = computed(() => {
    const n = props.node as { margin?: { top: number; right: number; bottom: number; left: number } }
    return { top: n.margin?.top ?? 0, right: n.margin?.right ?? 0, bottom: n.margin?.bottom ?? 0, left: n.margin?.left ?? 0 }
})

function updateMargin(side: 'top' | 'right' | 'bottom' | 'left', value: number | undefined): void {
    const current = nodeMargin.value
    update({ margin: { ...current, [side]: Math.max(0, value ?? 0) } } as Partial<CompositeNode>)
}

// ── Constants ──────────────────────────────────

const WEIGHT_OPTIONS = ['normal', 'bold']
const SHAPE_TYPE_OPTIONS = ['rect', 'line', 'circle', 'ellipse']
const IMAGE_SHAPE_TYPE_OPTIONS = ['rect', 'circle', 'ellipse']
const LINE_STYLE_OPTIONS = ['solid', 'dashed', 'dotted']
const OBJECT_FIT_OPTIONS = ['contain', 'cover', 'fill', 'none']

const NODE_ICONS: Record<string, string> = {
    VBox: '▦',
    HBox: '▥',
    Label: 'T',
    Shape: '◇',
    Table: '⊞',
    Image: '🖼',
}

const nodeIcon = computed(() => NODE_ICONS[props.node.type] ?? '?')

// ── Updates ────────────────────────────────────

function update(patch: Partial<CompositeNode>): void {
    store.updateCompositeNode(props.node.id, patch)
}

function updateShapeType(newType: string): void {
    const typed = newType as 'line' | 'rect' | 'circle' | 'ellipse'
    const patch: Record<string, unknown> = { shapeType: typed }

    // When switching to circle, sync w = h (diameter)
    if (typed === 'circle') {
        const w = (shape.value as ShapeNode).w ?? 40
        patch.w = w
        patch.h = w
    }

    update(patch as Partial<CompositeNode>)
}

function updateCircleDiameter(value: number | null | undefined): void {
    const d = value ?? 40
    update({ w: d, h: d } as Partial<CompositeNode>)
}

function updateImageShapeType(newType: string): void {
    const typed = newType as 'rect' | 'circle' | 'ellipse'
    const patch: Record<string, unknown> = { shapeType: typed }

    // When switching to circle, sync width = height (diameter)
    if (typed === 'circle') {
        const w = (image.value as ImageNode).width ?? 40
        patch.width = w
        patch.height = w
    }

    update(patch as Partial<CompositeNode>)
}

function updateImageCircleDiameter(value: number | null | undefined): void {
    const d = value ?? 40
    update({ width: d, height: d } as Partial<CompositeNode>)
}

function removeNode(): void {
    store.removeCompositeNode(props.node.id)
}
</script>
