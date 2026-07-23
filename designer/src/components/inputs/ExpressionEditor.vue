<template>
    <div class="expression-editor">
        <label class="mb-1 block text-xs font-medium text-gray-700">Content</label>
        <div class="relative">
            <textarea
                ref="textareaRef"
                :value="modelValue"
                class="w-full rounded border border-gray-300 px-2 py-1.5 pr-8 text-xs focus:border-blue-500 focus:outline-none"
                rows="3"
                placeholder='e.g. {{ UPPER(name) }}, {{ SUM(prices) }}, {{ name | upper }}'
                spellcheck="false"
                @input="onInput"
            />
            <button
                class="absolute right-1 top-1 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-blue-600"
                title="Open function reference"
                @click="showFunctionsModal = true"
            >
                <span class="text-xs font-semibold">f(x)</span>
            </button>
        </div>

        <!-- Preview -->
        <div v-if="preview" class="mt-1 rounded border border-gray-200 bg-gray-50 px-2 py-1 font-mono text-[10px] text-gray-600">
            {{ preview }}
        </div>

        <!-- Functions Modal -->
        <FunctionsModal
            v-model="showFunctionsModal"
            @select="onFunctionSelect"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import FunctionsModal from '@/components/modals/FunctionsModal.vue'
import { evaluate } from '@/utils/ExpressionEvaluator'

const props = defineProps<{
    modelValue: string
    /** Optional data object for expression preview evaluation */
    data?: Record<string, unknown>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const textareaRef = ref<HTMLTextAreaElement | null>(null)
const showFunctionsModal = ref(false)

const preview = computed(() => {
    if (!props.data) return ''
    const expression = extractExpression(props.modelValue)
    if (!expression) return ''
    try {
        const result = evaluate(expression, props.data)
        if (result === `{{ ${expression} }}`) return ''
        return `Preview: ${result}`
    } catch {
        return ''
    }
})

function extractExpression(text: string): string {
    const match = text.match(/\{\{\s*(.+?)\s*\}\}/)
    return match ? match[1] : text
}

function onInput(event: Event): void {
    const target = event.target as HTMLTextAreaElement
    emit('update:modelValue', target.value)
}

function onFunctionSelect(functionSyntax: string): void {
    const textarea = textareaRef.value
    if (!textarea) {
        // Fallback: append at end
        emit('update:modelValue', props.modelValue + functionSyntax)
        return
    }

    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const currentText = props.modelValue
    const newText = currentText.substring(0, start) + functionSyntax + currentText.substring(end)

    emit('update:modelValue', newText)

    // Restore cursor position after the inserted text
    requestAnimationFrame(() => {
        textarea.focus()
        const cursorPos = start + functionSyntax.length
        textarea.setSelectionRange(cursorPos, cursorPos)
    })
}
</script>

<style scoped>
.expression-editor :deep(textarea) {
    font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace;
}
</style>
