<template>
    <div>
        <!-- Element row -->
        <div
            data-testid="band-element"
            :data-tree-id="'tree-element-' + element.id"
            class="flex items-center gap-1 rounded px-2 py-1 text-xs transition-colors"
            :class="rowClasses"
            :style="{ paddingLeft: (24 + 8 + depth * 16) + 'px' }"
            @click.stop="handleSelect"
        >
            <!-- Expand/collapse toggle (containers only) -->
            <button
                v-if="element.type === 'container'"
                class="inline-flex w-3.5 shrink-0 items-center justify-center text-[10px] text-gray-400 hover:text-gray-600 focus:outline-none"
                @click.stop="toggleCollapse"
            >
                {{ isCollapsed ? '▶' : '▼' }}
            </button>
            <span v-else class="w-3.5" />

            <!-- Type icon -->
            <span class="w-4 text-center text-xs">{{ elementIcon(element.type) }}</span>

            <!-- Preview -->
            <span class="flex-1 truncate" :class="bandEnabled ? 'text-gray-700' : 'text-gray-400'">
                {{ elementPreview(element) }}
            </span>

            <!-- Type badge -->
            <span
                class="rounded px-1 py-0.5 text-[10px] font-medium"
                :class="badgeClass"
            >
                {{ element.type }}
            </span>

            <!-- Children count (containers only) -->
            <span
                v-if="element.type === 'container'"
                class="rounded px-1 py-0.5 text-[10px] bg-gray-100 text-gray-500"
            >
                {{ containerChildren.length }}
            </span>
        </div>

        <!-- Recursive children (containers only) -->
        <div v-if="showChildren">
            <ElementTreeItem
                v-for="child in containerChildren"
                :key="child.id"
                :element="child"
                :band-id="bandId"
                :depth="depth + 1"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement, ElementType, ContainerContent } from '@/types/designer'

const props = defineProps<{
    element: DesignerElement
    bandId: string
    depth?: number
}>()

const depth = computed(() => props.depth ?? 0)
const store = useDesignerStore()

// ── Band state (for disabled styling) ─────────

const band = computed(() => store.page.bands?.find(b => b.id === props.bandId))
const bandEnabled = computed(() => band.value?.enabled ?? true)



// ── Expand / Collapse ──────────────────────────

const collapsed = ref(new Set<string>())

const isCollapsed = computed(() => collapsed.value.has(props.element.id))

function toggleCollapse(): void {
    const next = new Set(collapsed.value)
    if (next.has(props.element.id)) {
        next.delete(props.element.id)
    } else {
        next.add(props.element.id)
    }
    collapsed.value = next
}

// ── Container children (recursive reference) ───

const containerChildren = computed<DesignerElement[]>(() => {
    if (props.element.type !== 'container') return []
    const content = props.element.content as ContainerContent
    return content.children ?? []
})

const showChildren = computed(() =>
    props.element.type === 'container'
    && containerChildren.value.length > 0
    && !isCollapsed.value,
)

// ── Selection ──────────────────────────────────

function handleSelect(): void {
    if (!bandEnabled.value) return
    store.selectElement(props.element.id)
    store.selectBand(props.bandId)
}

// ── Row styling ────────────────────────────────

const rowClasses = computed(() => {
    const classes: string[] = []

    if (!bandEnabled.value) {
        classes.push('opacity-60', 'cursor-default')
    } else if (store.isSelected(props.element.id)) {
        classes.push('bg-blue-50', 'text-blue-700')
    } else {
        classes.push('hover:bg-gray-50', 'cursor-pointer')
    }

    return classes.join(' ')
})

// ── Element helpers ────────────────────────────

interface ElementMeta {
    icon: string
    label: string
}

const ELEMENT_META: Record<ElementType, ElementMeta> = {
    text: { icon: 'T', label: 'Text' },
    image: { icon: '\uD83D\uDDBC', label: 'Image' },
    table: { icon: '\u229E', label: 'Table' },
    line: { icon: '\u2501', label: 'Line' },
    rectangle: { icon: '\u25A0', label: 'Rectangle' },
    barcode: { icon: '\u258C\u258C', label: 'Barcode' },
    page_number: { icon: '\u00B6', label: 'Page Number' },
    container: { icon: '\u25A1', label: 'Container' },
}

function elementIcon(type: ElementType): string {
    return ELEMENT_META[type]?.icon ?? '?'
}

function elementPreview(el: DesignerElement): string {
    switch (el.type) {
        case 'text': {
            const c = el.content as { text?: string }
            return c.text?.substring(0, 30) || '(empty)'
        }
        case 'image': {
            const c = el.content as { imageUrl?: string }
            return c.imageUrl?.substring(0, 30) || '(no image)'
        }
        case 'table': {
            const c = el.content as { header?: string[]; variable?: string }
            return c.variable
                ? `${c.variable} (${c.header?.length ?? 0} cols)`
                : `${c.header?.length ?? 0} cols`
        }
        case 'line': {
            const c = el.content as { orientation?: string }
            return c.orientation ?? ''
        }
        case 'rectangle': {
            const c = el.content as { colorVariable?: string }
            return c.colorVariable ? `\uD83C\uDFA8 ${c.colorVariable}` : ''
        }
        case 'barcode': {
            const c = el.content as { symbology?: string; value?: string }
            const val = c.value?.substring(0, 15) || ''
            return `${c.symbology ?? ''}${val ? ' ' + val : ''}`
        }
        case 'page_number': {
            const c = el.content as { format?: string }
            return c.format ?? ''
        }
        case 'container': {
            const c = el.content as ContainerContent
            return `${c.children?.length ?? 0} ${(c.children?.length ?? 0) === 1 ? 'child' : 'children'}`
        }
        default:
            return ''
    }
}

const badgeClass = computed(() => {
    const map: Record<ElementType, string> = {
        text: 'bg-blue-100 text-blue-700',
        image: 'bg-green-100 text-green-700',
        table: 'bg-purple-100 text-purple-700',
        line: 'bg-amber-100 text-amber-700',
        rectangle: 'bg-cyan-100 text-cyan-700',
        barcode: 'bg-pink-100 text-pink-700',
        page_number: 'bg-gray-100 text-gray-600',
        container: 'bg-indigo-100 text-indigo-700',
    }
    return map[props.element.type] ?? 'bg-gray-100 text-gray-600'
})
</script>
