<template>
    <div class="border-b border-gray-100">
        <button
            v-if="collapsible"
            type="button"
            class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-gray-50"
            @click="isOpen = !isOpen"
        >
            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {{ title }}
            </h4>
            <span
                class="text-[10px] text-gray-400 transition-transform duration-150"
                :class="{ 'rotate-90': isOpen }"
            >▶</span>
        </button>
        <h4
            v-else
            class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500"
        >
            {{ title }}
        </h4>
        <div v-show="isOpen || !collapsible" class="px-4 pb-3 space-y-2">
            <slot />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = withDefaults(defineProps<{
    title: string
    collapsible?: boolean
    defaultOpen?: boolean
}>(), {
    collapsible: false,
    defaultOpen: true,
})

const isOpen = ref(props.defaultOpen)
</script>
