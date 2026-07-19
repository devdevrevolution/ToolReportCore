<template>
    <div data-testid="properties-panel" class="properties-panel w-[280px] flex-shrink-0 overflow-y-auto border-l border-gray-200 bg-white">
        <!-- Placeholder: no selection -->
        <div v-if="store.selectedElementIds.length === 0" class="p-6 text-center text-sm text-gray-400">
            Select an element to edit
        </div>

        <!-- Multi-selection: batch actions -->
        <div v-else-if="store.selectedElementIds.length > 1" class="p-4">
            <div class="mb-4 text-center">
                <span class="text-lg font-semibold text-gray-800">{{ store.selectedElementIds.length }} elements selected</span>
            </div>

            <PropertyGroup title="Batch Actions">
                <div class="space-y-2">
                    <button
                        class="w-full rounded bg-red-50 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-100"
                        @click="batchDelete"
                    >
                        Delete all
                    </button>
                    <button
                        class="w-full rounded bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100"
                        @click="batchToggleLock"
                    >
                        Toggle lock / unlock
                    </button>
                    <button
                        class="w-full rounded bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100"
                        @click="batchToggleVisible"
                    >
                        Toggle visible / hidden
                    </button>
                </div>
            </PropertyGroup>
        </div>

        <!-- Properties: single element selected -->
        <template v-else>
            <!-- Element type header -->
            <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                <span class="text-lg">{{ ELEMENT_ICONS[el.type] }}</span>
                <span class="text-sm font-medium text-gray-900">{{ capitalize(el.type) }}</span>
                <span class="ml-auto text-xs text-gray-400">{{ el.id.slice(0, 8) }}</span>
            </div>

            <!-- Position -->
            <PropertyGroup title="Position">
                <div class="grid grid-cols-2 gap-2">
                    <NumberInput label="X" v-model="posX" />
                    <NumberInput label="Y" v-model="posY" />
                    <NumberInput label="W" v-model="posW" :min="5" />
                    <NumberInput label="H" v-model="posH" :min="5" />
                    <NumberInput label="Rot" v-model="posRot" :step="5" />
                </div>
            </PropertyGroup>

            <!-- Position Mode (only for children of containers) -->
            <PropertyGroup v-if="childContainerInfo" title="Position Mode">
                <div class="flex gap-2">
                    <button
                        class="flex-1 rounded border px-3 py-1.5 text-xs"
                        :class="el.positionMode === 'fill'
                            ? 'border-gray-200 bg-gray-100 text-gray-500'
                            : 'border-blue-200 bg-blue-50 text-blue-700'"
                        @click="updatePositionMode('absolute')"
                    >
                        Absolute
                    </button>
                    <button
                        class="flex-1 rounded border px-3 py-1.5 text-xs"
                        :class="el.positionMode === 'fill'
                            ? 'border-purple-200 bg-purple-50 text-purple-700'
                            : 'border-gray-200 bg-gray-100 text-gray-500'"
                        @click="updatePositionMode('fill')"
                    >
                        Fill
                    </button>
                </div>
            </PropertyGroup>

            <!-- Typography (text only) -->
            <PropertyGroup v-if="el.type === 'text'" title="Typography">
                <div class="space-y-2">
                    <SelectInput label="Family" :options="FONT_FAMILIES" v-model="styleFontFamily" />
                    <div class="grid grid-cols-2 gap-2">
                        <NumberInput label="Size" v-model="styleFontSize" />
                        <SelectInput label="Weight" :options="WEIGHT_OPTIONS" v-model="styleFontWeight" />
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="rounded border px-3 py-1 text-xs"
                            :class="{ 'bg-blue-100 text-blue-700': el.styles.fontStyle === 'italic' }"
                            @click="updateStyle('fontStyle', el.styles.fontStyle === 'italic' ? 'normal' : 'italic')"
                        >
                            I
                        </button>
                        <ColorInput label="Color" v-model="styleColor" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <SelectInput label="H-Align" :options="ALIGN_OPTIONS" v-model="styleTextAlign" />
                        <SelectInput label="V-Align" :options="V_ALIGN_OPTIONS" v-model="styleVerticalAlign" />
                    </div>
                    <NumberInput label="Line Height" v-model="styleLineHeight" :step="0.1" />
                </div>
            </PropertyGroup>

            <!-- Appearance -->
            <PropertyGroup title="Appearance">
                <div class="space-y-2">
                    <ColorInput label="Background" v-model="styleBackgroundColor" />
                    <div class="grid grid-cols-3 gap-2">
                        <NumberInput label="Border W" v-model="borderWidth" />
                        <ColorInput label="Color" v-model="borderColor" />
                        <SelectInput label="Style" :options="BORDER_STYLES" v-model="borderStyle" />
                    </div>
                    <NumberInput label="Radius" v-model="styleBorderRadius" />
                </div>
            </PropertyGroup>

            <!-- Padding -->
            <PropertyGroup title="Padding">
                <div class="grid grid-cols-2 gap-2">
                    <NumberInput label="Top" v-model="paddingTop" />
                    <NumberInput label="Right" v-model="paddingRight" />
                    <NumberInput label="Bottom" v-model="paddingBottom" />
                    <NumberInput label="Left" v-model="paddingLeft" />
                </div>
            </PropertyGroup>

            <!-- Content (varies by type) -->
            <PropertyGroup title="Content">
                <ElementProperty :element="el" />
            </PropertyGroup>

            <!-- Layer controls -->
            <PropertyGroup title="Layer">
                <div class="flex gap-2">
                    <button
                        class="flex-1 rounded bg-red-50 px-3 py-1.5 text-xs text-red-700 hover:bg-red-100"
                        @click="store.removeElement(el.id)"
                    >
                        Delete
                    </button>
                    <button
                        class="flex-1 rounded bg-gray-50 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        @click="store.duplicateElement(el.id)"
                    >
                        Duplicate
                    </button>
                </div>
                <div class="mt-2 flex gap-2">
                    <button
                        class="flex-1 rounded border px-3 py-1.5 text-xs"
                        :class="el.visible ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-100'"
                        @click="store.updateElement(el.id, { visible: !el.visible })"
                    >
                        {{ el.visible ? '\uD83D\uDC41 Visible' : '\uD83D\uDC41 Hidden' }}
                    </button>
                    <button
                        class="flex-1 rounded border px-3 py-1.5 text-xs"
                        :class="el.locked ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-100'"
                        @click="store.updateElement(el.id, { locked: !el.locked })"
                    >
                        {{ el.locked ? '\uD83D\uDD12 Locked' : '\uD83D\uDD13 Unlocked' }}
                    </button>
                </div>
            </PropertyGroup>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDesignerStore, isDesignerChild } from '@/stores/designer'
import type {
    DesignerElement,
    ContainerContent,
} from '@/types/designer'

import PropertyGroup from './PropertyGroup.vue'
import NumberInput from '../inputs/NumberInput.vue'
import SelectInput from '../inputs/SelectInput.vue'
import ColorInput from '../inputs/ColorInput.vue'
import ElementProperty from '../elements/ElementProperty.vue'

// ── Store ──────────────────────────────────────

const store = useDesignerStore()
const el = computed(() => store.selectedElement!)

/**
 * If the selected element is a child of a container, returns the
 * container id and child id so we can update positionMode.
 */
const childContainerInfo = computed<{ containerId: string; childId: string } | null>(() => {
    const selected = store.selectedElement
    if (!selected || !store.page.bands) return null
    for (const band of store.page.bands) {
        for (const candidate of (band.children ?? []).filter(isDesignerChild)) {
            if (candidate.type === 'container') {
                const content = candidate.content as ContainerContent
                if (content.children.some(c => c.id === selected.id)) {
                    return { containerId: candidate.id, childId: selected.id }
                }
            }
        }
    }
    return null
})

function updatePositionMode(mode: 'absolute' | 'fill'): void {
    if (!childContainerInfo.value) return
    store.updateChildElement(
        childContainerInfo.value.containerId,
        childContainerInfo.value.childId,
        { positionMode: mode } as Partial<DesignerElement>,
    )
}

// ── Constants ──────────────────────────────────

const FONT_FAMILIES = ['Helvetica', 'Times New Roman', 'Courier New', 'Arial', 'Georgia', 'Verdana']
const WEIGHT_OPTIONS = ['normal', 'bold']
const ALIGN_OPTIONS = ['left', 'center', 'right', 'justify']
const V_ALIGN_OPTIONS = ['top', 'bottom']
const BORDER_STYLES = ['solid', 'dashed', 'dotted']

const ELEMENT_ICONS: Record<string, string> = {
    text: 'T',
    image: '\uD83D\uDDBC',
    table: '\u229E',
    line: '\u2501',
    rectangle: '\u25A0',
    barcode: '\u258C\u258C',
    page_number: '\u00B6',
    container: '\u25AD',
}

// ── Helpers ────────────────────────────────────

function capitalize(str: string): string {
    return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ')
}

// ── Two-way bindings via computed get/set ──────
// Each computed reads from the store and writes back on change,
// enabling v-model on the input components.

function updateField(field: string, value: number): void {
    if (!store.selectedElement) return
    store.updateElement(store.selectedElement.id, { [field]: value })
}

function updateStyle(field: string, value: unknown): void {
    if (!store.selectedElement) return
    store.updateElement(store.selectedElement.id, {
        styles: { ...store.selectedElement.styles, [field]: value },
    })
}

function setBorder(field: string, value: unknown): void {
    if (!store.selectedElement) return
    const currentBorder = store.selectedElement.styles.border ?? {
        width: 0,
        color: '#000000',
        style: 'solid',
    }
    store.updateElement(store.selectedElement.id, {
        styles: {
            ...store.selectedElement.styles,
            border: { ...currentBorder, [field]: value },
        },
    })
}

function updatePadding(field: string, value: number): void {
    if (!store.selectedElement) return
    store.updateElement(store.selectedElement.id, {
        styles: {
            ...store.selectedElement.styles,
            padding: { ...store.selectedElement.styles.padding, [field]: value },
        },
    })
}

// ── Position (number) ──────────────────────────

const posX = computed<number | undefined>({
    get: () => Math.round(el.value.x),
    set: v => { if (v !== undefined) updateField('x', v) },
})
const posY = computed<number | undefined>({
    get: () => Math.round(el.value.y),
    set: v => { if (v !== undefined) updateField('y', v) },
})
const posW = computed<number | undefined>({
    get: () => Math.round(el.value.width),
    set: v => { if (v !== undefined) updateField('width', v) },
})
const posH = computed<number | undefined>({
    get: () => Math.round(el.value.height),
    set: v => { if (v !== undefined) updateField('height', v) },
})
const posRot = computed<number | undefined>({
    get: () => el.value.rotation,
    set: v => { if (v !== undefined) updateField('rotation', v) },
})

// ── Typography styles ──────────────────────────

const styleFontFamily = computed<string>({
    get: () => el.value.styles.fontFamily,
    set: v => updateStyle('fontFamily', v),
})
const styleFontSize = computed<number | undefined>({
    get: () => el.value.styles.fontSize,
    set: v => { if (v !== undefined) updateStyle('fontSize', v) },
})
const styleFontWeight = computed<string>({
    get: () => el.value.styles.fontWeight,
    set: v => updateStyle('fontWeight', v),
})
const styleColor = computed<string>({
    get: () => el.value.styles.color,
    set: v => updateStyle('color', v),
})
const styleTextAlign = computed<string>({
    get: () => el.value.styles.textAlign,
    set: v => updateStyle('textAlign', v),
})
const styleVerticalAlign = computed<string>({
    get: () => el.value.styles.verticalAlign,
    set: v => updateStyle('verticalAlign', v),
})
const styleLineHeight = computed<number | undefined>({
    get: () => el.value.styles.lineHeight,
    set: v => { if (v !== undefined) updateStyle('lineHeight', v) },
})

// ── Appearance ─────────────────────────────────

const styleBackgroundColor = computed<string>({
    get: () => el.value.styles.backgroundColor ?? '',
    set: v => updateStyle('backgroundColor', v || null),
})
const borderWidth = computed<number | undefined>({
    get: () => el.value.styles.border?.width ?? 0,
    set: v => { if (v !== undefined) setBorder('width', v) },
})
const borderColor = computed<string>({
    get: () => el.value.styles.border?.color ?? '#000000',
    set: v => setBorder('color', v),
})
const borderStyle = computed<string>({
    get: () => el.value.styles.border?.style ?? 'solid',
    set: v => setBorder('style', v),
})
const styleBorderRadius = computed<number | undefined>({
    get: () => el.value.styles.borderRadius,
    set: v => { if (v !== undefined) updateStyle('borderRadius', v) },
})

// ── Padding ────────────────────────────────────

const paddingTop = computed<number | undefined>({
    get: () => el.value.styles.padding?.top ?? 0,
    set: v => { if (v !== undefined) updatePadding('top', v) },
})
const paddingRight = computed<number | undefined>({
    get: () => el.value.styles.padding?.right ?? 0,
    set: v => { if (v !== undefined) updatePadding('right', v) },
})
const paddingBottom = computed<number | undefined>({
    get: () => el.value.styles.padding?.bottom ?? 0,
    set: v => { if (v !== undefined) updatePadding('bottom', v) },
})
const paddingLeft = computed<number | undefined>({
    get: () => el.value.styles.padding?.left ?? 0,
    set: v => { if (v !== undefined) updatePadding('left', v) },
})

// ── Batch actions (multi-select) ──────────────

function batchDelete(): void {
    const ids = store.selectedElementIds
    if (ids.length > 0) {
        store.removeElements(ids)
    }
}

function batchToggleLock(): void {
    const ids = store.selectedElementIds
    const bands = store.page.bands ?? []
    // Determine current state: lock all if ANY is unlocked
    const anyUnlocked = ids.some(id => {
        for (const band of bands) {
            const designerChildren = (band.children ?? []).filter(isDesignerChild)
            const el = designerChildren.find(e => e.id === id)
            if (el && !el.locked) return true
        }
        return false
    })
    const newLocked = anyUnlocked
    for (const id of ids) {
        store.updateElement(id, { locked: newLocked } as any)
    }
}

function batchToggleVisible(): void {
    const ids = store.selectedElementIds
    const bands = store.page.bands ?? []
    // Determine current state: hide all if ANY is visible
    const anyVisible = ids.some(id => {
        for (const band of bands) {
            const designerChildren = (band.children ?? []).filter(isDesignerChild)
            const el = designerChildren.find(e => e.id === id)
            if (el && el.visible) return true
        }
        return false
    })
    const newVisible = !anyVisible
    for (const id of ids) {
        store.updateElement(id, { visible: newVisible } as any)
    }
}
</script>

<style scoped>
.input {
    display: block;
    width: 100%;
    border-radius: 0.25rem;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1rem;
}
.input:focus {
    border-color: #3b82f6;
    outline: 1px solid #3b82f6;
    outline-offset: -1px;
}
</style>