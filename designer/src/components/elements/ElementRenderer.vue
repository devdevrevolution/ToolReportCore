<script setup lang="ts">
import { computed, type Component } from 'vue'
import type { DesignerElement } from '@/types/designer'

import LineElement from './LineElement/LineElement.vue'
import RectangleElement from './RectangleElement/RectangleElement.vue'
import BarcodeElement from './BarcodeElement/BarcodeElement.vue'
import PageNumberElement from './PageNumberElement/PageNumberElement.vue'
import ImageElement from './ImageElement/ImageElement.vue'
import TextElement from './TextElement/TextElement.vue'
import TableElement from './TableElement/TableElement.vue'
import ContainerElement from './ContainerElement/ContainerElement.vue'

const props = defineProps<{ element: DesignerElement; scale?: number }>()

const componentMap: Record<string, Component> = {
  line: LineElement,
  rectangle: RectangleElement,
  barcode: BarcodeElement,
  page_number: PageNumberElement,
  image: ImageElement,
  text: TextElement,
  table: TableElement,
  container: ContainerElement,
}

const resolvedComponent = computed(() => componentMap[props.element.type] ?? null)
</script>

<template>
  <component :is="resolvedComponent" :element="element" :scale="scale" />
</template>
