<script setup lang="ts">
import { computed, type Component } from 'vue'
import type { DesignerElement } from '@/types/designer'

import LineProperty from './LineElement/LineProperty.vue'
import RectangleProperty from './RectangleElement/RectangleProperty.vue'
import BarcodeProperty from './BarcodeElement/BarcodeProperty.vue'
import PageNumberProperty from './PageNumberElement/PageNumberProperty.vue'
import ImageProperty from './ImageElement/ImageProperty.vue'
import TextProperty from './TextElement/TextProperty.vue'
import TableProperty from './TableElement/TableProperty.vue'
import ContainerProperty from './ContainerElement/ContainerProperty.vue'

const props = defineProps<{ element: DesignerElement }>()

const componentMap: Record<string, Component> = {
  line: LineProperty,
  rectangle: RectangleProperty,
  barcode: BarcodeProperty,
  page_number: PageNumberProperty,
  image: ImageProperty,
  text: TextProperty,
  table: TableProperty,
  container: ContainerProperty,
}

const resolvedComponent = computed(() => componentMap[props.element.type] ?? null)
</script>

<template>
  <component :is="resolvedComponent" :element="element" />
</template>
