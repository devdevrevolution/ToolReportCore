<template>
    <div
        data-testid="datasource-form-overlay"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
        @click.self="emit('cancel')"
    >
        <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">
                {{ props.datasource ? 'Edit Datasource' : 'Add Datasource' }}
            </h3>

            <!-- Name -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Name</label>
                <input
                    data-testid="ds-name-input"
                    type="text"
                    :value="form.name"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    placeholder="My API"
                    @input="onNameInput"
                />
                <p v-if="errors.name" class="mt-0.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <!-- URL -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">URL</label>
                <input
                    data-testid="ds-url-input"
                    type="text"
                    :value="form.url"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    placeholder="https://api.example.com/data"
                    @input="onUrlInput"
                />
                <p v-if="errors.url" class="mt-0.5 text-xs text-red-500">{{ errors.url }}</p>
            </div>

            <!-- Method -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Method</label>
                <select
                    data-testid="ds-method-select"
                    :value="form.method"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    @change="onMethodChange"
                >
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                </select>
            </div>

            <!-- Headers -->
            <div class="mb-3">
                <div class="mb-1 flex items-center justify-between">
                    <label class="text-xs text-gray-500">Headers</label>
                    <button
                        data-testid="ds-add-header-btn"
                        type="button"
                        class="text-xs text-blue-600 hover:text-blue-800"
                        @click="addHeader"
                    >
                        + Add
                    </button>
                </div>

                <div
                    v-for="(headerKey, index) in headerKeys"
                    :key="index"
                    data-testid="ds-header-row"
                    class="mb-1 flex items-center gap-1"
                >
                    <input
                        type="text"
                        :value="headerKey"
                        class="w-2/5 rounded border border-gray-300 px-2 py-1 text-xs font-mono focus:border-blue-500 focus:outline-none"
                        placeholder="Key"
                        @input="onHeaderKeyInput(index, ($event.target as HTMLInputElement).value)"
                    />
                    <input
                        type="text"
                        :value="form.headers[headerKey] ?? ''"
                        class="flex-1 rounded border border-gray-300 px-2 py-1 text-xs font-mono focus:border-blue-500 focus:outline-none"
                        placeholder="Value"
                        @input="onHeaderValueInput(index, headerKey, ($event.target as HTMLInputElement).value)"
                    />
                    <button
                        data-testid="ds-remove-header-btn"
                        type="button"
                        class="text-gray-400 hover:text-red-500"
                        @click="removeHeader(index)"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Auth -->
            <div class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Authentication</label>
                <select
                    data-testid="ds-auth-select"
                    :value="form.authType"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    @change="onAuthTypeChange"
                >
                    <option value="none">None</option>
                    <option value="bearer">Bearer Token</option>
                </select>
            </div>

            <div v-if="form.authType === 'bearer'" class="mb-3">
                <label class="mb-1 block text-xs text-gray-500">Token</label>
                <input
                    data-testid="ds-auth-token-input"
                    type="text"
                    :value="form.authToken"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    placeholder="Bearer token..."
                    @input="onAuthTokenInput"
                />
            </div>

            <!-- Timeout -->
            <div class="mb-4">
                <label class="mb-1 block text-xs text-gray-500">Timeout (ms)</label>
                <input
                    data-testid="ds-timeout-input"
                    type="number"
                    :value="form.timeout"
                    min="1000"
                    max="60000"
                    step="500"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    @input="onTimeoutInput"
                />
                <p v-if="errors.timeout" class="mt-0.5 text-xs text-red-500">{{ errors.timeout }}</p>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end gap-2">
                <button
                    data-testid="ds-cancel-btn"
                    type="button"
                    class="rounded border border-gray-300 px-4 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                    @click="emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    data-testid="ds-save-btn"
                    type="button"
                    class="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                    @click="submit"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import type { DatasourceConfig } from '@/types/designer'

const props = defineProps<{
    datasource?: DatasourceConfig
}>()

const emit = defineEmits<{
    (e: 'save', config: Omit<DatasourceConfig, 'id' | 'lastError'>): void
    (e: 'cancel'): void
}>()

// ── Form state ─────────────────────────────────

const form = reactive({
    name: props.datasource?.name ?? '',
    url: props.datasource?.url ?? '',
    method: props.datasource?.method ?? 'GET' as 'GET' | 'POST',
    headers: props.datasource?.headers ?? {} as Record<string, string>,
    authType: props.datasource?.auth?.type ?? 'none' as 'none' | 'bearer',
    authToken: props.datasource?.auth?.token ?? '',
    timeout: props.datasource?.timeout ?? 5000,
})

const errors = reactive<Record<string, string>>({})
const headerKeys = ref<string[]>(
    props.datasource && Object.keys(props.datasource.headers ?? {}).length > 0
        ? Object.keys(props.datasource.headers)
        : [''],
)

// ── Input handlers ─────────────────────────────

function onNameInput(e: Event): void {
    form.name = (e.target as HTMLInputElement).value
    delete errors.name
}

function onUrlInput(e: Event): void {
    form.url = (e.target as HTMLInputElement).value
    delete errors.url
}

function onMethodChange(e: Event): void {
    form.method = (e.target as HTMLSelectElement).value as 'GET' | 'POST'
}

function onAuthTypeChange(e: Event): void {
    form.authType = (e.target as HTMLSelectElement).value as 'none' | 'bearer'
}

function onAuthTokenInput(e: Event): void {
    form.authToken = (e.target as HTMLInputElement).value
}

function onTimeoutInput(e: Event): void {
    form.timeout = Number((e.target as HTMLInputElement).value)
    delete errors.timeout
}

function onHeaderKeyInput(index: number, newKey: string): void {
    const oldKey = headerKeys.value[index]
    if (oldKey && oldKey !== newKey) {
        // Transfer the value to the new key and remove the old one
        form.headers[newKey] = form.headers[oldKey] ?? ''
        delete form.headers[oldKey]
    }
    headerKeys.value[index] = newKey
}

function onHeaderValueInput(_index: number, key: string, value: string): void {
    if (key) {
        form.headers[key] = value
    }
}

// ── Header management ──────────────────────────

function addHeader(): void {
    headerKeys.value.push('')
}

function removeHeader(index: number): void {
    const key = headerKeys.value[index]
    if (key) {
        delete form.headers[key]
    }
    headerKeys.value.splice(index, 1)
}

// ── Validation ─────────────────────────────────

function validate(): boolean {
    Object.keys(errors).forEach(k => delete errors[k])

    if (!form.name.trim()) errors.name = 'Name is required'
    if (!form.url.trim()) errors.url = 'URL is required'
    else if (!/^https?:\/\/.+/.test(form.url)) errors.url = 'Must be a valid HTTP(S) URL'
    if (form.timeout < 1000 || form.timeout > 60000) errors.timeout = 'Timeout must be 1000-60000ms'

    return Object.keys(errors).length === 0
}

// ── Submit ─────────────────────────────────────

function submit(): void {
    if (!validate()) return

    const headers: Record<string, string> = {}
    for (const key of headerKeys.value) {
        if (key.trim()) headers[key.trim()] = form.headers[key] ?? ''
    }

    emit('save', {
        name: form.name.trim(),
        url: form.url.trim(),
        method: form.method,
        headers,
        auth: form.authType === 'none' ? { type: 'none' } : { type: 'bearer', token: form.authToken },
        timeout: form.timeout,
    })
}
</script>
