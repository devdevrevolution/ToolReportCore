/**
 * Shared helpers for property panels.
 */
export function usePropertyHelpers() {
    /**
     * Parse a dimension string to a positive number, or undefined if invalid/empty.
     */
    function parseDimension(value: string): number | undefined {
        const n = Number(value)
        return Number.isFinite(n) && n >= 0 ? n : undefined
    }

    return { parseDimension }
}
