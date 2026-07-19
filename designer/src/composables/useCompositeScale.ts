import { inject, type Ref } from "vue";

const PX_PER_MM = 96 / 25.4;

/**
 * Provides zoom-aware unit conversion for composite node components.
 *
 * When used inside CompositeCanvas (which `provide`s `compositeScale`),
 * all conversions reflect the current canvas zoom level. When used outside
 * the canvas (property panels, tree navigation), falls back to 100% zoom.
 */
export function useCompositeScale() {
    const scale = inject<Ref<number>>("compositeScale");

    /** Convert mm (model unit) to CSS px at current zoom. */
    function mmToPx(mm: number): number {
        return mm * (scale?.value ?? PX_PER_MM);
    }

    /** Convert mm to a CSS px string, e.g. `"151.18px"`. */
    function mmToPxStr(mm: number): string {
        return `${mmToPx(mm)}px`;
    }

    /** Convert pt (model unit for font sizes) to CSS px at current zoom. */
    function ptToPx(pt: number): number {
        return (pt * 25.4 * (scale?.value ?? PX_PER_MM)) / 72;
    }

    return { scale, mmToPx, mmToPxStr, ptToPx };
}
