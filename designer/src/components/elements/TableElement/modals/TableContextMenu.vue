<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50" @click.self="emit('close')" @keydown.escape="emit('close')">
            <div
                class="absolute rounded-lg border border-gray-200 bg-white py-1 shadow-xl"
                :style="{ left: `${x}px`, top: `${y}px` }"
                style="min-width: 180px;"
            >
                <!-- ── Column mode ────────────────── -->
                <template v-if="mode === 'column'">
                    <div class="px-3 py-1.5 text-[10px] font-medium text-gray-400 uppercase tracking-wide">
                        Column: {{ columnName }}
                    </div>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'editColumn')"
                    >
                        <span class="w-4 text-center text-[10px]">✎</span>
                        Edit column
                    </button>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'styleHeader')"
                    >
                        <span class="w-4 text-center text-[10px]">🎨</span>
                        Style header
                    </button>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'configureColumns')"
                    >
                        <span class="w-4 text-center text-[10px]">☰</span>
                        Configure all columns
                    </button>

                    <div class="my-1 border-t border-gray-100"></div>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'insertColumnLeft')"
                    >
                        <span class="w-4 text-center text-[10px]">◀</span>
                        Insert column left
                    </button>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'insertColumnRight')"
                    >
                        <span class="w-4 text-center text-[10px]">▶</span>
                        Insert column right
                    </button>

                    <div class="my-1 border-t border-gray-100"></div>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-red-600 hover:bg-red-50 disabled:text-red-300 disabled:hover:bg-transparent"
                        :disabled="!canDeleteColumn"
                        @click="emit('action', 'deleteColumn')"
                    >
                        <span class="w-4 text-center text-[10px]">✕</span>
                        Delete column
                    </button>
                </template>

                <!-- ── Cell mode ──────────────────── -->
                <template v-else>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100 disabled:text-gray-300 disabled:hover:bg-transparent"
                        :disabled="!canStyle"
                        @click="emit('action', 'style')"
                    >
                        <span class="w-4 text-center text-[10px]">🎨</span>
                        Style cell
                    </button>

                    <div class="my-1 border-t border-gray-100"></div>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100 disabled:text-gray-300 disabled:hover:bg-transparent"
                        :disabled="!canMerge"
                        @click="emit('action', 'merge')"
                    >
                        <span class="w-4 text-center text-[10px]">⊞</span>
                        Merge cells
                    </button>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100 disabled:text-gray-300 disabled:hover:bg-transparent"
                        :disabled="!canUnmerge"
                        @click="emit('action', 'unmerge')"
                    >
                        <span class="w-4 text-center text-[10px]">⊟</span>
                        Unmerge cells
                    </button>

                    <div class="my-1 border-t border-gray-100"></div>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'insertRowAbove')"
                    >
                        <span class="w-4 text-center text-[10px]">▲</span>
                        Insert row above
                    </button>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100"
                        @click="emit('action', 'insertRowBelow')"
                    >
                        <span class="w-4 text-center text-[10px]">▼</span>
                        Insert row below
                    </button>

                    <div class="my-1 border-t border-gray-100"></div>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-red-600 hover:bg-red-50 disabled:text-red-300 disabled:hover:bg-transparent"
                        :disabled="!canDeleteRow"
                        @click="emit('action', 'deleteRow')"
                    >
                        <span class="w-4 text-center text-[10px]">✕</span>
                        Delete row
                    </button>

                    <button
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[11px] text-gray-700 hover:bg-gray-100 disabled:text-gray-300 disabled:hover:bg-transparent"
                        :disabled="!canClear"
                        @click="emit('action', 'clear')"
                    >
                        <span class="w-4 text-center text-[10px]">⌫</span>
                        Clear cells
                    </button>
                </template>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
defineProps<{
    x: number
    y: number
    mode: 'cell' | 'column'
    canStyle: boolean
    canMerge: boolean
    canUnmerge: boolean
    canDeleteRow: boolean
    canClear: boolean
    canDeleteColumn: boolean
    columnName: string
}>()

const emit = defineEmits<{
    action: [action: string]
    close: []
}>()
</script>
