<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/30" @mousedown="close" />

            <!-- Modal -->
            <div class="relative z-10 flex max-h-[80vh] w-[600px] flex-col rounded-lg border border-gray-200 bg-white shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Functions</span>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search functions..."
                            class="w-48 rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                        />
                        <button
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            @click="close"
                        >
                            &#x2715;
                        </button>
                    </div>
                </div>

                <!-- Category tabs -->
                <div class="flex border-b border-gray-200 px-4">
                    <button
                        v-for="cat in allCategories"
                        :key="cat"
                        class="border-b-2 px-3 py-2 text-xs font-medium transition-colors"
                        :class="activeCategory === cat
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeCategory = cat"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Function list -->
                <div class="flex-1 overflow-y-auto px-4 py-3">
                    <div v-if="filteredByCategory.length === 0" class="py-6 text-center text-xs text-gray-400">
                        No functions match your search.
                    </div>

                    <div
                        v-for="fn in filteredByCategory"
                        :key="fn.name"
                        class="mb-2 cursor-pointer rounded border border-gray-100 bg-gray-50 p-3 transition-colors hover:border-blue-200 hover:bg-blue-50"
                        @click="selectFunction(fn)"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="font-mono text-xs font-semibold text-gray-900">{{ fn.syntax }}</span>
                            </div>
                            <span
                                class="ml-2 flex-shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium"
                                :class="categoryBadgeClass(fn.category)"
                            >
                                {{ fn.category }}
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500">{{ fn.description }}</p>

                        <!-- Parameters -->
                        <div v-if="fn.parameters.length" class="mt-2 space-y-1">
                            <div
                                v-for="param in fn.parameters"
                                :key="param.name"
                                class="flex items-center gap-2 text-[10px]"
                            >
                                <span class="font-mono font-medium text-gray-700">{{ param.name }}</span>
                                <span class="text-gray-400">{{ param.type }}</span>
                                <span v-if="param.required" class="text-red-400">required</span>
                                <span v-if="param.defaultValue !== undefined" class="text-gray-400">
                                    default: {{ param.defaultValue }}
                                </span>
                            </div>
                        </div>

                        <p class="mt-1 text-[10px] text-gray-400">
                            Return: {{ fn.returnType }}
                        </p>
                    </div>
                </div>

                <!-- Footer hint -->
                <div class="border-t border-gray-200 px-4 py-2 text-[10px] text-gray-400">
                    Click a function to insert its syntax into the editor.
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import {
    FUNCTION_DEFINITIONS,
    getCategoryNames,
    type FunctionDefinition,
    type FunctionCategory,
} from '@/utils/FunctionDefinitions'

const props = defineProps<{
    modelValue: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    select: [functionSyntax: string]
}>()

const search = ref('')
const activeCategory = ref<FunctionCategory>('Math')

const allCategories = computed(() => getCategoryNames())

const filteredFunctions = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return FUNCTION_DEFINITIONS
    return FUNCTION_DEFINITIONS.filter(
        fn =>
            fn.name.toLowerCase().includes(q) ||
            fn.description.toLowerCase().includes(q) ||
            fn.syntax.toLowerCase().includes(q)
    )
})

const filteredByCategory = computed(() => {
    if (search.value.trim()) {
        // When searching, show all matches across categories
        return filteredFunctions.value
    }
    return filteredFunctions.value.filter(fn => fn.category === activeCategory.value)
})

function selectFunction(fn: FunctionDefinition): void {
    emit('select', fn.syntax)
    close()
}

function close(): void {
    emit('update:modelValue', false)
}

function categoryBadgeClass(category: FunctionCategory): string {
    const map: Record<FunctionCategory, string> = {
        Math: 'bg-blue-100 text-blue-700',
        Text: 'bg-green-100 text-green-700',
        Date: 'bg-purple-100 text-purple-700',
        Logic: 'bg-amber-100 text-amber-700',
        Formatting: 'bg-gray-100 text-gray-700',
    }
    return map[category] ?? 'bg-gray-100 text-gray-700'
}
</script>
