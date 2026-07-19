<template>
    <Teleport to="body">
        <div
            v-if="visible"
            class="fixed z-50 min-w-[160px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
            :style="{ left: x + 'px', top: y + 'px' }"
            @click.stop
        >
            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                :disabled="!canCopy"
                @click="emit('copy')"
            >
                <span class="w-5 text-center text-xs text-gray-400">⌘C</span>
                Copiar
            </button>
            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                :disabled="!canPaste"
                @click="emit('paste')"
            >
                <span class="w-5 text-center text-xs text-gray-400">⌘V</span>
                Pegar
            </button>
            <div class="my-1 border-t border-gray-100" />
            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                :disabled="!canDuplicate"
                @click="emit('duplicate')"
            >
                <span class="w-5 text-center text-xs text-gray-400">⌘D</span>
                Duplicar
            </button>
            <div class="my-1 border-t border-gray-100" />
            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 disabled:opacity-40"
                :disabled="!canDelete"
                @click="emit('delete')"
            >
                <span class="w-5 text-center text-xs text-gray-400">Del</span>
                Eliminar
            </button>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
defineProps<{
    visible: boolean
    x: number
    y: number
    canCopy: boolean
    canPaste: boolean
    canDuplicate: boolean
    canDelete: boolean
}>()

const emit = defineEmits<{
    copy: []
    paste: []
    duplicate: []
    delete: []
}>()
</script>
