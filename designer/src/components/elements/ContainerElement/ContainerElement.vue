<script setup lang="ts">
import type { DesignerElement } from '@/types/designer'
import { ref } from 'vue'
import { useContainerElement } from './composables/useContainerElement'
import { useDesignerStore } from '@/stores/designer'
import ElementRenderer from '../ElementRenderer.vue'

const props = defineProps<{ element: DesignerElement; scale?: number }>()
const { children, isEmpty } = useContainerElement(props.element)
const store = useDesignerStore()
const isHovered = ref(false)

function childPxStyle(child: DesignerElement): Record<string, string> {
  const s = props.scale ?? 1
  if (child.positionMode === 'fill') {
    return {
      position: 'absolute',
      top: '0',
      left: '0',
      width: '100%',
      height: '100%',
      ...(store.isSelected(child.id)
        ? { boxShadow: 'inset 0 0 0 2px #3b82f6', backgroundColor: 'rgba(59,130,246,0.08)' }
        : {}),
    }
  }
  return {
    position: 'absolute',
    top: `${(child.y ?? 0) * s}px`,
    left: `${(child.x ?? 0) * s}px`,
    width: `${(child.width ?? 0) * s}px`,
    height: `${(child.height ?? 0) * s}px`,
    ...(store.isSelected(child.id)
      ? { boxShadow: 'inset 0 0 0 2px #3b82f6', backgroundColor: 'rgba(59,130,246,0.08)' }
      : {}),
  }
}

/**
 * Select a child element when clicked on the canvas.
 * Finds the parent band by looking up which band contains this container.
 *
 * - Normal click → selects the child (stops propagation so container isn't also selected)
 * - Alt+click → selects the PARENT container (lets event bubble for canvas drag handler)
 */
function selectChild(child: DesignerElement, e: MouseEvent): void {
  if (child.locked) return

  // Alt+click = select the parent container instead + let event bubble for drag
  if (e.altKey) {
    store.selectElement(props.element.id)
    for (const band of store.page.bands ?? []) {
      if ((band.children ?? []).some(el => el.id === props.element.id)) {
        store.selectBand(band.id)
        break
      }
    }
    // DON'T stop propagation — bubbles to .canvas-element for drag start
    return
  }

  e.stopPropagation()

  if (e.metaKey || e.ctrlKey) {
    store.toggleElementSelection(child.id)
  } else {
    store.selectElement(child.id)
  }

  // Select the parent band
  for (const band of store.page.bands ?? []) {
    if ((band.children ?? []).some(el => el.id === props.element.id)) {
      store.selectBand(band.id)
      break
    }
  }
}
</script>

<template>
  <div
    class="container-element relative h-full w-full overflow-hidden"
    :class="{ 'border-2 border-dashed border-gray-300': isEmpty }"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Drag handle — visible on hover or when container is selected -->
    <div
      v-if="!isEmpty && (isHovered || store.isSelected(element.id))"
      class="container-drag-handle absolute left-0 right-0 top-0 z-30 flex cursor-grab items-center justify-center"
      :style="{ height: '14px' }"
      title="Drag to move container"
    >
      <div class="flex items-center gap-1 rounded-b bg-blue-500/70 px-2 py-0.5">
        <span class="text-[8px] text-white">⠿</span>
        <span class="text-[7px] font-medium tracking-wide text-white/90">CONTAINER</span>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="isEmpty"
      class="flex h-full w-full items-center justify-center text-[10px] text-gray-400"
    >
      Empty container
    </div>

    <!-- Children (clickable for selection) -->
    <div
      v-for="child in children"
      :key="child.id"
      :style="childPxStyle(child)"
      :class="{
        'cursor-grab': !child.locked,
        'cursor-not-allowed': child.locked,
        'opacity-40': !child.visible,
      }"
      @mousedown.stop="selectChild(child, $event)"
    >
      <ElementRenderer :element="child" :scale="scale" />
    </div>
  </div>
</template>
