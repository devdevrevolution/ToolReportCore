<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-start justify-center pt-16">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/30" @mousedown="$emit('close')" />

            <!-- Panel -->
            <div class="relative z-10 flex max-h-[80vh] w-[580px] flex-col rounded-lg border border-gray-200 bg-white shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Expression Builder</span>
                    <div class="flex items-center gap-3">
                        <!-- Mode toggle -->
                        <div class="flex rounded-md border border-gray-300 text-xs">
                            <button
                                class="px-2.5 py-1"
                                :class="mode === 'visual' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                @click="mode = 'visual'; syncFromRaw()"
                            >
                                Visual
                            </button>
                            <button
                                class="px-2.5 py-1"
                                :class="mode === 'raw' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                @click="mode = 'raw'; syncFromVisual()"
                            >
                                Manual
                            </button>
                        </div>
                        <button
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            @click="$emit('close')"
                        >
                            <span class="text-xs">&#x2715;</span>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
                    <!-- ═══ VISUAL MODE ═══ -->
                    <template v-if="mode === 'visual'">
                        <!-- Prefix (literal before variable) -->
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Prefix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input
                                v-model="prefixText"
                                type="text"
                                class="input"
                                placeholder='e.g. "Total: ", "Richiesta:\n"'
                                @input="onVisualChange"
                            />
                        </div>

                        <!-- Variable -->
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Variable</label>
                            <div class="flex gap-1">
                                <input
                                    v-model="variableKey"
                                    type="text"
                                    class="input flex-1"
                                    placeholder="e.g. price, client.name, [].total"
                                    @input="onVisualChange"
                                />
                                <button
                                    class="flex-shrink-0 rounded border border-gray-300 bg-gray-50 px-2 text-xs text-gray-600 hover:bg-gray-100"
                                    title="Select a field from datasources"
                                    @click="showFieldPicker = true"
                                >
                                    &#x21B3;
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Filters -->
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700">Filters</label>
                                <button
                                    class="text-xs text-blue-600 hover:text-blue-800"
                                    @click="addFilter"
                                >
                                    + Add filter
                                </button>
                            </div>

                            <div v-if="filters.length === 0" class="rounded border border-dashed border-gray-300 py-3 text-center text-xs text-gray-400">
                                No filters applied. Click "Add filter" or select one below.
                            </div>

                            <!-- Active filters -->
                            <div v-for="(filter, index) in filters" :key="index" class="mb-2 rounded border border-gray-200 bg-gray-50 p-2">
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="filter.name"
                                        class="input flex-1 py-1"
                                        @change="onFilterNameChange(index)"
                                    >
                                        <option value="" disabled>Select filter...</option>
                                        <optgroup v-for="(group, category) in filtersByCategory" :key="category" :label="categoryLabel(category)">
                                            <option v-for="def in group" :key="def.name" :value="def.name">
                                                {{ def.label }}
                                            </option>
                                        </optgroup>
                                    </select>

                                    <button
                                        class="flex-shrink-0 rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600"
                                        @click="removeFilter(index)"
                                    >
                                        &#x2715;
                                    </button>
                                </div>

                                <!-- Filter params -->
                                <div v-if="getFilterDef(filter.name)?.params.length" class="mt-2 space-y-1">
                                    <div
                                        v-for="(paramDef, pIdx) in getFilterDef(filter.name)!.params"
                                        :key="pIdx"
                                        class="flex items-center gap-2"
                                    >
                                        <label class="w-24 flex-shrink-0 text-[10px] text-gray-500">{{ paramDef.name }}</label>
                                        <select
                                            v-if="paramDef.type === 'select'"
                                            v-model="filter.params[pIdx]"
                                            class="input flex-1 py-0.5 text-xs"
                                        >
                                            <option v-for="opt in paramDef.options" :key="opt.value" :value="opt.value">
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                        <input
                                            v-else-if="paramDef.type === 'number'"
                                            v-model.number="filter.params[pIdx]"
                                            type="number"
                                            class="input flex-1 py-0.5 text-xs"
                                            @input="onVisualChange"
                                        />
                                        <input
                                            v-else-if="paramDef.type === 'boolean'"
                                            v-model="filter.params[pIdx]"
                                            type="checkbox"
                                            class="h-3.5 w-3.5 rounded border-gray-300"
                                            @change="onVisualChange"
                                        />
                                        <input
                                            v-else
                                            v-model="filter.params[pIdx]"
                                            type="text"
                                            class="input flex-1 py-0.5 text-xs"
                                            :placeholder="String(paramDef.defaultValue)"
                                            @input="onVisualChange"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Quick add filter chips -->
                            <div v-if="filters.length === 0" class="mt-2 flex flex-wrap gap-1">
                                <button
                                    v-for="def in popularFilters"
                                    :key="def.name"
                                    class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] text-gray-600 hover:border-blue-300 hover:bg-blue-50"
                                    @click="addFilterByName(def.name)"
                                >
                                    {{ def.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Suffix (literal after variable) -->
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Suffix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input
                                v-model="suffixText"
                                type="text"
                                class="input"
                                placeholder='e.g. " USD", "!"'
                                @input="onVisualChange"
                            />
                        </div>
                    </template>

                    <!-- ═══ RAW/MANUAL MODE ═══ -->
                    <template v-else>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">
                                Expression
                            </label>
                            <p class="mb-2 text-[10px] text-gray-400">
                                Use <span class="font-mono font-medium">+</span> to concatenate, <span class="font-mono font-medium">|</span> for filters, <span class="font-mono font-medium">'quotes'</span> for literals.
                                <br />Example: <span class="font-mono text-gray-600">'Total: ' + price | currency("$")</span>
                            </p>
                            <textarea
                                v-model="rawExpression"
                                class="input h-28 font-mono text-xs"
                                placeholder="price | currency(&quot;$&quot;, 2) | upper"
                                spellcheck="false"
                                @input="onRawChange"
                            />
                            <p class="mt-1 text-[10px] text-gray-400">
                                Do not include the <span class="font-mono">{{ }}</span> delimiters — they are added automatically.
                            </p>
                        </div>

                        <!-- Available filters reference -->
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Available Filters</label>
                            <div class="grid grid-cols-2 gap-1 text-[10px]">
                                <div v-for="def in FILTER_DEFINITIONS" :key="def.name" class="rounded border border-gray-100 bg-gray-50 px-2 py-1">
                                    <span class="font-mono font-medium text-gray-800">{{ def.name }}</span>
                                    <span v-if="def.params.length" class="text-gray-400">({{ def.params.map(p => p.name).join(', ') }})</span>
                                    <div class="text-gray-400">{{ def.description }}</div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══ SHARED: Preview ═══ -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Expression Preview</label>
                        <div class="rounded border border-gray-200 bg-gray-900 px-3 py-2 font-mono text-sm text-green-400">
                            {{ previewExpression }}
                        </div>
                        <p class="mt-1 text-[10px] text-gray-400">
                            This is the expression that will be inserted into the text element.
                        </p>
                    </div>

                    <!-- Full text preview -->
                    <div v-if="currentText">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Text Preview</label>
                        <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 break-all">
                            {{ textPreview }}
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-2 border-t border-gray-200 px-4 py-3">
                    <button
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                        @click="$emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                        :disabled="!hasValidExpression"
                        @click="applyExpression"
                    >
                        Apply
                    </button>
                </div>
            </div>
        </div>

        <!-- Nested field selector -->
        <FieldSelector
            v-if="showFieldPicker"
            @select="onFieldSelected"
            @close="showFieldPicker = false"
        />
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { FILTER_DEFINITIONS, getFilterDefinition, type FilterDefinition } from '@/utils/filterDefinitions'
import { buildExpression, parseExpression, type ParsedFilter, type ParsedTerm } from '@/utils/expressionParser'
import FieldSelector from './FieldSelector.vue'

interface ActiveFilter {
    name: string
    params: unknown[]
}

const props = defineProps<{
    /** Current expression or variable path to edit (without {{ }}) */
    modelValue?: string
    /** Current full text of the element (for text preview) */
    currentText?: string
}>()

const emit = defineEmits<{
    /** Emits the final expression string (without {{ }}) */
    apply: [expression: string]
    close: []
}>()

// ── State ──────────────────────────────────────

const mode = ref<'visual' | 'raw'>('visual')
const variableKey = ref('')
const prefixText = ref('')
const suffixText = ref('')
const filters = ref<ActiveFilter[]>([])
const rawExpression = ref('')
const showFieldPicker = ref(false)

// ── Initialize from existing expression ─────────

watch(
    () => props.modelValue,
    (val) => {
        if (!val) {
            variableKey.value = ''
            prefixText.value = ''
            suffixText.value = ''
            filters.value = []
            rawExpression.value = ''
            return
        }

        rawExpression.value = val

        // Parse expression into terms to extract prefix/suffix/variable/filters
        const parsed = parseExpression(val)

        if (parsed.hasConcatenation && parsed.terms.length > 1) {
            // Extract prefix, variable, and suffix from terms
            prefixText.value = ''
            suffixText.value = ''
            variableKey.value = ''
            filters.value = []

            for (const term of parsed.terms) {
                if (term.type === 'literal') {
                    // First literal = prefix, last literal = suffix
                    if (!variableKey.value && !prefixText.value) {
                        prefixText.value = term.value
                    } else {
                        suffixText.value = (suffixText.value ? suffixText.value : '') + term.value
                    }
                } else {
                    // Variable term (with filters)
                    variableKey.value = term.value
                    filters.value = term.filters.map(f => ({
                        name: f.name,
                        params: [...f.params],
                    }))
                }
            }
        } else if (parsed.terms.length === 1) {
            const term = parsed.terms[0]
            if (term.type === 'literal') {
                // Just a literal string, no variable
                prefixText.value = term.value
                variableKey.value = ''
                suffixText.value = ''
                filters.value = []
            } else {
                // Single variable with filters
                prefixText.value = ''
                suffixText.value = ''
                variableKey.value = term.value
                filters.value = term.filters.map(f => ({
                    name: f.name,
                    params: [...f.params],
                }))
            }
        } else {
            // Fallback: plain variable
            variableKey.value = val
            prefixText.value = ''
            suffixText.value = ''
            filters.value = []
        }
    },
    { immediate: true },
)

// ── Build terms from visual state ───────────────

function buildTermsFromVisual(): ParsedTerm[] {
    const terms: ParsedTerm[] = []

    if (prefixText.value) {
        terms.push({ type: 'literal', value: prefixText.value, filters: [] })
    }

    if (variableKey.value) {
        terms.push({
            type: 'variable',
            value: variableKey.value,
            filters: filters.value
                .filter(f => f.name !== '')
                .map(f => ({ name: f.name, params: [...f.params] })),
        })
    }

    if (suffixText.value) {
        terms.push({ type: 'literal', value: suffixText.value, filters: [] })
    }

    return terms
}

// ── Computed ────────────────────────────────────

const filtersByCategory = computed(() => {
    const groups: Record<string, FilterDefinition[]> = {}
    for (const filter of FILTER_DEFINITIONS) {
        if (!groups[filter.category]) {
            groups[filter.category] = []
        }
        groups[filter.category].push(filter)
    }
    return groups
})

const popularFilters = computed(() =>
    FILTER_DEFINITIONS.filter(f =>
        ['currency', 'number', 'upper', 'lower', 'default', 'if'].includes(f.name),
    ),
)

const previewExpression = computed(() => {
    if (mode.value === 'raw') {
        const trimmed = rawExpression.value.trim()
        return trimmed ? `{{ ${trimmed} }}` : '{{ }}'
    }

    const terms = buildTermsFromVisual()
    if (terms.length === 0) return '{{ }}'

    const expr = buildExpression(terms)
    return `{{ ${expr} }}`
})

const hasValidExpression = computed(() => {
    if (mode.value === 'raw') {
        return rawExpression.value.trim().length > 0
    }
    return variableKey.value.trim().length > 0 || prefixText.value.length > 0 || suffixText.value.length > 0
})

const textPreview = computed(() => {
    if (!props.currentText) return ''

    const expr = mode.value === 'raw'
        ? rawExpression.value.trim()
        : buildExpression(buildTermsFromVisual())

    const oldExpr = props.modelValue || ''
    const oldPlaceholder = `{{ ${oldExpr} }}`

    if (oldExpr && props.currentText.includes(oldPlaceholder)) {
        return props.currentText.replace(oldPlaceholder, `{{ ${expr} }}`)
    }

    if (!props.currentText.includes('{{')) {
        return props.currentText
    }

    return props.currentText
})

// ── Sync between modes ──────────────────────────

function syncFromVisual(): void {
    const terms = buildTermsFromVisual()
    rawExpression.value = terms.length > 0 ? buildExpression(terms) : ''
}

function syncFromRaw(): void {
    const trimmed = rawExpression.value.trim()
    if (!trimmed) {
        variableKey.value = ''
        prefixText.value = ''
        suffixText.value = ''
        filters.value = []
        return
    }

    const parsed = parseExpression(trimmed)

    if (parsed.hasConcatenation && parsed.terms.length > 1) {
        prefixText.value = ''
        suffixText.value = ''
        variableKey.value = ''
        filters.value = []

        for (const term of parsed.terms) {
            if (term.type === 'literal') {
                if (!variableKey.value && !prefixText.value) {
                    prefixText.value = term.value
                } else {
                    suffixText.value = (suffixText.value ? suffixText.value : '') + term.value
                }
            } else {
                variableKey.value = term.value
                filters.value = term.filters.map(f => ({
                    name: f.name,
                    params: [...f.params],
                }))
            }
        }
    } else if (parsed.terms.length === 1) {
        const term = parsed.terms[0]
        prefixText.value = ''
        suffixText.value = ''
        if (term.type === 'literal') {
            prefixText.value = term.value
            variableKey.value = ''
            filters.value = []
        } else {
            variableKey.value = term.value
            filters.value = term.filters.map(f => ({
                name: f.name,
                params: [...f.params],
            }))
        }
    } else {
        variableKey.value = trimmed
        prefixText.value = ''
        suffixText.value = ''
        filters.value = []
    }
}

// ── Event handlers ──────────────────────────────

function onVisualChange(): void {
    rawExpression.value = buildExpression(buildTermsFromVisual())
}

function onRawChange(): void {
    const trimmed = rawExpression.value.trim()
    if (!trimmed) {
        variableKey.value = ''
        prefixText.value = ''
        suffixText.value = ''
        filters.value = []
        return
    }

    const parsed = parseExpression(trimmed)

    if (parsed.hasConcatenation && parsed.terms.length > 1) {
        prefixText.value = ''
        suffixText.value = ''
        variableKey.value = ''
        filters.value = []

        for (const term of parsed.terms) {
            if (term.type === 'literal') {
                if (!variableKey.value && !prefixText.value) {
                    prefixText.value = term.value
                } else {
                    suffixText.value = (suffixText.value ? suffixText.value : '') + term.value
                }
            } else {
                variableKey.value = term.value
                filters.value = term.filters.map(f => ({
                    name: f.name,
                    params: [...f.params],
                }))
            }
        }
    } else if (parsed.terms.length === 1) {
        const term = parsed.terms[0]
        prefixText.value = ''
        suffixText.value = ''
        if (term.type === 'literal') {
            prefixText.value = term.value
            variableKey.value = ''
            filters.value = []
        } else {
            variableKey.value = term.value
            filters.value = term.filters.map(f => ({
                name: f.name,
                params: [...f.params],
            }))
        }
    } else {
        variableKey.value = trimmed
        prefixText.value = ''
        suffixText.value = ''
        filters.value = []
    }
}

function categoryLabel(category: string): string {
    const labels: Record<string, string> = {
        format: 'Format',
        transform: 'Transform',
        logic: 'Logic',
        text: 'Text',
    }
    return labels[category] ?? category
}

function getFilterDef(name: string): FilterDefinition | undefined {
    return getFilterDefinition(name)
}

function addFilter(): void {
    filters.value.push({ name: '', params: [] })
}

function addFilterByName(name: string): void {
    const def = getFilterDefinition(name)
    if (!def) return
    filters.value.push({
        name: def.name,
        params: def.params.map(p => p.defaultValue),
    })
    onVisualChange()
}

function removeFilter(index: number): void {
    filters.value.splice(index, 1)
    onVisualChange()
}

function onFilterNameChange(index: number): void {
    const filter = filters.value[index]
    const def = getFilterDefinition(filter.name)
    if (def) {
        filter.params = def.params.map(p => p.defaultValue)
    } else {
        filter.params = []
    }
    onVisualChange()
}

function onFieldSelected(path: string): void {
    variableKey.value = path
    showFieldPicker.value = false
    onVisualChange()
}

function applyExpression(): void {
    const expr = mode.value === 'raw'
        ? rawExpression.value.trim()
        : buildExpression(buildTermsFromVisual())

    emit('apply', expr)
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