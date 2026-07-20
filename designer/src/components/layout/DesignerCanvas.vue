<template>
        <div
            ref="canvasContainer"
            data-testid="designer-canvas"
            class="relative h-full min-h-[500px] w-full select-none overflow-hidden bg-gray-200"
            @mousedown="onContainerMouseDown"
            @wheel="onCanvasWheel"
        >
            <!-- Corner block -->
            <div
                class="absolute left-0 top-0 z-30 border-b border-r border-gray-300 bg-gray-100"
                :style="{
                    width: LEFT_RULER_WIDTH + 'px',
                    height: RULER_SIZE + 'px',
                }"
            />

            <!-- Top ruler -->
            <div
                class="absolute top-0 right-0 z-30 overflow-hidden border-b border-gray-300 bg-gray-100 pointer-events-none"
                :style="{
                    left: LEFT_RULER_WIDTH + 'px',
                    height: RULER_SIZE + 'px',
                }"
            >
                <svg ref="topRulerSvg" width="100%" height="20" class="block">
                    <g
                        :transform="`translate(${pageOffsetX - LEFT_RULER_WIDTH}, 0)`"
                    >
                        <template v-for="tick in topRulerTicks" :key="tick.mm">
                            <line
                                :x1="tick.x"
                                :y1="20"
                                :x2="tick.x"
                                :y2="20 - tick.len"
                                stroke="#6b7280"
                                stroke-width="1"
                            />
                            <text
                                v-if="tick.label !== null"
                                :x="tick.x + 2"
                                :y="11"
                                class="fill-gray-500"
                                font-size="9"
                                font-family="monospace"
                            >
                                {{ tick.label }}
                            </text>
                        </template>
                    </g>
                </svg>
            </div>

            <!-- Scrollable area: left panel + page scroll together -->
            <div
                ref="scrollAreaEl"
                class="absolute overflow-auto"
                :style="{
                    top: RULER_SIZE + 'px',
                    left: '0',
                    right: '0',
                    bottom: '0',
                }"
            >
                <div class="flex items-start min-w-max">
                <!-- Left panel: band strips with height labels (no ruler ticks) -->
                <div
                    class="flex-shrink-0 z-30 border-r border-gray-300 bg-gray-100 pointer-events-none"
                    :style="{ width: LEFT_RULER_WIDTH + 'px' }"
                >
                    <!-- Top padding to align with page content area -->
                    <div :style="{ height: (store.page.margin.top * scale) + 'px' }" />
                    <!-- Band strips — full-height colored background + label + height + resize zone -->
                    <template v-for="(band, idx) in pageBands" :key="'bl-' + band.id">
                        <div
                            class="flex items-center gap-1 overflow-hidden border-b border-gray-200 px-1"
                            :style="{
                                height: (bandRenderHeight(band) * scale) + 'px',
                                backgroundColor: bandColor(band.type, 0.08),
                            }"
                        >
                            <div
                                class="h-full w-[3px] flex-shrink-0 rounded-sm"
                                :style="{ backgroundColor: bandColor(band.type) }"
                            />
                            <span
                                class="min-w-0 truncate text-[10px] font-medium leading-tight"
                                :style="{ color: bandColor(band.type) }"
                                >{{ band.label }}</span
                            >
                            <span
                                class="flex-shrink-0 text-[9px] font-normal leading-tight opacity-60"
                                :style="{ color: bandColor(band.type) }"
                                >{{ bandRenderHeight(band) }}mm</span
                            >
                        </div>
                        <!-- Resize hit zone at bottom of each band (except last) -->
                        <div
                            v-if="idx < pageBands.length - 1"
                            class="h-[5px] cursor-s-resize pointer-events-auto hover:bg-purple-400/30"
                            @mousedown.stop="onBandResizeStart($event, band)"
                        />
                    </template>
                </div>

                <!-- Page container (takes remaining width) -->
                <div class="flex-grow min-w-0">
                    <div
                        ref="pageEl"
                        data-testid="a4-page"
                        class="pdf-page relative mx-auto bg-white shadow-lg"
                        :style="pageStyle"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
                @mousedown="onPageMouseDown"
            >
                <!-- Margin overlays (non-printable area shading) — hidden when margin is 0 -->
                <template v-for="side in MARGIN_SIDES" :key="'mo-' + side">
                    <div
                        v-if="store.page.margin[side] > 0"
                        class="absolute pointer-events-none"
                        :style="marginOverlayStyle(side)"
                    />
                </template>

                <!-- Margin guide lines — hidden when margin is 0 -->
                <template v-for="side in MARGIN_SIDES" :key="'mg-' + side">
                    <div
                        v-if="store.page.margin[side] > 0"
                        class="absolute pointer-events-none"
                        :style="marginGuideLineStyle(side)"
                    />
                </template>

                <!-- Design grid (5mm squares, toggleable) -->
                <svg
                    v-if="showGrid"
                    class="absolute pointer-events-none z-0"
                    :style="gridStyle"
                >
                    <defs>
                        <pattern
                            id="design-grid"
                            :width="gridStepPx"
                            :height="gridStepPx"
                            patternUnits="userSpaceOnUse"
                        >
                            <path
                                :d="`M ${gridStepPx} 0 L 0 0 0 ${gridStepPx}`"
                                fill="none"
                                stroke="#d1d5db"
                                stroke-width="0.5"
                            />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#design-grid)" />
                </svg>

                <!-- Band containers (visual sections) -->
                <div
                    v-for="band in pageBands"
                    :key="band.id"
                    class="band-container absolute overflow-hidden"
                    :class="{
                        'ring-1 ring-purple-400':
                            band.id === store.selectedBandId,
                    }"
                    :style="bandStyle(band)"
                    @mousedown.stop="onBandMouseDown($event, band)"
                >
                    <!-- Colored left border (3px) indicating band type -->
                    <div
                        class="absolute left-0 top-0 bottom-0"
                        :style="{
                            width: '3px',
                            backgroundColor: bandColor(band.type),
                        }"
                    />

                    <!-- Band label badge (top-left corner) -->
                    <span
                        class="absolute left-1 top-0.5 rounded px-1 text-[8px] font-semibold uppercase leading-tight"
                        :style="{
                            backgroundColor: bandColor(band.type, 0.12),
                            color: bandColor(band.type),
                        }"
                    >
                        {{ band.label }}
                    </span>

                    <!-- Data binding badge -->
                    <span
                        v-if="band.collectionPath"
                        class="absolute right-1 top-0.5 rounded border border-amber-400 bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold leading-tight text-amber-800 shadow-sm"
                        :title="`Iterates: ${band.collectionPath}`"
                    >
                        ↳ {{ band.collectionPath }}
                    </span>

                    <!-- Subtle band background -->
                    <div
                        class="absolute inset-0 pointer-events-none"
                        :style="{ backgroundColor: bandColor(band.type, 0.03) }"
                    />

                    <!-- Resize handle — bottom edge for all bands -->
                    <div
                        class="absolute bottom-0 left-0 right-0 z-20 h-[5px] cursor-s-resize transition-colors hover:bg-purple-400/30"
                        @mousedown.stop="onBandResizeStart($event, band)"
                    />

                    <!-- Elements of THIS band (rendered relative to band top) -->
                    <!-- NOTE: container children are NOT rendered here — they are
                         rendered by ContainerElement.vue, and filteredBandElements
                         excludes them to avoid double rendering. -->
                    <div
                        v-for="el in filteredBandElements(band)"
                        :key="el.id"
                        class="canvas-element absolute z-10 overflow-hidden"
                        :class="{
                            'ring-2 ring-blue-500':
                                store.isSelected(el.id),
                            'cursor-grab': !el.locked && store.editingElementId !== el.id,
                            'cursor-not-allowed': el.locked,
                            hidden: !el.visible,
                        }"
                        :style="elementPxStyle(el)"
                        @mousedown.stop="onElementMouseDown($event, el)"
                    >
                        <!-- Element renderer -->
                        <ElementRenderer :element="el" :scale="scale" />

                        <!-- Lock overlay -->
                        <div
                            v-if="el.locked"
                            class="absolute inset-0 flex items-center justify-center bg-black/5"
                        >
                            <span class="text-base opacity-60">🔒</span>
                        </div>

                        <!-- Resize handles (only when selected and unlocked) -->
                        <template
                            v-if="
                                store.selectedElementIds.length === 1 && store.isSelected(el.id) && !el.locked && store.editingElementId !== el.id
                            "
                        >
                            <div
                                v-for="handle in RESIZE_HANDLES"
                                :key="handle.corner"
                                class="absolute z-10 h-2 w-2 border border-blue-500 bg-white"
                                :style="handleStyle(handle.corner)"
                                @mousedown.stop="
                                    onResizeStart($event, el, handle)
                                "
                            />
                        </template>
                    </div>
                </div>

                <!-- Container draw preview -->
                <div v-if="isDrawingContainer" :style="drawPreviewStyle" />

                <!-- Drop zone indicator -->
                <div
                    v-if="isDragOver"
                    data-testid="drop-indicator"
                    class="pointer-events-none absolute z-20 border-2 border-dashed"
                    :class="
                        isFieldPathDrag
                            ? 'border-green-400 bg-green-100/30'
                            : 'border-blue-400 bg-blue-100/30'
                    "
                    :style="{
                        left: dropX + 'px',
                        top: dropY + 'px',
                        width: '100px',
                        height: '40px',
                    }"
                >
                    <span
                        v-if="isFieldPathDrag"
                        class="absolute -top-4 left-0 whitespace-nowrap text-[10px] text-green-600"
                        >Link field</span
                    >
                </div>
            </div>

            <!-- Zoom controls (min 100%, zoom-in only) -->
            <div
                class="absolute bottom-3 right-3 z-40 flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 shadow-sm"
            >
                <button
                    class="rounded px-1.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-30"
                    :disabled="zoomPercent <= 100"
                    @click="zoomOut"
                >
                    −
                </button>

                <button
                    class="rounded px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
                    @click="fitToScreen"
                    title="Fit to screen (minimum 100%)"
                >
                    {{ zoomPercent }}%
                </button>

                <button
                    class="rounded px-1.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
                    @click="zoomIn"
                >
                    +
                </button>

                <span class="mx-0.5 h-4 w-px bg-gray-200" />

                <button
                    class="rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                    :class="showGrid
                        ? 'bg-blue-100 text-blue-700'
                        : 'text-gray-400 hover:text-gray-600'"
                    @click="showGrid = !showGrid; snapEnabled = showGrid ? snapEnabled : false"
                    title="Toggle design grid (5mm)"
                >
                    ▦
                </button>

                <button
                    v-if="showGrid"
                    class="rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                    :class="snapEnabled
                        ? 'bg-amber-100 text-amber-700'
                        : 'text-gray-400 hover:text-gray-600'"
                    @click="snapEnabled = !snapEnabled"
                    title="Snap to grid (5mm)"
                >
                    ⊞
                </button>
            </div>
        </div>
    </div>
    </div>
</div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useDesignerStore, isDesignerChild } from "@/stores/designer";
import type {
    DesignerElement,
    ElementType,
    ReportBand,
    BandAnchor,
    ContainerContent,
} from "@/types/designer";
import type { ResizeHandleCorner } from "@/composables/useElementDrag";
import { useElementDrag } from "@/composables/useElementDrag";

import ElementRenderer from '../elements/ElementRenderer.vue'

// ── Store ──────────────────────────────────────

const store = useDesignerStore();

// ── Design grid & snap ─────────────────────────

const showGrid = ref(false);
const snapEnabled = ref(false);

const snapGridSize = computed(() =>
    snapEnabled.value && showGrid.value ? 5 : 1
);

// ── Element drag/resize composable ─────────────

const scale = ref(1);

const {
    isDragging,
    dragPreviewPositions,
    onElementMouseDown,
    onResizeStart,
    cancelDrag,
    teardown,
} = useElementDrag(scale, { snapGridSize });

// ── Refs ───────────────────────────────────────

const canvasContainer = ref<HTMLElement | null>(null);
const scrollAreaEl = ref<HTMLElement | null>(null);
const pageEl = ref<HTMLElement | null>(null);
const topRulerSvg = ref<SVGSVGElement | null>(null);
const isDragOver = ref(false);
const isFieldPathDrag = ref(false);
const dropX = ref(0);
const dropY = ref(0);

const gridStepPx = computed(() => 5 * scale.value);

const gridStyle = computed(() => {
    const s = scale.value;
    const ml = store.page.margin.left * s;
    const mt = store.page.margin.top * s;
    const w = (store.page.width - store.page.margin.left - store.page.margin.right) * s;
    const h = (store.designPageHeight - store.page.margin.top - store.page.margin.bottom) * s;
    return { left: `${ml}px`, top: `${mt}px`, width: `${w}px`, height: `${h}px` };
});

// ── Container draw mode refs ───────────────────

const isDrawingContainer = ref(false)
const drawStartPx = ref({ x: 0, y: 0 })
const drawCurrentPx = ref({ x: 0, y: 0 })
const drawBandId = ref('detail')

// ── Constants ──────────────────────────────────

const SNAP = 1; // mm — snap-to-grid increment
const PADDING = 40; // px — canvas padding around the page

// CSS pixels per millimeter at standard 96dpi.
// 1 inch = 25.4mm = 96px  →  1mm = 96/25.4 ≈ 3.779px
// This is what "actual size" (100%) means on screen.
const PX_PER_MM = 96 / 25.4;


const RULER_SIZE = 20; // px — top ruler bar thickness
const LEFT_RULER_WIDTH = 55; // px — left panel (band labels + height, no ruler ticks)

// Page offset for ruler alignment (measured from getBoundingClientRect)
const pageOffsetX = ref(0);
const pageOffsetY = ref(0);

const RESIZE_HANDLES = [
    { corner: "nw", cursor: "nw-resize" },
    { corner: "n", cursor: "n-resize" },
    { corner: "ne", cursor: "ne-resize" },
    { corner: "e", cursor: "e-resize" },
    { corner: "se", cursor: "se-resize" },
    { corner: "s", cursor: "s-resize" },
    { corner: "sw", cursor: "sw-resize" },
    { corner: "w", cursor: "w-resize" },
] as const;

// ── Margin constants ──────────────────────────

const MARGIN_SIDES = ["top", "right", "bottom", "left"] as const;
type MarginSide = (typeof MARGIN_SIDES)[number];

// ── Band constants ─────────────────────────────

const BAND_COLORS: Record<string, string> = {
    title: "#3b82f6", // blue-500
    pageHeader: "#22c55e", // green-500
    columnHeader: "#14b8a6", // teal-500
    detail: "#6366f1", // indigo-500
    columnFooter: "#0ea5e9", // sky-500
    pageFooter: "#a855f7", // purple-500
    summary: "#f59e0b", // amber-500
};

// ── Band resize state (module-level, not reactive) ──

let bandResizeId: string | null = null;
let bandResizeStartY = 0;
let bandResizeOrigHeight = 0;

// Allow element drag to other bands (module-level state)
let bandResizeElementOffsets: Map<string, number> = new Map();

// Tracks the lowest element bottom in the band being resized (relative mm).
let bandResizeContentMinHeight = 0;

// ── Draw preview style ─────────────────────────

const drawPreviewStyle = computed(() => {
    if (!isDrawingContainer.value) return { display: 'none' }
    const x = Math.min(drawStartPx.value.x, drawCurrentPx.value.x)
    const y = Math.min(drawStartPx.value.y, drawCurrentPx.value.y)
    const w = Math.abs(drawCurrentPx.value.x - drawStartPx.value.x)
    const h = Math.abs(drawCurrentPx.value.y - drawStartPx.value.y)
    return {
        position: 'absolute',
        left: `${x}px`,
        top: `${y}px`,
        width: `${Math.max(w, 5)}px`,
        height: `${Math.max(h, 5)}px`,
        zIndex: '30',
        border: '2px dashed #3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        pointerEvents: 'none' as const,
    }
})

// ── Zoom (minimum 100%, zoom-in only) ─────────

const zoomPercent = computed(() => Math.round((scale.value / PX_PER_MM) * 100));

const ZOOM_PRESETS_IN = [100, 125, 150, 200, 250, 300];

/** Set scale floor to 100% actual physical size — never below. */
function setZoom(newScale: number): void {
    scale.value = Math.max(PX_PER_MM, newScale);
    requestAnimationFrame(() => updatePageOffset());
}

/** Convert a percentage of actual size to the internal scale (px/mm). */
function pctToScale(pct: number): number {
    return (pct / 100) * PX_PER_MM;
}

function recalcScale(): void {
    const el = canvasContainer.value;
    if (!el) return;

    const cw = el.clientWidth;
    const ch = el.clientHeight;

    if (cw === 0 || ch === 0) return;

    // Account for ruler panels: subtract left panel + top ruler from available space
    const availableW = cw - LEFT_RULER_WIDTH;
    const availableH = ch - RULER_SIZE;

    const scaleX = (availableW - PADDING * 2) / store.page.width;
    const scaleY = (availableH - PADDING * 2) / store.designPageHeight;

    // Fit to available space, but never below 100%
    setZoom(Math.min(scaleX, scaleY));
}

function zoomIn(): void {
    const curPct = (scale.value / PX_PER_MM) * 100;
    const next = ZOOM_PRESETS_IN.find((p) => p > curPct + 0.5);
    if (next !== undefined) setZoom(pctToScale(next));
}

function zoomOut(): void {
    const curPct = (scale.value / PX_PER_MM) * 100;
    const prev = [...ZOOM_PRESETS_IN].reverse().find((p) => p < curPct - 0.5);
    if (prev !== undefined) setZoom(pctToScale(prev));
}

function fitToScreen(): void {
    recalcScale();
}

function onCanvasWheel(e: WheelEvent): void {
    if (!e.ctrlKey && !e.metaKey) return;
    e.preventDefault();

    if (e.deltaY < 0) zoomIn();
    else if (scale.value > PX_PER_MM) zoomOut();
}

/** Reset scale to 100%. */
function lockScale(): void {
    setZoom(PX_PER_MM);
}

// ── Page style ─────────────────────────────────

const pageStyle = computed(() => ({
    width: `${store.page.width * scale.value}px`,
    height: `${store.designPageHeight * scale.value}px`,
}));

// ── Ruler helpers ──────────────────────────────

/**
 * Determine tick step (mm) based on current zoom level
 * to avoid overcrowding the ruler.
 */
function rulerStep(): number {
    const s = scale.value;
    if (s >= 0.8) return 10; // every 10mm with 1mm sub-ticks
    if (s >= 0.4) return 20;
    if (s >= 0.2) return 50;
    return 100;
}

interface RulerTick {
    mm: number;
    x: number; // horizontal position in px (top ruler)
    y: number; // vertical position in px (left ruler)
    len: number; // tick length in px
    label: string | null;
}

/**
 * Ticks for the top (horizontal) ruler.
 * Origin is at the page's left edge — offset by pageOffsetX in the SVG.
 */
const topRulerTicks = computed<RulerTick[]>(() => {
    const s = scale.value;
    const pageW = store.page.width;
    const step = rulerStep();
    const ticks: RulerTick[] = [];

    for (let mm = 0; mm <= pageW; mm++) {
        const x = mm * s;
        const isMajor = mm % step === 0;
        const isMid = mm % (step / 2) === 0;

        if (isMajor) {
            ticks.push({ mm, x, y: 0, len: 14, label: String(mm) });
        } else if (isMid && step > 5) {
            ticks.push({ mm, x, y: 0, len: 10, label: null });
        } else if (step <= 10) {
            // Only show 1mm ticks at high zoom
            ticks.push({ mm, x, y: 0, len: 6, label: null });
        }
    }
    return ticks;
});

/**
 * Measure page offset within the canvas container so ruler ticks
 * align with the page edges (handles mx-auto centering).
 */
function updatePageOffset(): void {
    if (!canvasContainer.value || !pageEl.value) return;
    const canvasRect = canvasContainer.value.getBoundingClientRect();
    const pageRect = pageEl.value.getBoundingClientRect();
    pageOffsetX.value = pageRect.left - canvasRect.left;
    pageOffsetY.value = pageRect.top - canvasRect.top;
}

// ── Helpers ────────────────────────────────────

function snapToGrid(value: number, gridSize: number = SNAP): number {
    return Math.round(value / gridSize) * gridSize;
}

/**
 * Convert an element's model coordinates (mm) to pixel styles for the canvas.
 * Now that elements are nested in bands, top is relative to band top.
 * left is relative to the content area (left margin line).
 * Since the parent band container is already positioned at left: margin.left,
 * the element's left is simply el.x * scale.
 */
function elementPxStyle(
    el: DesignerElement,
): Record<string, string | undefined> {
    const s = scale.value;

    // During drag, use preview position (no store writes) + shadow opacity
    const preview = dragPreviewPositions.value[el.id];
    const x = preview?.x ?? el.x;
    const y = preview?.y ?? el.y;

    const styles: Record<string, string | undefined> = {
        left: `${x * s}px`,
        top: `${y * s}px`,
        width: `${el.width * s}px`,
        height: `${el.height * s}px`,
        display: "flex",
    };

    // Shadow opacity during drag preview
    if (preview) {
        styles.opacity = "0.4";
        styles.zIndex = "50";
    }

    // Background color — applied on the outer container so it fills the
    // full element area, not just the text content bounds.
    if (el.styles.backgroundColor) {
        styles.backgroundColor = el.styles.backgroundColor;
    }

    // Border
    if (el.styles.border && (el.styles.border as any).width) {
        const b = el.styles.border as { width: number; color: string; style: string };
        styles.border = `${b.width * s}px ${b.style ?? 'solid'} ${b.color ?? '#000000'}`;
    }

    // Border radius — clip the whole element box in the preview.
    if (el.styles.borderRadius) {
        styles.borderRadius = `${el.styles.borderRadius}px`;
        styles.overflow = "hidden";
    }

    // Horizontal alignment (justify-content)
    switch (el.styles.textAlign) {
        case "left":
            styles.justifyContent = "flex-start";
            break;
        case "center":
            styles.justifyContent = "center";
            break;
        case "right":
            styles.justifyContent = "flex-end";
            break;
        case "justify":
            styles.justifyContent = "center"; // justify doesn't apply to flex, use center as fallback
            break;
    }

    // Vertical alignment (align-items)
    switch (el.styles.verticalAlign) {
        case "top":
            styles.alignItems = "flex-start";
            break;
        case "middle":
            styles.alignItems = "center";
            break;
        case "bottom":
            styles.alignItems = "flex-end";
            break;
    }

    if (el.rotation) {
        styles.transform = `rotate(${el.rotation}deg)`;
    }

    return styles;
}

// ── Margin helpers ─────────────────────────────

/**
 * Style for the semi-transparent margin overlay on each side.
 */
function marginOverlayStyle(side: MarginSide): Record<string, string> {
    const s = scale.value;
    const pw = `${store.page.width * s}px`;
    const ph = `${store.designPageHeight * s}px`;
    const mt = `${store.page.margin.top * s}px`;
    const mr = `${store.page.margin.right * s}px`;
    const mb = `${store.page.margin.bottom * s}px`;
    const ml = `${store.page.margin.left * s}px`;

    switch (side) {
        case "top":
            return {
                left: "0",
                top: "0",
                width: pw,
                height: mt,
                backgroundColor: "rgba(0,0,0,0.06)",
            };
        case "bottom":
            return {
                left: "0",
                top: `calc(${ph} - ${mb})`,
                width: pw,
                height: mb,
                backgroundColor: "rgba(0,0,0,0.06)",
            };
        case "left":
            return {
                left: "0",
                top: mt,
                width: ml,
                height: `calc(${ph} - ${mt} - ${mb})`,
                backgroundColor: "rgba(0,0,0,0.06)",
            };
        case "right":
            return {
                left: `calc(${pw} - ${mr})`,
                top: mt,
                width: mr,
                height: `calc(${ph} - ${mt} - ${mb})`,
                backgroundColor: "rgba(0,0,0,0.06)",
            };
    }
}

/**
 * Style for the visible thin line at the margin edge (visual only, not draggable).
 */
function marginGuideLineStyle(side: MarginSide): Record<string, string> {
    const s = scale.value;
    const ml = store.page.margin.left * s;
    const mt = store.page.margin.top * s;
    const mr = store.page.margin.right * s;
    const mb = store.page.margin.bottom * s;
    const cw = (store.page.width - ml / s - mr / s) * s;
    const ph = store.designPageHeight * s;
    const ch = ph - mt - mb;

    switch (side) {
        case "top":
            return {
                left: `${ml}px`,
                top: `${mt}px`,
                width: `${cw}px`,
                height: "1px",
                backgroundColor: "rgba(59,130,246,0.4)",
            };
        case "bottom":
            return {
                left: `${ml}px`,
                top: `${mt + ch}px`,
                width: `${cw}px`,
                height: "1px",
                backgroundColor: "rgba(59,130,246,0.4)",
            };
        case "left":
            return {
                left: `${ml}px`,
                top: `${mt}px`,
                width: "1px",
                height: `${ch}px`,
                backgroundColor: "rgba(59,130,246,0.4)",
            };
        case "right":
            return {
                left: `${ml + cw}px`,
                top: `${mt}px`,
                width: "1px",
                height: `${ch}px`,
                backgroundColor: "rgba(59,130,246,0.4)",
            };
    }
}

// ── Band helpers ───────────────────────────────

const pageBands = computed<ReportBand[]>(() => {
    return (store.page.bands ?? []).filter(b => b.enabled !== false);
});

/**
 * Convert a band type to a hex or rgba color string.
 */
function bandColor(type: string, alpha: number = 1): string {
    const hex = BAND_COLORS[type] ?? "#6b7280";
    if (alpha === 1) return hex;
    const r = Number.parseInt(hex.slice(1, 3), 16);
    const g = Number.parseInt(hex.slice(3, 5), 16);
    const b = Number.parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Get the anchor type for a band — 'top' grows downward from the page
 * top, 'bottom' grows upward from the page bottom, 'fill' (detail)
 * occupies the remaining space between top and bottom groups.
 */
function bandAnchor(band: ReportBand): BandAnchor {
    return band.anchor;
}

/**
 * Compute the Y position (from page top, in mm) of a band in design mode.
 * Uses ONLY enabled bands (pageBands), so disabled bands take no space.
 */
function bandYPos(band: ReportBand): number {
    let y = store.page.margin.top;
    for (const b of pageBands.value) {
        if (b.id === band.id) return y;
        y += b.height;
    }
    return y;
}

/**
 * The rendered height of a band in design mode — always the stored height.
 */
function bandRenderHeight(band: ReportBand): number {
    return band.height;
}

/**
 * Y position for a band ID (lookup from pageBands, ignoring disabled bands).
 */
function bandYPosById(bandId: string): number {
    let y = store.page.margin.top;
    for (const b of pageBands.value) {
        if (b.id === bandId) return y;
        y += b.height;
    }
    return y;
}

/**
 * Rendered height for a band ID.
 */
function bandRenderHeightById(bandId: string): number {
    return pageBands.value.find(b => b.id === bandId)?.height ?? 0;
}

/**
 * Convert a band's model position to pixel styles for the canvas.
 * Positioned relative to the .pdf-page, within the content area.
 * Uses `bandYPos` and `bandRenderHeight` for anchor-aware layout.
 */
function bandStyle(band: ReportBand): Record<string, string> {
    const s = scale.value;
    const topPx = bandYPos(band) * s;
    const leftPx = store.page.margin.left * s;
    const widthPx =
        (store.page.width - store.page.margin.left - store.page.margin.right) *
        s;
    const heightPx = bandRenderHeight(band) * s;

    return {
        left: `${leftPx}px`,
        top: `${topPx}px`,
        width: `${widthPx}px`,
        height: `${heightPx}px`,
    };
}

/**
 * Determine which band (if any) contains a given Y position (in mm).
 * Bands are stacked sequentially in design mode.
 */
function getBandAtPosition(yMm: number): string | undefined {
    const bands = pageBands.value;
    for (const band of bands) {
        const y = bandYPos(band);
        const h = bandRenderHeight(band);
        if (yMm >= y && yMm < y + h) {
            return band.id;
        }
    }
    return undefined;
}

/**
 * Determine if an element overlaps another band by more than 25% of its height.
 * Returns the target band id, or `undefined` if no sufficient overlap.
 */
function getTargetBand(
    el: DesignerElement,
    currentBandId?: string,
): string | undefined {
    const bands = pageBands.value;
    const elTop = el.y;
    const elBottom = el.y + el.height;
    const elH = el.height;
    const skipId = currentBandId ?? elementBandId(el);

    for (const band of bands) {
        if (band.id === skipId) continue;

        const bTop = bandYPos(band);
        const bBottom = bTop + bandRenderHeight(band);
        const overlap = Math.max(
            0,
            Math.min(elBottom, bBottom) - Math.max(elTop, bTop),
        );

        if (elH > 0 && overlap / elH > 0.25) {
            return band.id;
        }
    }
    return undefined;
}

/**
 * Filter elements that belong to the band loop, excluding those that are
 * children of a container element (they are rendered by ContainerElement.vue
 * instead — rendering them twice would cause visual duplication).
 */
function filteredBandElements(band: ReportBand): DesignerElement[] {
    const childIds = new Set<string>()
    const designerChildren = (band.children ?? []).filter(isDesignerChild)
    for (const el of designerChildren) {
        if (el.type === 'container' && el.content) {
            const content = el.content as ContainerContent
            for (const child of content.children ?? []) {
                childIds.add(child.id)
            }
        }
    }
    return designerChildren.filter(el => !childIds.has(el.id))
}

/**
 * Return the bounding box (in mm) of the band an element belongs to.
 * Accepts bandId directly (since elements no longer carry bandId).
 * Returns `null` if the band isn't found.
 */
function elementBandBounds(
    elementOrBandId: DesignerElement | string,
): { left: number; right: number; top: number; bottom: number } | null {
    const bandId =
        typeof elementOrBandId === "string"
            ? elementOrBandId
            : elementBandId(elementOrBandId);
    if (!bandId) return null;

    const band = store.page.bands?.find((b) => b.id === bandId);
    if (!band) return null;

    return {
        left: store.page.margin.left,
        right: store.page.width - store.page.margin.right,
        top: bandYPos(band),
        bottom: bandYPos(band) + bandRenderHeight(band),
    };
}

/**
 * Find an element across all bands by id.
 */
function findElementById(id: string): DesignerElement | undefined {
    const bands = store.page.bands ?? [];
    for (const band of bands) {
        const el = (band.children ?? []).find((e) => e.id === id);
        if (el && isDesignerChild(el)) return el;
    }
    return undefined;
}

/**
 * Find the band that contains an element by id.
 */
function getElementBand(id: string): ReportBand | undefined {
    const bands = store.page.bands ?? [];
    return bands.find((b) => (b.children ?? []).some((el) => el.id === id));
}

/**
 * Get an element's current band id (or undefined if not found).
 */
function elementBandId(el: DesignerElement): string | undefined {
    const band = getElementBand(el.id);
    return band?.id;
}

/**
 * Clamp a value between min and max.
 */
function clamp(val: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, val));
}

/**
 * Find a container element at a given (relX, relY) position within a band.
 * Searches top-level elements in reverse z-order (last rendered = on top).
 */
function findContainerAtPosition(
    bandId: string,
    relX: number,
    relY: number,
): DesignerElement | null {
    const band = store.page.bands?.find((b) => b.id === bandId);
    if (!band) return null;

    const elements = (band.children ?? []).filter(isDesignerChild);
    // Iterate in reverse so the top-most (last rendered) container wins
    for (let i = elements.length - 1; i >= 0; i--) {
        const el = elements[i];
        if (
            el.type === "container" &&
            relX >= el.x &&
            relX <= el.x + el.width &&
            relY >= el.y &&
            relY <= el.y + el.height
        ) {
            return el;
        }
    }
    return null;
}

type DraggedField = {
    path: string;
    name: string;
    type: string;
    datasourceId?: string;
};

function collectionPathFromField(field: DraggedField): string | null {
    if (field.type === "array") {
        // Root-level array: "[]" → "" (empty = root data is the collection)
        // Nested: "anagrafica[]" → "anagrafica"
        const stripped = field.path.replace(/\[\]$/u, "");
        return stripped === "" ? "" : stripped;
    }

    const arrayMarker = field.path.indexOf("[]");
    if (arrayMarker === -1) return null;

    // "[].cognome" → "" (root array), "anagrafica[].cognome" → "anagrafica"
    const prefix = field.path.slice(0, arrayMarker);
    return prefix === "" ? "" : prefix;
}

function normalizeFieldPathForBand(
    path: string,
    collectionPath?: string | null,
): string {
    // null/undefined = no collection context, return as-is
    if (collectionPath === null || collectionPath === undefined) return path;

    // Empty string = root-level array:
    // strip "[]." prefix from "[].cognome" → "cognome"
    if (collectionPath === "") {
        if (path.startsWith("[].")) {
            return path.slice(3);
        }

        return path;
    }

    const normalizedCollection = collectionPath.replace(/\[\]$/u, "");
    const prefixes = [`${normalizedCollection}[].`, `${normalizedCollection}.`];
    const prefix = prefixes.find((p) => path.startsWith(p));

    return prefix ? path.slice(prefix.length) : path;
}

/**
 * CSS position for each resize handle corner.
 */
function handleStyle(corner: ResizeHandleCorner): Record<string, string> {
    const base: Record<string, string> = {
        position: "absolute",
        width: "8px",
        height: "8px",
        zIndex: "10",
        backgroundColor: "white",
        border: "1px solid #3b82f6",
    };

    switch (corner) {
        case "nw":
            return { ...base, top: "-4px", left: "-4px", cursor: "nw-resize" };
        case "n":
            return {
                ...base,
                top: "-4px",
                left: "calc(50% - 4px)",
                cursor: "n-resize",
            };
        case "ne":
            return { ...base, top: "-4px", right: "-4px", cursor: "ne-resize" };
        case "e":
            return {
                ...base,
                top: "calc(50% - 4px)",
                right: "-4px",
                cursor: "e-resize",
            };
        case "se":
            return {
                ...base,
                bottom: "-4px",
                right: "-4px",
                cursor: "se-resize",
            };
        case "s":
            return {
                ...base,
                bottom: "-4px",
                left: "calc(50% - 4px)",
                cursor: "s-resize",
            };
        case "sw":
            return {
                ...base,
                bottom: "-4px",
                left: "-4px",
                cursor: "sw-resize",
            };
        case "w":
            return {
                ...base,
                top: "calc(50% - 4px)",
                left: "-4px",
                cursor: "w-resize",
            };
    }
}

// ── Container selection (click on empty area) ──

function onContainerMouseDown(e: MouseEvent): void {
    const target = e.target as HTMLElement;

    // Ignore clicks on elements, band labels, or resize handles
    if (target.closest(".canvas-element")) return;
    if (target.closest(".band-container")) return;

    // Exit inline edit mode when clicking outside the edited element
    if (store.editingElementId) store.exitEditMode();

    store.clearSelection();
    store.selectBand(null);
}

// ── Container draw mode handlers ───────────────

/**
 * Start drawing a container from a mousedown event.
 * Extracted so both band-level and page-level handlers can use it.
 */
function startDrawFromEvent(e: MouseEvent): void {
    if (!pageEl.value) return

    const rect = pageEl.value.getBoundingClientRect()

    drawStartPx.value = {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
    }
    drawCurrentPx.value = { ...drawStartPx.value }

    // Determine band at this position (YB position in mm)
    const absYmm = (e.clientY - rect.top) / scale.value
    const bandId = getBandAtPosition(absYmm) ?? 'detail'
    drawBandId.value = bandId

    isDrawingContainer.value = true

    document.addEventListener('mousemove', onDrawMouseMove)
    document.addEventListener('mouseup', onDrawMouseUp)
}

/**
 * Band-level mousedown — selects the band normally,
 * but starts drawing when container drawMode is active.
 */
function onBandMouseDown(e: MouseEvent, band: ReportBand): void {
    // Exit inline edit mode when clicking a band
    if (store.editingElementId) store.exitEditMode()

    if (store.containerDrawMode) {
        const target = e.target as HTMLElement
        // Don't draw on existing elements
        if (target.closest('.canvas-element')) {
            store.selectBand(band.id)
            return
        }
        // Start drawing in this band
        startDrawFromEvent(e)
    } else {
        store.selectBand(band.id)
    }
}

/**
 * Page-level mousedown — handles draws in the margin area
 * (outside any band-container, where the event isn't stopped).
 */
function onPageMouseDown(e: MouseEvent): void {
    if (store.editingElementId) store.exitEditMode()
    if (!store.containerDrawMode) return

    const target = e.target as HTMLElement
    // Ignore clicks on existing elements
    if (target.closest('.canvas-element')) return
    // Band-level draws are handled by onBandMouseDown —
    // page handler only fires for margin-area draws
    if (target.closest('.band-container')) return

    startDrawFromEvent(e)
}

function onDrawMouseMove(e: MouseEvent): void {
    if (!isDrawingContainer.value || !pageEl.value) return

    const rect = pageEl.value.getBoundingClientRect()
    drawCurrentPx.value = {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
    }
}

function onDrawMouseUp(): void {
    if (!isDrawingContainer.value) return

    isDrawingContainer.value = false

    document.removeEventListener('mousemove', onDrawMouseMove)
    document.removeEventListener('mouseup', onDrawMouseUp)

    const startPx = drawStartPx.value
    const currentPx = drawCurrentPx.value

    // Calculate dimensions in mm
    const dxPx = currentPx.x - startPx.x
    const dyPx = currentPx.y - startPx.y
    const absDx = Math.abs(dxPx)
    const absDy = Math.abs(dyPx)

    // Minimum size check (20px ≈ 5mm at 100% zoom)
    if (absDx < 20 && absDy < 20) {
        // Too small — exit draw mode without creating
        store.containerDrawMode = false
        return
    }

    const s = scale.value
    const xMm = Math.min(startPx.x, currentPx.x) / s
    const yMm = Math.min(startPx.y, currentPx.y) / s
    const wMm = Math.max(absDx / s, 5)
    const hMm = Math.max(absDy / s, 5)

    // Convert to band-relative coordinates
    const bandId = drawBandId.value
    const bandTopMm = bandYPosById(bandId)

    const grid = snapGridSize.value
    const relX = snapToGrid(xMm - store.page.margin.left, grid)
    const relY = snapToGrid(yMm - bandTopMm, grid)

    // Clamp within band
    const band = pageBands.value.find(b => b.id === bandId)
    const bHeight = band?.height ?? 100
    const contentWidth = store.page.width - store.page.margin.left - store.page.margin.right
    const clampedX = Math.max(0, Math.min(relX, contentWidth - wMm))
    const clampedY = Math.max(0, Math.min(relY, bHeight - hMm))

    store.pushHistory()
    const newElId = store.addElement('container', { x: clampedX, y: clampedY }, bandId)
    store.resizeElement(newElId, snapToGrid(wMm, grid), snapToGrid(hMm, grid))
    store.selectElement(newElId)

    // Deactivate draw mode after creating one container
    store.containerDrawMode = false
}

// ── Drop zone (receive new elements from toolbar) ─

function onBandResizeStart(e: MouseEvent, band: ReportBand): void {
    e.stopPropagation();

    store.pushHistory();

    bandResizeId = band.id;
    bandResizeStartY = e.clientY;
    bandResizeOrigHeight = band.height;

    bandResizeContentMinHeight = 0;
    for (const el of (band.children ?? []).filter(isDesignerChild)) {
        const elBottom = el.y + el.height;
        if (elBottom > bandResizeContentMinHeight) {
            bandResizeContentMinHeight = elBottom;
        }
    }

    document.addEventListener("mousemove", onBandResizeMouseMove);
    document.addEventListener("mouseup", onBandResizeMouseUp);
}

function onBandResizeMouseMove(e: MouseEvent): void {
    if (!bandResizeId) return;

    const dy = (e.clientY - bandResizeStartY) / scale.value;
    const newHeight = snapToGrid(bandResizeOrigHeight + dy, snapGridSize.value);
    const minH = Math.max(0, bandResizeContentMinHeight);

    store.setBandHeight(bandResizeId, Math.max(minH, newHeight));
}

function onBandResizeMouseUp(): void {
    bandResizeId = null;
    bandResizeOrigHeight = 0;
    bandResizeElementOffsets = new Map();
    bandResizeContentMinHeight = 0;

    document.removeEventListener("mousemove", onBandResizeMouseMove);
    document.removeEventListener("mouseup", onBandResizeMouseUp);
}

// ── Drop zone (receive new elements from toolbar) ─

function dataTransferHasType(
    dataTransfer: DataTransfer | null | undefined,
    type: string,
): boolean {
    if (!dataTransfer || !dataTransfer.types) return false;

    const types = dataTransfer.types as unknown;
    if (typeof (types as any).contains === "function") {
        return (types as any).contains(type);
    }

    if (typeof (types as any).includes === "function") {
        return (types as any).includes(type);
    }

    if (Array.isArray(types)) {
        return types.includes(type);
    }

    return false;
}

function onDragOver(e: DragEvent): void {
    e.preventDefault();

    const hasElementType = dataTransferHasType(e.dataTransfer, "element-type");
    const hasFieldPath = dataTransferHasType(e.dataTransfer, "field-path");

    if (hasElementType || hasFieldPath) {
        isDragOver.value = true;
        isFieldPathDrag.value = !!hasFieldPath;
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = hasFieldPath ? "link" : "copy";
        }

        // Update drop indicator position relative to the pdf page
        const pageEl = (e.currentTarget as HTMLElement).closest(".pdf-page");
        if (pageEl) {
            const rect = pageEl.getBoundingClientRect();
            dropX.value = e.clientX - rect.left;
            dropY.value = e.clientY - rect.top;
        }
    }
}

function onDragLeave(): void {
    isDragOver.value = false;
    isFieldPathDrag.value = false;
}

function onDrop(e: DragEvent): void {
    e.preventDefault();
    isDragOver.value = false;
    isFieldPathDrag.value = false;

    const pageEl = (e.currentTarget as HTMLElement).closest(".pdf-page");
    if (!pageEl) return;

    const rect = pageEl.getBoundingClientRect();

    // Check for field-path first (binding an existing element to a discovered field)
    const fieldData = e.dataTransfer?.getData("field-path");
    if (fieldData) {
        try {
            const field = JSON.parse(fieldData) as DraggedField;
            const dropXmm = (e.clientX - rect.left) / scale.value;
            const dropYmm = (e.clientY - rect.top) / scale.value;

            // Convert to relative to content/band for collision check
            const relDropX = dropXmm - store.page.margin.left;
            const targetBandId = getBandAtPosition(dropYmm);
            const targetBand = targetBandId
                ? store.page.bands?.find((b) => b.id === targetBandId)
                : undefined;

            // Find element under the drop position
            const dropEl = (store.page.bands ?? [])
                .flatMap((b) => b.children.filter(isDesignerChild))
                .find((el) => {
                    const band = getElementBand(el.id);
                    const bTop = band ? bandYPosById(band.id) : 0;
                    const absElY = el.y + bTop;
                    return (
                        relDropX >= el.x &&
                        relDropX <= el.x + el.width &&
                        dropYmm >= absElY &&
                        dropYmm <= absElY + el.height
                    );
                });

            if (
                dropEl &&
                (dropEl.type === "text" ||
                    dropEl.type === "table" ||
                    dropEl.type === "barcode" ||
                    dropEl.type === "image")
            ) {
                // Set variable binding and update text/image to show the full path
                const contentPatch: Record<string, unknown> = {
                    variable: normalizeFieldPathForBand(
                        field.path,
                        targetBand?.collectionPath,
                    ),
                };

                if (dropEl.type === "image") {
                    contentPatch.imageUrl = field.path;
                } else if (dropEl.type === "text" || dropEl.type === "barcode") {
                    const normalizedVar = contentPatch.variable as string | undefined;
                    contentPatch.text = normalizedVar
                        ? `{{ ${normalizedVar} }}`
                        : field.path;
                }

                store.updateElement(dropEl.id, {
                    content: {
                        ...dropEl.content,
                        ...contentPatch,
                    } as never,
                });
            } else if (targetBand) {
                const inferredCollectionPath = collectionPathFromField(field);
                const nextCollectionPath =
                    targetBand.collectionPath ?? inferredCollectionPath;

                if (
                    targetBand.type === "detail" &&
                    (nextCollectionPath !== null || field.datasourceId)
                ) {
                    store.updateBand(targetBand.id, {
                        collectionPath:
                            nextCollectionPath !== null
                                ? nextCollectionPath
                                : targetBand.collectionPath ?? null,
                        datasourceId:
                            field.datasourceId ??
                            targetBand.datasourceId ??
                            null,
                    });
                }

                const bTop = bandYPos(targetBand);
                const bHeight = bandRenderHeight(targetBand);
                const contentWidth =
                    store.page.width -
                    store.page.margin.left -
                    store.page.margin.right;
                const variable = normalizeFieldPathForBand(
                    field.path,
                    nextCollectionPath,
                );
                const gridS = snapGridSize.value
                const newElId = store.addElement(
                    "text",
                    {
                        x: clamp(snapToGrid(relDropX, gridS), 0, contentWidth - 100),
                        y: clamp(snapToGrid(dropYmm - bTop, gridS), 0, bHeight - 15),
                    },
                    targetBand.id,
                );

                const newEl = findElementById(newElId);
                if (newEl) {
                    store.updateElement(newElId, {
                        content: {
                            ...newEl.content,
                            text: variable ? `{{ ${variable} }}` : field.path,
                            variable,
                        } as never,
                    });
                    store.selectElement(newElId);
                }
            }
        } catch {
            // Invalid JSON — ignore silently
        }
        return;
    }

    // Existing element-type handling (adding new elements from palette)
    const type = e.dataTransfer?.getData("element-type") as
        | ElementType
        | undefined;
    if (!type) return;

    // Convert pixel position to mm coordinates
    const gridS = snapGridSize.value;
    const absX = snapToGrid((e.clientX - rect.left) / scale.value, gridS);
    const absY = snapToGrid((e.clientY - rect.top) / scale.value, gridS);

    // Determine which band the element lands in
    const bandId = getBandAtPosition(absY) ?? "detail";
    const bTop = bandYPosById(bandId);
    const bHeight = bandRenderHeightById(bandId);

    // Convert to relative X and Y (relative to band content area)
    const relX = absX - store.page.margin.left;
    const relY = absY - bTop;

    // ── Check if drop is on a container element ─────────
    const containerAtDrop = findContainerAtPosition(bandId, relX, relY);
    if (containerAtDrop) {
        const content = containerAtDrop.content as ContainerContent;
        const padding = content.padding ?? 4;

        // Position relative to container's content area (inside padding)
        const childX = snapToGrid(relX - containerAtDrop.x - padding, gridS);
        const childY = snapToGrid(relY - containerAtDrop.y - padding, gridS);

        // Clamp within container content area
        const containerContentW = containerAtDrop.width - padding * 2;
        const containerContentH = containerAtDrop.height - padding * 2;
        const clampedChildX = snapToGrid(
            clamp(childX, 0, containerContentW - 20), gridS,
        );
        const clampedChildY = snapToGrid(
            clamp(childY, 0, containerContentH - 20), gridS,
        );

        const newElId = store.addChildElement(
            containerAtDrop.id,
            type,
            clampedChildX,
            clampedChildY,
        );
        store.selectElement(newElId);
        return;
    }

    // ── Band-level add (no container hit) ──────────────
    const newElId = store.addElement(type, { x: relX, y: relY }, bandId);

    // Clamp the newly placed element within its band
    const newEl = findElementById(newElId);
    if (newEl) {
        const contentWidth =
            store.page.width - store.page.margin.left - store.page.margin.right;

        const cx = clamp(newEl.x, 0, contentWidth - newEl.width);
        const cy = clamp(newEl.y, 0, bHeight - newEl.height);
        if (cx !== newEl.x || cy !== newEl.y) {
            store.moveElement(newElId, cx, cy);
        }
    }
}

// ── Keyboard shortcuts ─────────────────────────

function onKeyDown(e: KeyboardEvent): void {
    const tag = (e.target as HTMLElement).tagName;
    const isInputFocused = tag === "INPUT" || tag === "TEXTAREA";

    // Undo / Redo (always available, even with input focused)
    const isMod = e.metaKey || e.ctrlKey;

    if (isMod && e.key === "z" && !e.shiftKey) {
        e.preventDefault();
        store.undo();
        return;
    }
    if ((isMod && e.key === "z" && e.shiftKey) || (isMod && e.key === "y")) {
        e.preventDefault();
        store.redo();
        return;
    }

    // Skip element-specific shortcuts when typing
    if (isInputFocused) return;

    const selIds = store.selectedElementIds;
    if (selIds.length === 0) return;

    // Delete / Backspace — remove all selected
    if (e.key === "Delete" || e.key === "Backspace") {
        // Filter out locked elements from deletion
        const unlockedIds = selIds.filter(id => {
            const el = findElementById(id);
            return el && !el.locked;
        });
        if (unlockedIds.length > 0) {
            store.removeElements(unlockedIds);
        }
        return;
    }

    // Escape — cancel drag if active, otherwise deselect
    if (e.key === "Escape") {
        if (isDragging.value) {
            cancelDrag();
        }
        store.clearSelection();
        return;
    }

    // Arrow keys — nudge selected elements
    // Shift = 10mm, with grid snap use the grid size
    const gridNudge = snapEnabled.value && showGrid.value ? 5 : 1;
    const step = e.shiftKey ? 10 : gridNudge;
    const contentWidth =
        store.page.width - store.page.margin.left - store.page.margin.right;

    const dx = e.key === "ArrowLeft" ? -step : e.key === "ArrowRight" ? step : 0;
    const dy = e.key === "ArrowUp" ? -step : e.key === "ArrowDown" ? step : 0;

    if (dx === 0 && dy === 0) return;
    e.preventDefault();

    for (const id of selIds) {
        const el = findElementById(id);
        if (!el || el.locked) continue;

        const band = getElementBand(id);
        if (!band) continue;

        const bHeight = bandRenderHeight(band);
        const gridS = snapGridSize.value;
        const newX = clamp(snapToGrid(el.x + dx, gridS), 0, contentWidth - el.width);
        const newY = clamp(snapToGrid(el.y + dy, gridS), 0, bHeight - el.height);

        store.moveElement(id, newX, newY);
    }
}

// ── Lifecycle ──────────────────────────────────

onMounted(() => {
    // Start at 100% (actual physical size)
    lockScale();

    document.addEventListener("keydown", onKeyDown);
});

onUnmounted(() => {
    document.removeEventListener("keydown", onKeyDown);

    // Clean up any dangling drag/resize/band-resize state
    teardown();
    document.removeEventListener("mousemove", onBandResizeMouseMove);
    document.removeEventListener("mouseup", onBandResizeMouseUp);

    // Clean up container draw mode listeners
    document.removeEventListener("mousemove", onDrawMouseMove);
    document.removeEventListener("mouseup", onDrawMouseUp);
});

// ── Watchers ───────────────────────────────────

// Reset to 100% when paper dimensions change (e.g., loading a different template)
watch(
    () => [store.page.width, store.page.height],
    () => {
        lockScale();
    },
);

// When bands resize, keep current zoom — just update ruler offset
watch(
    () => store.designPageHeight,
    () => {
        requestAnimationFrame(() => updatePageOffset());
    },
);
</script>
