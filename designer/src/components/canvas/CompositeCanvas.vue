<template>
<div class="contents">
    <div
        ref="canvasContainer"
        data-testid="composite-canvas"
        class="relative h-full min-h-[500px] w-full select-none overflow-hidden bg-gray-200"
        @wheel="onCanvasWheel"
        @contextmenu="onContextMenu"
        @click="closeContextMenu"
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

        <!-- Scrollable area -->
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
                <!-- Left panel: band strips -->
                <div
                    class="flex-shrink-0 z-30 border-r border-gray-300 bg-gray-100 pointer-events-none"
                    :style="{ width: LEFT_RULER_WIDTH + 'px' }"
                >
                    <div
                        :style="{
                            height: store.page.margin.top * scale + 'px',
                        }"
                    />
                    <template
                        v-for="(band, idx) in pageBands"
                        :key="'bl-' + band.id"
                    >
                        <div
                            class="flex items-center gap-1 overflow-hidden border-b border-gray-200 px-1"
                            :style="{
                                height: bandRenderHeight(band) * scale + 'px',
                                backgroundColor: bandColor(band.type, 0.08),
                            }"
                        >
                            <div
                                class="h-full w-[3px] flex-shrink-0 rounded-sm"
                                :style="{
                                    backgroundColor: bandColor(band.type),
                                }"
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
                        <div
                            v-if="idx < pageBands.length - 1"
                            class="h-[5px] cursor-s-resize pointer-events-auto hover:bg-purple-400/30"
                            @mousedown.stop="onBandResizeStart($event, band)"
                        />
                    </template>
                </div>

                <!-- Page container -->
                <div class="flex-grow min-w-0">
                    <div
                        ref="pageEl"
                        data-testid="composite-page"
                        class="pdf-page relative mx-auto bg-white shadow-lg"
                        :style="pageStyle"
                        @dragover="onDragOver"
                        @dragleave="onDragLeave"
                        @drop="onDrop"
                    >
                        <!-- Margin overlays -->
                        <template
                            v-for="side in MARGIN_SIDES"
                            :key="'mo-' + side"
                        >
                            <div
                                v-if="store.page.margin[side] > 0"
                                class="absolute pointer-events-none"
                                :style="marginOverlayStyle(side)"
                            />
                        </template>

                        <!-- Margin guide lines -->
                        <template
                            v-for="side in MARGIN_SIDES"
                            :key="'mg-' + side"
                        >
                            <div
                                v-if="store.page.margin[side] > 0"
                                class="absolute pointer-events-none"
                                :style="marginGuideLineStyle(side)"
                            />
                        </template>

                        <!-- Grid overlay -->
                        <svg
                            v-if="showGrid"
                            class="absolute inset-0 pointer-events-none z-0"
                            :width="store.page.width * scale"
                            :height="store.designPageHeight * scale"
                        >
                            <defs>
                                <pattern
                                    id="composite-grid"
                                    :width="gridSize * scale"
                                    :height="gridSize * scale"
                                    patternUnits="userSpaceOnUse"
                                >
                                    <path
                                        :d="`M ${gridSize * scale} 0 L 0 0 0 ${gridSize * scale}`"
                                        fill="none"
                                        stroke="rgba(99,102,241,0.15)"
                                        stroke-width="0.5"
                                    />
                                </pattern>
                            </defs>
                            <rect
                                width="100%"
                                height="100%"
                                fill="url(#composite-grid)"
                            />
                        </svg>

                        <!-- Band containers -->
                        <div
                            v-for="band in pageBands"
                            :key="band.id"
                            class="band-container absolute overflow-hidden"
                            :class="{
                                'ring-1 ring-indigo-400':
                                    band.id === store.selectedBandId,
                            }"
                            :style="bandStyle(band)"
                            @mousedown.stop="onBandMouseDown($event, band)"
                            @dragover.prevent="onBandDragOver($event, band.id)"
                            @dragleave="onBandDragLeave(band.id)"
                            @drop="onBandDrop($event, band.id)"
                        >
                            <!-- Colored left border -->
                            <div
                                class="absolute left-0 top-0 bottom-0"
                                :style="{
                                    width: '3px',
                                    backgroundColor: bandColor(band.type),
                                }"
                            />

                            <!-- Band label badge -->
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
                                class="absolute right-1 top-0.5 rounded bg-emerald-50 px-1 text-[8px] font-semibold leading-tight text-emerald-700"
                            >
                                {{ band.collectionPath }}
                            </span>

                            <!-- Subtle band background -->
                            <div
                                class="absolute inset-0 pointer-events-none"
                                :style="{
                                    backgroundColor: bandColor(band.type, 0.03),
                                }"
                            />

                            <!-- Empty band hint -->
                            <div
                                v-if="compositeRoots(band).length === 0"
                                class="absolute inset-0 flex items-center justify-center text-xs text-gray-400"
                            >
                                Drop components here
                            </div>

                            <!-- Band resize handle -->
                            <div
                                class="absolute bottom-0 left-0 right-0 z-20 h-[5px] cursor-s-resize transition-colors hover:bg-purple-400/30"
                                @mousedown.stop="
                                    onBandResizeStart($event, band)
                                "
                            />

                            <!-- Composite roots (absolutely positioned) -->
                            <div
                                v-for="root in compositeRoots(band)"
                                :key="root.id"
                                class="canvas-root absolute z-10"
                                :class="{
                                    'ring-2 ring-indigo-500':
                                        store.selectedCompositeNodeId ===
                                        root.id,
                                    'cursor-grab': true,
                                }"
                                :style="rootPxStyle(root)"
                                @mousedown.stop="onRootMouseDown($event, root)"
                            >
                                <!-- Node renderer -->
                                <CompositeNodeRenderer :node="root.node" />

                                <!-- Resize handles -->
                                <template
                                    v-if="
                                        store.selectedCompositeNodeId ===
                                        root.id
                                    "
                                >
                                    <div
                                        v-for="handle in RESIZE_HANDLES"
                                        :key="handle.corner"
                                        class="absolute z-10 h-2 w-2 border border-indigo-500 bg-white"
                                        :style="handleStyle(handle.corner)"
                                        @mousedown.stop="
                                            onRootResizeStart(
                                                $event,
                                                root,
                                                handle,
                                            )
                                        "
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zoom controls -->
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
                title="Fit to screen"
            >
                {{ zoomPercent }}%
            </button>
            <button
                class="rounded px-1.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
                @click="zoomIn"
            >
                +
            </button>
        </div>

        <!-- Grid controls -->
        <div
            class="absolute bottom-3 left-1/2 z-40 flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 shadow-sm"
        >
            <button
                class="rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                :class="showGrid ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                title="Toggle grid visibility"
                @click="showGrid = !showGrid"
            >
                <svg class="inline h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M0 4h16M0 8h16M0 12h16M4 0v16M8 0v16M12 0v16" />
                </svg>
            </button>
            <button
                class="rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                :class="snapToGridEnabled ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                title="Toggle snap to grid"
                @click="snapToGridEnabled = !snapToGridEnabled"
            >
                <svg class="inline h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="1" y="1" width="6" height="6" rx="1" />
                    <rect x="9" y="9" width="6" height="6" rx="1" />
                    <path d="M7 4h2M4 7v2M12 7v2M7 12h2" stroke-dasharray="1 1" />
                </svg>
            </button>
            <div class="h-4 w-px bg-gray-200" />
            <span class="text-[10px] text-gray-400">{{ gridSize }}mm</span>
        </div>
    </div>

    <!-- Context menu -->
    <CompositeContextMenu
        v-if="contextMenu"
        :visible="!!contextMenu"
        :x="contextMenu.x"
        :y="contextMenu.y"
        :can-copy="!!store.selectedCompositeNodeId"
        :can-paste="store.hasCompatibleClipboard"
        :can-duplicate="!!store.selectedCompositeNodeId"
        :can-delete="!!store.selectedCompositeNodeId"
        @copy="onContextMenuCopy"
        @paste="onContextMenuPaste"
        @duplicate="onContextMenuDuplicate"
        @delete="onContextMenuDelete"
    />

    <!-- Toast notification -->
    <div
        v-if="toastMessage"
        class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-lg transition-all"
    >
        {{ toastMessage }}
    </div>
</div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, provide } from "vue";
import { useDesignerStore } from "@/stores/designer";
import { useResize } from "@/composables/useResize";
import type {
    ReportBand,
    CompositeRoot,
    CompositeNodeType,
    CompositeNode,
} from "@/types/designer";
import CompositeNodeRenderer, {
    type CompositeDropHandlers,
} from "./CompositeNodeRenderer.vue";
import CompositeContextMenu from "./CompositeContextMenu.vue";

// ── Store ──────────────────────────────────────

const store = useDesignerStore();

// ── Utilities ──────────────────────────────────

/**
 * Infer the collectionPath from a dragged field's path.
 * Mirrors DesignerCanvas.collectionPathFromField.
 */
function collectionPathFromField(field: {
    path: string;
    type: string;
}): string | null {
    if (field.type === "array") {
        // Root-level array: "[]" → "" (root data is the collection)
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

/**
 * Find the band that contains a given composite node (root or nested).
 */
function findBandForNode(nodeId: string): ReportBand | null {
    const bands = store.page.bands;
    if (!bands) return null;

    for (const band of bands) {
        if (!band.children) continue;

        // Check direct roots
        if (band.children.some((r) => r.id === nodeId)) return band;

        // Check nested nodes inside roots
        for (const root of band.children) {
            if (!("node" in root)) continue;
            if (findNodeInTree(root.node as CompositeNode, nodeId)) return band;
        }
    }
    return null;
}

function findNodeInTree(node: CompositeNode, id: string): CompositeNode | null {
    if (node.id === id) return node;
    if (!("children" in node)) return null;
    for (const child of node.children) {
        const found = findNodeInTree(child, id);
        if (found) return found;
    }
    return null;
}

/**
 * Infer and set collectionPath + datasourceId on a band when a collection field is dropped.
 */
function inferCollectionForBand(
    band: ReportBand,
    field: { path: string; type: string; datasourceId?: string },
): void {
    const inferred = collectionPathFromField(field);
    if (inferred === null) return;

    const nextCollectionPath = band.collectionPath ?? inferred;
    if (band.type === "detail" && (nextCollectionPath !== null || field.datasourceId)) {
        store.updateBand(band.id, {
            collectionPath:
                nextCollectionPath !== null
                    ? nextCollectionPath
                    : band.collectionPath ?? null,
            datasourceId: field.datasourceId ?? band.datasourceId ?? null,
        });
    }
}

// ── Constants ──────────────────────────────────

const PX_PER_MM = 96 / 25.4;
const RULER_SIZE = 20;
const LEFT_RULER_WIDTH = 55;
const PADDING = 40;
const DEFAULT_GRID_SIZE = 5; // mm

const MARGIN_SIDES = ["top", "right", "bottom", "left"] as const;
type MarginSide = (typeof MARGIN_SIDES)[number];

// ── Grid state ─────────────────────────────────

const showGrid = ref(true);
const snapToGridEnabled = ref(true);
const gridSize = ref(DEFAULT_GRID_SIZE); // mm

/**
 * Snap a value (mm) to the nearest grid line.
 */
function snapToGrid(value: number): number {
    if (!snapToGridEnabled.value) return value;
    const step = gridSize.value;
    return Math.round(value / step) * step;
}

const BAND_COLORS: Record<string, string> = {
    title: "#3b82f6",
    pageHeader: "#22c55e",
    columnHeader: "#14b8a6",
    detail: "#6366f1",
    columnFooter: "#0ea5e9",
    pageFooter: "#a855f7",
    summary: "#f59e0b",
};

const RESIZE_HANDLES = [
    { corner: "nw" },
    { corner: "n" },
    { corner: "ne" },
    { corner: "e" },
    { corner: "se" },
    { corner: "s" },
    { corner: "sw" },
    { corner: "w" },
] as const;

type ResizeCorner = (typeof RESIZE_HANDLES)[number]["corner"];

// ── Scale / Zoom ───────────────────────────────

const scale = ref(PX_PER_MM);

// ── Drag-drop handlers (provide a los contenedores anidados) ──
// Permite que VBox/HBox dentro del canvas sean drop-targets.
// El drop.stop en CompositeNodeRenderer evita que el evento burbujee
// a la banda — así el drop dentro de un contenedor crea un hijo (flow),
// mientras que el drop en zona vacía de la banda crea un root absoluto.
const hoverContainerId = ref<string | null>(null);

const dropHandlers: CompositeDropHandlers = {
    hoverId: hoverContainerId,
    dragOver(_e: DragEvent, node: CompositeNode) {
        hoverContainerId.value = node.id;
    },
    dragLeave(node: CompositeNode) {
        if (hoverContainerId.value === node.id) hoverContainerId.value = null;
    },
    drop(e: DragEvent, node: CompositeNode) {
        e.preventDefault();
        e.stopPropagation();
        hoverContainerId.value = null;

        const fieldData = e.dataTransfer?.getData("field-path");
        const type = e.dataTransfer?.getData("composite-node-type") as
            | CompositeNodeType
            | undefined;

        if (fieldData) {
            // Field drop into container → create Label with {{ field.path }}
            const field = JSON.parse(fieldData);
            const newId = store.addChildCompositeNodeWithText(
                node.id,
                "Label",
                `{{ ${field.path} }}`,
            );
            if (!newId) {
                console.warn(
                    "[CompositeCanvas] Drop rejected — node is not a container:",
                    node.id,
                );
            }

            // Infer collectionPath on the parent band for collection fields
            const band = findBandForNode(node.id);
            if (band) {
                inferCollectionForBand(band, field);
            }
        } else if (type) {
            // Palette drop into container → existing behavior
            const newId = store.addChildCompositeNode(node.id, type);
            if (!newId) {
                console.warn(
                    "[CompositeCanvas] Drop rejected — node is not a container:",
                    node.id,
                );
            }
        }
    },
};

provide("compositeDropHandlers", dropHandlers);
provide("compositeParentLayout", null);
provide("compositeResizeHandlers", {
    startResize: onChildResizeStart,
});
provide("compositeScale", scale);

// ── Context menu ───────────────────────────────

const contextMenu = ref<{ x: number; y: number } | null>(null);

function onContextMenu(e: MouseEvent) {
    e.preventDefault();
    contextMenu.value = { x: e.clientX, y: e.clientY };
}

function closeContextMenu() {
    contextMenu.value = null;
}

function onContextMenuCopy() {
    if (store.selectedCompositeNodeId) {
        store.copyCompositeRoot(store.selectedCompositeNodeId);
    }
    closeContextMenu();
}

function onContextMenuPaste() {
    const targetBandId = store.selectedBandId ?? undefined;
    const newId = store.pasteCompositeRoot(targetBandId);
    if (!newId && !store.hasCompatibleClipboard) {
        showToast("No se puede pegar: engine distinto");
    }
    closeContextMenu();
}

function onContextMenuDuplicate() {
    if (store.selectedCompositeNodeId) {
        duplicateCompositeRootById(store.selectedCompositeNodeId);
    }
    closeContextMenu();
}

function onContextMenuDelete() {
    if (store.selectedCompositeNodeId) {
        store.removeCompositeNode(store.selectedCompositeNodeId);
    }
    closeContextMenu();
}

// ── Duplicate composite root ────────────────────

function duplicateCompositeRootById(rootId: string) {
    const bands = store.page.bands;
    if (!bands) return;
    for (const band of bands) {
        if (!band.children) continue;
        const idx = band.children.findIndex(
            (r) => "node" in r && r.id === rootId,
        );
        if (idx === -1) continue;

        const original = band.children[idx] as CompositeRoot;
        const cloned = JSON.parse(JSON.stringify(original));
        function reId(node: any) {
            node.id = crypto.randomUUID();
            if (node.node) reId(node.node);
            if (Array.isArray(node.children)) node.children.forEach(reId);
        }
        reId(cloned);
        cloned.x = Math.max(0, snapToGrid(cloned.x + gridSize.value));
        cloned.y = Math.max(0, snapToGrid(cloned.y + gridSize.value));

        store.pushHistory();
        band.children.push(cloned);
        store.selectedCompositeNodeId = cloned.id;
        store.isDirty = true;
        return;
    }
}

// ── Toast notification ─────────────────────────

const toastMessage = ref<string | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | null = null;

function showToast(msg: string) {
    toastMessage.value = msg;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastMessage.value = null }, 3000);
}

// ── Keyboard shortcuts ─────────────────────────

function onKeyDown(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement).tagName;
    const isInputFocused = tag === "INPUT" || tag === "TEXTAREA";
    const isMod = e.metaKey || e.ctrlKey;

    if (isInputFocused) return;

    // Ctrl/Cmd + C → copy selected composite root
    if (isMod && e.key === "c" && store.selectedCompositeNodeId) {
        e.preventDefault();
        store.copyCompositeRoot(store.selectedCompositeNodeId);
        return;
    }

    // Ctrl/Cmd + V → paste from clipboard
    if (isMod && e.key === "v") {
        e.preventDefault();
        const targetBandId = store.selectedBandId ?? undefined;
        const newId = store.pasteCompositeRoot(targetBandId);
        if (!newId && !store.hasCompatibleClipboard) {
            showToast("No se puede pegar: engine distinto");
        }
        return;
    }

    // Ctrl/Cmd + D → duplicate
    if (isMod && e.key === "d" && store.selectedCompositeNodeId) {
        e.preventDefault();
        duplicateCompositeRootById(store.selectedCompositeNodeId);
        return;
    }

    // Escape → deselect
    if (e.key === "Escape") {
        store.selectedCompositeNodeId = null;
        contextMenu.value = null;
        return;
    }

    // Delete/Backspace → remove selected
    if ((e.key === "Delete" || e.key === "Backspace") && store.selectedCompositeNodeId) {
        e.preventDefault();
        store.removeCompositeNode(store.selectedCompositeNodeId);
        return;
    }

    // Arrow keys → nudge selected root by 1mm
    if (["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"].includes(e.key) && store.selectedCompositeRoot) {
        e.preventDefault();
        const root = store.selectedCompositeRoot;
        const dx = e.key === "ArrowLeft" ? -1 : e.key === "ArrowRight" ? 1 : 0;
        const dy = e.key === "ArrowUp" ? -1 : e.key === "ArrowDown" ? 1 : 0;
        store.pushHistory();
        store.moveCompositeRoot(root.id, root.x + dx, root.y + dy);
        return;
    }
}

const zoomPercent = computed(() => Math.round((scale.value / PX_PER_MM) * 100));

const ZOOM_PRESETS = [100, 125, 150, 200, 250, 300];

function setZoom(newScale: number): void {
    scale.value = Math.max(PX_PER_MM, newScale);
    requestAnimationFrame(() => updatePageOffset());
}

function pctToScale(pct: number): number {
    return (pct / 100) * PX_PER_MM;
}

function recalcScale(): void {
    const el = canvasContainer.value;
    if (!el) return;
    const cw = el.clientWidth;
    const ch = el.clientHeight;
    if (cw === 0 || ch === 0) return;

    const availableW = cw - LEFT_RULER_WIDTH;
    const availableH = ch - RULER_SIZE;
    const scaleX = (availableW - PADDING * 2) / store.page.width;
    const scaleY = (availableH - PADDING * 2) / store.designPageHeight;
    setZoom(Math.min(scaleX, scaleY));
}

function zoomIn(): void {
    const curPct = (scale.value / PX_PER_MM) * 100;
    const next = ZOOM_PRESETS.find((p) => p > curPct + 0.5);
    if (next !== undefined) setZoom(pctToScale(next));
}

function zoomOut(): void {
    const curPct = (scale.value / PX_PER_MM) * 100;
    const prev = [...ZOOM_PRESETS].reverse().find((p) => p < curPct - 0.5);
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

// ── Refs ───────────────────────────────────────

const canvasContainer = ref<HTMLElement | null>(null);
const scrollAreaEl = ref<HTMLElement | null>(null);
const pageEl = ref<HTMLElement | null>(null);
const topRulerSvg = ref<SVGSVGElement | null>(null);
const pageOffsetX = ref(0);
const pageOffsetY = ref(0);

// ── Drag-over state (palette drop) ────────────

const dragOverBandId = ref<string | null>(null);

// ── Page style ─────────────────────────────────

const pageStyle = computed(() => ({
    width: `${store.page.width * scale.value}px`,
    height: `${store.designPageHeight * scale.value}px`,
}));

// ── Ruler ticks ────────────────────────────────

function rulerStep(): number {
    const s = scale.value;
    if (s >= 0.8) return 10;
    if (s >= 0.4) return 20;
    if (s >= 0.2) return 50;
    return 100;
}

interface RulerTick {
    mm: number;
    x: number;
    len: number;
    label: string | null;
}

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
            ticks.push({ mm, x, len: 14, label: String(mm) });
        } else if (isMid && step > 5) {
            ticks.push({ mm, x, len: 10, label: null });
        } else if (step <= 10) {
            ticks.push({ mm, x, len: 6, label: null });
        }
    }
    return ticks;
});

function updatePageOffset(): void {
    if (!canvasContainer.value || !pageEl.value) return;
    const canvasRect = canvasContainer.value.getBoundingClientRect();
    const pageRect = pageEl.value.getBoundingClientRect();
    pageOffsetX.value = pageRect.left - canvasRect.left;
    pageOffsetY.value = pageRect.top - canvasRect.top;
}

// ── Margin helpers ─────────────────────────────

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
                backgroundColor: "rgba(99,102,241,0.4)",
            };
        case "bottom":
            return {
                left: `${ml}px`,
                top: `${mt + ch}px`,
                width: `${cw}px`,
                height: "1px",
                backgroundColor: "rgba(99,102,241,0.4)",
            };
        case "left":
            return {
                left: `${ml}px`,
                top: `${mt}px`,
                width: "1px",
                height: `${ch}px`,
                backgroundColor: "rgba(99,102,241,0.4)",
            };
        case "right":
            return {
                left: `${ml + cw}px`,
                top: `${mt}px`,
                width: "1px",
                height: `${ch}px`,
                backgroundColor: "rgba(99,102,241,0.4)",
            };
    }
}

// ── Band helpers ───────────────────────────────

const pageBands = computed<ReportBand[]>(() => {
    return (store.page.bands ?? []).filter((b) => b.enabled !== false);
});

function bandColor(type: string, alpha: number = 1): string {
    const hex = BAND_COLORS[type] ?? "#6b7280";
    if (alpha === 1) return hex;
    const r = Number.parseInt(hex.slice(1, 3), 16);
    const g = Number.parseInt(hex.slice(3, 5), 16);
    const b = Number.parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function bandYPos(band: ReportBand): number {
    let y = store.page.margin.top;
    for (const b of pageBands.value) {
        if (b.id === band.id) return y;
        y += b.height;
    }
    return y;
}

function bandRenderHeight(band: ReportBand): number {
    return band.height;
}

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

function compositeRoots(band: ReportBand): CompositeRoot[] {
    return (band.children ?? []).filter((c): c is CompositeRoot => 'node' in c)
}

// ── Root rendering ─────────────────────────────

/**
 * Convert a CompositeRoot's model coords (mm) to pixel styles.
 * Position is relative to the band container (which is itself
 * positioned at the content area origin).
 */
function rootPxStyle(root: CompositeRoot): Record<string, string | undefined> {
    const s = scale.value;
    const styles: Record<string, string | undefined> = {
        left: `${root.x * s}px`,
        top: `${root.y * s}px`,
    };

    if (root.width !== undefined) styles.width = `${root.width * s}px`;
    if (root.height !== undefined) styles.height = `${root.height * s}px`;

    return styles;
}

// ── Handle style ───────────────────────────────

function handleStyle(corner: ResizeCorner): Record<string, string> {
    const positions: Record<ResizeCorner, { [k: string]: string }> = {
        nw: { left: "-4px", top: "-4px", cursor: "nw-resize" },
        n: { left: "50%", top: "-4px", marginLeft: "-4px", cursor: "n-resize" },
        ne: { right: "-4px", top: "-4px", cursor: "ne-resize" },
        e: { right: "-4px", top: "50%", marginTop: "-4px", cursor: "e-resize" },
        se: { right: "-4px", bottom: "-4px", cursor: "se-resize" },
        s: {
            left: "50%",
            bottom: "-4px",
            marginLeft: "-4px",
            cursor: "s-resize",
        },
        sw: { left: "-4px", bottom: "-4px", cursor: "sw-resize" },
        w: { left: "-4px", top: "50%", marginTop: "-4px", cursor: "w-resize" },
    };
    return positions[corner];
}

// ── Band interaction ───────────────────────────

function onBandMouseDown(e: MouseEvent, band: ReportBand): void {
    store.selectBand(band.id);
    store.selectCompositeNode(null);
}

// ── Band resize (height drag) ──────────────────

let bandResizeId: string | null = null;
let bandResizeOrigHeight = 0;

const { onResizeStart: onBandResizeRaw } = useResize({
    direction: 'vertical',
    scale,
    apply: ({ dy }) => {
        if (!bandResizeId) return;
        const newHeight = Math.max(5, bandResizeOrigHeight + dy);
        store.setBandHeight(bandResizeId, Math.round(newHeight));
    },
});

function onBandResizeStart(e: MouseEvent, band: ReportBand): void {
    bandResizeId = band.id;
    bandResizeOrigHeight = band.height;
    onBandResizeRaw(e);
}

// ── Palette drop onto band ────────────────────

function onDragOver(e: DragEvent): void {
    if (e.dataTransfer) e.dataTransfer.dropEffect = "copy";
}

function onDragLeave(): void {}

function onDrop(e: DragEvent): void {
    // page-level drop — ignored, drops happen on bands
}

function onBandDragOver(e: DragEvent, bandId: string): void {
    if (e.dataTransfer) e.dataTransfer.dropEffect = "copy";
    dragOverBandId.value = bandId;
}

function onBandDragLeave(bandId: string): void {
    if (dragOverBandId.value === bandId) {
        dragOverBandId.value = null;
    }
}

function onBandDrop(e: DragEvent, bandId: string): void {
    e.preventDefault();
    dragOverBandId.value = null;

    const fieldData = e.dataTransfer?.getData("field-path");
    const type = e.dataTransfer?.getData("composite-node-type");

    // Compute drop position relative to band content origin (mm)
    const band = store.page.bands?.find((b) => b.id === bandId);
    if (!band) return;
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const xMm = (e.clientX - rect.left) / scale.value;
    const yMm = (e.clientY - rect.top) / scale.value;

    let rootId: string;

    if (fieldData) {
        // Field drop → create Label with {{ field.path }} binding
        const field = JSON.parse(fieldData);
        rootId = store.addCompositeNodeWithText(
            bandId,
            "Label",
            `{{ ${field.path} }}`,
        );

        // Infer collectionPath on the band for collection fields
        inferCollectionForBand(band, field);
    } else if (type) {
        // Palette drop → existing behavior
        rootId = store.addCompositeNode(bandId, type as CompositeNodeType);
    } else {
        return;
    }

    // Position the root at the drop point (snapped to grid)
    store.updateCompositeRoot(rootId, {
        x: Math.max(0, snapToGrid(xMm)),
        y: Math.max(0, snapToGrid(yMm)),
    });
}

// ── Root drag (absolute positioning) ───────────

let dragRoot: CompositeRoot | null = null;
let dragStartClientX = 0;
let dragStartClientY = 0;
let dragOrigX = 0;
let dragOrigY = 0;

function onRootMouseDown(e: MouseEvent, root: CompositeRoot): void {
    dragRoot = root;
    dragStartClientX = e.clientX;
    dragStartClientY = e.clientY;
    dragOrigX = root.x;
    dragOrigY = root.y;

    store.selectCompositeNode(root.id);
    store.pushHistory();

    document.addEventListener("mousemove", onRootDragMove);
    document.addEventListener("mouseup", onRootDragEnd);
}

function onRootDragMove(e: MouseEvent): void {
    if (!dragRoot) return;
    const dx = (e.clientX - dragStartClientX) / scale.value;
    const dy = (e.clientY - dragStartClientY) / scale.value;
    store.moveCompositeRoot(
        dragRoot.id,
        snapToGrid(dragOrigX + dx),
        snapToGrid(dragOrigY + dy),
    );
}

function onRootDragEnd(): void {
    dragRoot = null;
    document.removeEventListener("mousemove", onRootDragMove);
    document.removeEventListener("mouseup", onRootDragEnd);
}

// ── Root resize ────────────────────────────────

let resizeRoot: CompositeRoot | null = null;
let resizeCorner: ResizeCorner | null = null;
let resizeOrigX = 0;
let resizeOrigY = 0;
let resizeOrigW = 0;
let resizeOrigH = 0;

const { onResizeStart: onRootResizeRaw } = useResize({
    direction: 'both',
    scale,
    apply: ({ dx, dy }) => {
        if (!resizeRoot || !resizeCorner) return;

        let newX = resizeOrigX;
        let newY = resizeOrigY;
        let newW = resizeOrigW;
        let newH = resizeOrigH;

        const c = resizeCorner;
        if (c.includes("e")) newW = Math.max(5, resizeOrigW + dx);
        if (c.includes("w")) {
            newW = Math.max(5, resizeOrigW - dx);
            newX = resizeOrigX + (resizeOrigW - newW);
        }
        if (c.includes("s")) newH = Math.max(5, resizeOrigH + dy);
        if (c.includes("n")) {
            newH = Math.max(5, resizeOrigH - dy);
            newY = resizeOrigY + (resizeOrigH - newH);
        }

        store.resizeCompositeRoot(
            resizeRoot.id,
            snapToGrid(newX),
            snapToGrid(newY),
            snapToGrid(newW),
            snapToGrid(newH),
        );
    },
    onEnd: () => {
        resizeRoot = null;
        resizeCorner = null;
    },
});

function onRootResizeStart(
    e: MouseEvent,
    root: CompositeRoot,
    handle: { corner: ResizeCorner },
): void {
    resizeRoot = root;
    resizeCorner = handle.corner;
    resizeOrigX = root.x;
    resizeOrigY = root.y;
    resizeOrigW = root.width ?? 40;
    resizeOrigH = root.height ?? 20;
    store.pushHistory();
    onRootResizeRaw(e);
}

// ── Nested child resize ────────────────────────

let childResizeNodeId: string | null = null;
let childResizeDimension: "width" | "height" | null = null;
let childResizeDirection: 1 | -1 = 1;
let childResizeOrigValue = 0;

function getNodeDimension(
    node: CompositeNode,
    dimension: "width" | "height",
): number {
    if (dimension === "width") {
        if (node.type === "Shape") return (node as { w?: number }).w ?? 40;
        return (node as { width?: number }).width ?? 40;
    }
    if (node.type === "Shape") return (node as { h?: number }).h ?? 20;
    return (node as { height?: number }).height ?? 20;
}

const { onResizeStart: onChildResizeRaw } = useResize({
    direction: 'both',
    scale,
    apply: ({ dx, dy }) => {
        if (!childResizeNodeId || !childResizeDimension) return;
        const delta = (childResizeDimension === "width" ? dx : dy) * childResizeDirection;
        const value = Math.max(5, snapToGrid(childResizeOrigValue + delta));
        store.resizeCompositeNode(childResizeNodeId, childResizeDimension, value);
    },
    onEnd: () => {
        childResizeNodeId = null;
        childResizeDimension = null;
    },
});

function onChildResizeStart(
    e: MouseEvent,
    payload: { nodeId: string; dimension: "width" | "height"; direction: 1 | -1 },
): void {
    const node = store.selectedCompositeNode;
    if (!node || (node as CompositeNode).id !== payload.nodeId) return;

    childResizeNodeId = payload.nodeId;
    childResizeDimension = payload.dimension;
    childResizeDirection = payload.direction;
    childResizeOrigValue = getNodeDimension(node as CompositeNode, payload.dimension);
    store.pushHistory();
    onChildResizeRaw(e);
}

// ── Lifecycle ──────────────────────────────────

onMounted(() => {
    recalcScale();
    window.addEventListener("resize", recalcScale);
    document.addEventListener("keydown", onKeyDown);
});

onUnmounted(() => {
    window.removeEventListener("resize", recalcScale);
    document.removeEventListener("keydown", onKeyDown);
    document.removeEventListener("mousemove", onRootDragMove);
    document.removeEventListener("mouseup", onRootDragEnd);
    // band/root/child resize cleanup handled by useResize composable
});
</script>

<style scoped>
.pdf-page {
    font-family:
        system-ui,
        -apple-system,
        sans-serif;
}
</style>

