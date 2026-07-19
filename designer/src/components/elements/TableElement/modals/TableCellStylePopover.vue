<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50" @click.self="emit('close')" @keydown.escape="emit('close')">
            <div
                class="absolute rounded-lg border border-gray-200 bg-white p-4 shadow-xl"
                style="width: 280px; top: 50%; left: 50%; transform: translate(-50%, -50%);"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-gray-800">{{ title }}</h4>
                    <button
                        class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        @click="emit('close')"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <!-- Text -->
                    <div>
                        <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Text</label>
                        <input
                            type="text"
                            class="w-full rounded border border-gray-200 px-2 py-1 text-[11px] outline-none focus:border-blue-400"
                            :value="local.text"
                            @input="update('text', ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <!-- Font Family -->
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Font</label>
                            <select
                                class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                :value="local.fontFamily ?? ''"
                                @change="update('fontFamily', ($event.target as HTMLSelectElement).value || undefined)"
                            >
                                <option value="">Inherit</option>
                                <option value="Helvetica">Helvetica</option>
                                <option value="Times">Times</option>
                                <option value="Courier">Courier</option>
                            </select>
                        </div>

                        <!-- Font Size -->
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Size (pt)</label>
                            <input
                                type="number"
                                min="6"
                                max="72"
                                class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                :value="local.fontSize ?? ''"
                                @input="update('fontSize', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : undefined)"
                            />
                        </div>
                    </div>

                    <!-- Weight + Style -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Weight</label>
                            <select
                                class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                :value="local.fontWeight ?? ''"
                                @change="update('fontWeight', ($event.target as HTMLSelectElement).value || undefined)"
                            >
                                <option value="">Inherit</option>
                                <option value="normal">Normal</option>
                                <option value="bold">Bold</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Style</label>
                            <select
                                class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                :value="local.fontStyle ?? ''"
                                @change="update('fontStyle', ($event.target as HTMLSelectElement).value || undefined)"
                            >
                                <option value="">Inherit</option>
                                <option value="normal">Normal</option>
                                <option value="italic">Italic</option>
                            </select>
                        </div>
                    </div>

                    <!-- Color + Background -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Color</label>
                            <input
                                type="color"
                                class="h-8 w-full cursor-pointer rounded border border-gray-200 p-0.5 outline-none focus:border-blue-400"
                                :value="local.color ?? '#000000'"
                                @input="update('color', ($event.target as HTMLInputElement).value || undefined)"
                            />
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Background</label>
                            <input
                                type="color"
                                class="h-8 w-full cursor-pointer rounded border border-gray-200 p-0.5 outline-none focus:border-blue-400"
                                :value="local.backgroundColor ?? '#ffffff'"
                                @input="update('backgroundColor', ($event.target as HTMLInputElement).value || undefined)"
                            />
                        </div>
                    </div>

                    <!-- Text Align + Vertical Align -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Align</label>
                            <select
                                class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                :value="local.textAlign ?? ''"
                                @change="update('textAlign', ($event.target as HTMLSelectElement).value || undefined)"
                            >
                                <option value="">Inherit</option>
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] font-medium text-gray-500">V. Align</label>
                            <select
                                class="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] outline-none focus:border-blue-400"
                                :value="local.verticalAlign ?? ''"
                                @change="update('verticalAlign', ($event.target as HTMLSelectElement).value || undefined)"
                            >
                                <option value="">Inherit</option>
                                <option value="top">Top</option>
                                <option value="middle">Middle</option>
                                <option value="bottom">Bottom</option>
                            </select>
                        </div>
                    </div>

                    <!-- Wrap / No wrap -->
                    <div class="flex items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                :checked="local.nowrap === true"
                                @change="update('nowrap', ($event.target as HTMLInputElement).checked || undefined)"
                            />
                            <span class="text-[10px] font-medium text-gray-500">No wrap</span>
                        </label>
                        <span class="text-[9px] text-gray-400">truncate with …</span>
                    </div>

                    <!-- Padding -->
                    <div>
                        <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Padding</label>
                        <div class="grid grid-cols-4 gap-1">
                            <div>
                                <label class="block text-[9px] text-gray-400">T</label>
                                <input
                                    type="number"
                                    min="0"
                                    class="w-full rounded border border-gray-200 px-1 py-0.5 text-[10px] outline-none focus:border-blue-400"
                                    :value="local.padding?.top ?? ''"
                                    @input="updatePadding('top', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : undefined)"
                                />
                            </div>
                            <div>
                                <label class="block text-[9px] text-gray-400">R</label>
                                <input
                                    type="number"
                                    min="0"
                                    class="w-full rounded border border-gray-200 px-1 py-0.5 text-[10px] outline-none focus:border-blue-400"
                                    :value="local.padding?.right ?? ''"
                                    @input="updatePadding('right', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : undefined)"
                                />
                            </div>
                            <div>
                                <label class="block text-[9px] text-gray-400">B</label>
                                <input
                                    type="number"
                                    min="0"
                                    class="w-full rounded border border-gray-200 px-1 py-0.5 text-[10px] outline-none focus:border-blue-400"
                                    :value="local.padding?.bottom ?? ''"
                                    @input="updatePadding('bottom', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : undefined)"
                                />
                            </div>
                            <div>
                                <label class="block text-[9px] text-gray-400">L</label>
                                <input
                                    type="number"
                                    min="0"
                                    class="w-full rounded border border-gray-200 px-1 py-0.5 text-[10px] outline-none focus:border-blue-400"
                                    :value="local.padding?.left ?? ''"
                                    @input="updatePadding('left', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : undefined)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 flex justify-between">
                    <button
                        class="rounded text-[10px] text-gray-400 hover:text-gray-600"
                        @click="reset"
                    >
                        Reset to default
                    </button>
                    <div class="flex gap-1">
                        <button
                            class="rounded border border-gray-300 px-2.5 py-1 text-[10px] text-gray-600 hover:bg-gray-50"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <button
                            class="rounded bg-gray-900 px-2.5 py-1 text-[10px] text-white transition-colors hover:bg-gray-800"
                            @click="save"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import type { DesignerStyles, TableCellContent } from '@/types/designer'

const props = defineProps<{
    cell: TableCellContent
    elementStyles: DesignerStyles
    title?: string
}>()

const emit = defineEmits<{
    save: [cell: TableCellContent]
    close: []
}>()

const local = reactive<TableCellContent>({
    text: props.cell.text ?? '',
    fontFamily: props.cell.fontFamily,
    fontSize: props.cell.fontSize,
    fontWeight: props.cell.fontWeight,
    fontStyle: props.cell.fontStyle,
    color: props.cell.color,
    textAlign: props.cell.textAlign,
    backgroundColor: props.cell.backgroundColor,
    verticalAlign: props.cell.verticalAlign,
    nowrap: props.cell.nowrap,
    padding: props.cell.padding ? { ...props.cell.padding } : undefined,
})

function update(field: string, value: unknown): void {
    ;(local as Record<string, unknown>)[field] = value
}

function updatePadding(side: 'top' | 'right' | 'bottom' | 'left', value: number | undefined): void {
    if (!local.padding) {
        local.padding = { top: 0, right: 0, bottom: 0, left: 0 }
    }
    local.padding[side] = value ?? 0
}

function reset(): void {
    local.fontFamily = undefined
    local.fontSize = undefined
    local.fontWeight = undefined
    local.fontStyle = undefined
    local.color = undefined
    local.textAlign = undefined
    local.backgroundColor = undefined
    local.verticalAlign = undefined
    local.nowrap = undefined
    local.padding = undefined
}

function save(): void {
    const result: TableCellContent = { text: local.text }
    if (local.fontFamily !== undefined) result.fontFamily = local.fontFamily
    if (local.fontSize !== undefined) result.fontSize = local.fontSize
    if (local.fontWeight !== undefined) result.fontWeight = local.fontWeight
    if (local.fontStyle !== undefined) result.fontStyle = local.fontStyle
    if (local.color !== undefined) result.color = local.color
    if (local.textAlign !== undefined) result.textAlign = local.textAlign
    if (local.backgroundColor !== undefined) result.backgroundColor = local.backgroundColor
    if (local.verticalAlign !== undefined) result.verticalAlign = local.verticalAlign
    if (local.nowrap !== undefined) result.nowrap = local.nowrap
    if (local.padding !== undefined) {
        const p = local.padding
        if (p.top || p.right || p.bottom || p.left) {
            result.padding = { ...p }
        }
    }
    emit('save', result)
}
</script>
