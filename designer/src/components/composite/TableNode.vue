<script setup lang="ts">
import type { TableNode as TableNodeType } from '../../types/designer'
import CompositeNodeRenderer from '../canvas/CompositeNodeRenderer.vue'
import { useCompositeScale } from '../../composables/useCompositeScale'

defineProps<{
    node: TableNodeType
}>()

const { mmToPxStr } = useCompositeScale()
</script>

<template>
    <div class="inline-block rounded bg-gray-50">
        <table class="w-full border-collapse text-xs">
            <colgroup>
                <col
                    v-for="(w, i) in node.columnWidths"
                    :key="i"
                    :style="{ width: mmToPxStr(w) }"
                />
            </colgroup>
            <tbody>
                <tr v-for="(row, ri) in node.rows" :key="ri">
                    <td
                        v-for="(cell, ci) in row.cells"
                        :key="ci"
                        class="border border-gray-300"
                    >
                        <CompositeNodeRenderer :node="cell.child" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
