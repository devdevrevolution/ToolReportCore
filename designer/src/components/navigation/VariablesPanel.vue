<template>
    <div
        data-testid="variables-panel"
        class="w-full"
    >
        <!-- Header -->
        <button
            class="flex w-full items-center justify-between px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="isOpen = !isOpen"
        >
            <span>Template Variables</span>
            <div class="flex items-center gap-2">
                <button
                    data-testid="variables-info-btn"
                    class="rounded px-1.5 py-0.5 text-[10px] text-gray-400 hover:bg-blue-100 hover:text-blue-600"
                    title="How to use variables"
                    @click.stop="showInfoModal = true"
                >
                    ℹ️
                </button>
                <span
                    class="text-xs text-gray-400 transition-transform"
                    :class="{ 'rotate-90': isOpen }"
                >
                    ▶
                </span>
            </div>
        </button>

        <!-- Content -->
        <div v-if="isOpen" class="px-4 pb-3">
            <button
                data-testid="add-template-var-btn"
                class="mb-2 flex w-full items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-xs text-gray-500 hover:border-indigo-300 hover:text-indigo-600"
                @click="openAddModal"
            >
                + Add Variable
            </button>

            <div v-if="templateVarsStore.items.length === 0" class="py-2 text-center text-xs text-gray-400">
                No template variables
            </div>

            <div
                v-for="templateVar in templateVarsStore.items"
                :key="templateVar.id"
                data-testid="template-var-item"
                class="mb-1 rounded border border-gray-100 bg-gray-50 px-2 py-1.5"
            >
                <div class="flex items-center justify-between">
                    <div class="flex-1 truncate text-xs font-medium text-gray-800 font-mono">
                        {{ templateVar.name }}
                    </div>
                    <div class="flex items-center gap-0.5">
                        <span
                            class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                            :class="templateVar.visibility === 'private'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-green-100 text-green-700'"
                        >
                            {{ templateVar.visibility === 'private' ? '🔒' : '🌐' }}
                        </span>
                        <button
                            data-testid="template-var-edit-btn"
                            class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-blue-100 hover:text-blue-700"
                            @click="openEditModal(templateVar)"
                        >
                            Edit
                        </button>
                        <button
                            data-testid="template-var-remove-btn"
                            class="rounded px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-red-100 hover:text-red-700"
                            @click="handleDelete(templateVar)"
                        >
                            ✕
                        </button>
                    </div>
                </div>
                <div v-if="templateVar.description" class="mt-0.5 text-[10px] text-gray-400 truncate">
                    {{ templateVar.description }}
                </div>
            </div>
        </div>

        <!-- Modal -->
        <TemplateVarForm
            v-if="showForm"
            :template-var="editingTemplateVar ?? undefined"
            @save="onFormSave"
            @cancel="onFormCancel"
        />

        <!-- API Usage Info Modal -->
        <Teleport to="body">
            <div
                v-if="showInfoModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showInfoModal = false"
            >
                <div class="mx-4 max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white shadow-xl">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">How to use Template Variables</h3>
                        <button
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            @click="showInfoModal = false"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="px-6 py-4">
                        <p class="mb-4 text-sm text-gray-600">
                            Send a POST request to generate a PDF with your template variables.
                            The <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">data</code> object should contain your public variable names as keys.
                        </p>

                        <!-- Curl Example -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between rounded-t-lg bg-gray-900 px-4 py-2">
                                <span class="text-xs font-medium text-gray-400">cURL</span>
                                <button
                                    class="rounded px-2 py-1 text-xs text-gray-400 hover:bg-gray-800 hover:text-white"
                                    @click="copyCurl"
                                >
                                    {{ copied ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                            <pre class="overflow-x-auto rounded-b-lg bg-gray-900 p-4 text-sm text-green-400"><code>{{ curlExample }}</code></pre>
                        </div>

                        <!-- Variable Examples -->
                        <div v-if="templateVarsStore.items.length > 0" class="mb-4">
                            <h4 class="mb-2 text-sm font-medium text-gray-700">Your Variables</h4>
                            <div class="space-y-2">
                                <div
                                    v-for="v in templateVarsStore.items"
                                    :key="v.id"
                                    class="flex items-center gap-3 rounded border border-gray-100 bg-gray-50 px-3 py-2"
                                >
                                    <code class="font-mono text-xs text-gray-800">{{ v.name }}</code>
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                        :class="v.visibility === 'private'
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-green-100 text-green-700'"
                                    >
                                        {{ v.visibility === 'private' ? '🔒 Private' : '🌐 Public' }}
                                    </span>
                                    <span v-if="v.is_required" class="text-[10px] text-orange-600">Required</span>
                                    <span v-if="v.description" class="truncate text-[10px] text-gray-400">{{ v.description }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Response Example -->
                        <div>
                            <h4 class="mb-2 text-sm font-medium text-gray-700">Response</h4>
                            <pre class="overflow-x-auto rounded-lg bg-gray-100 p-4 text-xs text-gray-700"><code>{
  "data": {
    "id": 42,
    "status": "done",
    "title": "Reporte Propiedad #67890",
    "file_size": 24500,
    "generated_at": "2026-07-16T12:00:00.000000Z"
  },
  "message": "PDF generated successfully."
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTemplateVarsStore } from '@/stores/templateVars'
import { useApi } from '@/composables/useApi'
import { useDesignerStore } from '@/stores/designer'
import type { TemplateVar } from '@/api/types'
import TemplateVarForm from '@/components/forms/TemplateVarForm.vue'

const templateVarsStore = useTemplateVarsStore()
const designerStore = useDesignerStore()

const isOpen = ref(false)
const showForm = ref(false)
const showInfoModal = ref(false)
const copied = ref(false)
const editingTemplateVar = ref<TemplateVar | null>(null)

// Build curl example from template variables
const curlExample = computed(() => {
    const templateId = designerStore.templateId ?? '{TEMPLATE_ID}'
    const baseUrl = document.querySelector('meta[name="pdf-designer-api-prefix"]')?.getAttribute('content') || '/api/pdf-designer'

    // Build data object from public variables
    const dataObj: Record<string, string> = {}
    for (const v of templateVarsStore.items) {
        if (v.visibility === 'public') {
            dataObj[v.name] = v.value || `{${v.name}}`
        }
    }

    const dataStr = Object.keys(dataObj).length > 0
        ? JSON.stringify(dataObj, null, 4)
        : '{\n    \n  }'

    return `curl -s -X POST "${baseUrl}/templates/${templateId}/generate" \\
  -H "Authorization: Bearer $TOKEN" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "title": "Mi Reporte",
    "data": ${dataStr}
  }' | jq .`
})

async function copyCurl(): Promise<void> {
    try {
        await navigator.clipboard.writeText(curlExample.value)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    } catch {
        // Fallback for older browsers
        const textarea = document.createElement('textarea')
        textarea.value = curlExample.value
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    }
}

onMounted(async () => {
    if (designerStore.templateId) {
        const api = useApi()
        await templateVarsStore.fetch(api, designerStore.templateId)
    }
})

function openAddModal(): void {
    editingTemplateVar.value = null
    showForm.value = true
}

function openEditModal(templateVar: TemplateVar): void {
    editingTemplateVar.value = templateVar
    showForm.value = true
}

async function onFormSave(payload: { name: string; value: string | null; visibility: 'public' | 'private'; is_required: boolean; description: string | null }): Promise<void> {
    const api = useApi()
    const templateId = designerStore.templateId
    if (!templateId) return

    if (editingTemplateVar.value) {
        await templateVarsStore.update(api, templateId, editingTemplateVar.value.id, payload)
    } else {
        await templateVarsStore.create(api, templateId, payload)
    }
    showForm.value = false
    editingTemplateVar.value = null
}

function onFormCancel(): void {
    showForm.value = false
    editingTemplateVar.value = null
}

async function handleDelete(templateVar: TemplateVar): Promise<void> {
    const api = useApi()
    const templateId = designerStore.templateId
    if (!templateId) return
    await templateVarsStore.remove(api, templateId, templateVar.id)
}
</script>
