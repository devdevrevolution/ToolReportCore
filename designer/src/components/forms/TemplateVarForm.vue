<template>
    <div
        data-testid="template-var-form-overlay"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
        @click.self="emit('cancel')"
    >
        <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">
                {{ props.templateVar ? 'Edit Variable' : 'Add Variable' }}
            </h3>

            <!-- Name -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Name</label>
                <input
                    data-testid="template-var-name-input"
                    type="text"
                    v-model="form.name"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    placeholder="API_KEY"
                />
                <p v-if="errors.name" class="mt-0.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <!-- Value -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Default Value (optional)</label>
                <input
                    data-testid="template-var-value-input"
                    type="text"
                    v-model="form.value"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    placeholder="Enter default value or leave empty"
                />
            </div>

            <!-- Visibility -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Visibility</label>
                <select
                    data-testid="template-var-visibility-select"
                    v-model="form.visibility"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                >
                    <option value="public">Public (client-sends)</option>
                    <option value="private">Private (server-only)</option>
                </select>
                <p class="mt-0.5 text-[10px] text-gray-400">
                    {{ form.visibility === 'private'
                        ? 'Private variables are never exposed to the client. Use for API tokens, secrets, etc.'
                        : 'Public variables can be sent by the client during PDF generation.' }}
                </p>
            </div>

            <!-- Required (only for public) -->
            <div v-if="form.visibility === 'public'" class="mb-3 flex items-center gap-2">
                <input
                    data-testid="template-var-required-checkbox"
                    type="checkbox"
                    v-model="form.is_required"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <label class="text-xs text-gray-500">Required</label>
            </div>

            <!-- Description -->
            <div class="mb-4">
                <label class="mb-1 block text-xs text-gray-500">Description</label>
                <textarea
                    data-testid="template-var-description-input"
                    v-model="form.description"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    rows="2"
                    placeholder="Optional description"
                ></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2">
                <button
                    data-testid="template-var-cancel-btn"
                    class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    @click="emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    data-testid="template-var-save-btn"
                    class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="!isValid"
                    @click="onSave"
                >
                    {{ props.templateVar ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, computed } from 'vue'
import type { TemplateVar } from '@/api/types'

const props = defineProps<{
    templateVar?: TemplateVar
}>()

const emit = defineEmits<{
    (e: 'save', payload: {
        name: string
        value: string | null
        visibility: 'public' | 'private'
        is_required: boolean
        description: string | null
    }): void
    (e: 'cancel'): void
}>()

const form = reactive({
    name: props.templateVar?.name ?? '',
    value: props.templateVar?.value ?? '',
    visibility: (props.templateVar?.visibility ?? 'public') as 'public' | 'private',
    is_required: props.templateVar?.is_required ?? false,
    description: props.templateVar?.description ?? '',
})

const errors = reactive({
    name: '',
})

const isValid = computed(() => {
    return form.name.trim() !== '' && /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(form.name.trim())
})

function onSave(): void {
    errors.name = ''

    if (!form.name.trim()) {
        errors.name = 'Name is required'
        return
    }

    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(form.name.trim())) {
        errors.name = 'Name must be a valid identifier (letters, numbers, underscores)'
        return
    }

    emit('save', {
        name: form.name.trim(),
        value: form.value || null,
        visibility: form.visibility,
        is_required: form.visibility === 'public' ? form.is_required : false,
        description: form.description || null,
    })
}
</script>
