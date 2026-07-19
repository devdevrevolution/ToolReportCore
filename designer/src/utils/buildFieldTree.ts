// ──────────────────────────────────────────────
// Toolreport Designer — buildFieldTree
// Converts a flat list of DiscoveredField (from
// a single datasource) into a hierarchical tree
// of TreeNode, using the path dot-notation and
// nesting level to determine parent-child
// relationships.
// ──────────────────────────────────────────────

import type { DiscoveredField } from '@/types/designer'

// ── Types ─────────────────────────────────────

export interface TreeNode {
    /** Unique id within the datasource: `${datasourceId}-${path}` */
    id: string
    /** Human-readable display name (e.g. "Customer Email") */
    label: string
    /** Dot-notation path (e.g. "data.address.city") */
    path: string
    /** JSON type: string | number | boolean | array | object | null */
    type: string
    /** Nesting depth from the original field */
    depth: number
    /** Links back to the parent DatasourceConfig */
    datasourceId: string
    /** The original field — used for drag data */
    originalField: DiscoveredField
    /** Child nodes (empty for leaf nodes) */
    children: TreeNode[]
}

// ── Builder ───────────────────────────────────

/**
 * Build a tree from a flat list of discovered fields belonging
 * to a single datasource.
 *
 * Parent-child relationships are determined by path prefix:
 * a field whose path starts with another field's path followed by
 * `.` or `[` becomes a child of that field.
 *
 * Fields are sorted by nesting level (ascending), then
 * alphabetically by path within each level.
 */
export function buildFieldTree(fields: DiscoveredField[]): TreeNode[] {
    if (fields.length === 0) return []

    // Sort by level asc, then path asc for deterministic output
    const sorted = [...fields].sort((a, b) => {
        if (a.level !== b.level) return a.level - b.level
        return a.path.localeCompare(b.path)
    })

    const nodeMap = new Map<string, TreeNode>()
    const roots: TreeNode[] = []

    for (const field of sorted) {
        const node: TreeNode = {
            id: `${field.datasourceId}-${field.path}`,
            label: field.name,
            path: field.path,
            type: field.type,
            depth: field.level,
            datasourceId: field.datasourceId,
            originalField: field,
            children: [],
        }

        nodeMap.set(field.path, node)

        // Find the deepest parent whose path is a proper prefix
        const parentPath = findParentPath(field.path, nodeMap)

        if (parentPath !== null) {
            nodeMap.get(parentPath)!.children.push(node)
        } else {
            roots.push(node)
        }
    }

    // Sort children alphabetically by path within each node
    sortChildren(roots)

    return roots
}

// ── Internal helpers ──────────────────────────

/**
 * Find the longest registered path that is a proper prefix of `path`.
 * A proper prefix means the character immediately after the prefix
 * must be `.` or `[` (to avoid partial key matches like "addr" matching "address").
 */
function findParentPath(path: string, nodeMap: Map<string, TreeNode>): string | null {
    let bestMatch: string | null = null
    let bestLength = 0

    for (const candidatePath of nodeMap.keys()) {
        if (
            path !== candidatePath &&
            path.startsWith(candidatePath) &&
            path.length > candidatePath.length
        ) {
            const nextChar = path.charAt(candidatePath.length)
            if (nextChar === '.' || nextChar === '[') {
                if (candidatePath.length > bestLength) {
                    bestLength = candidatePath.length
                    bestMatch = candidatePath
                }
            }
        }
    }

    return bestMatch
}

/**
 * Recursively sort children by path alphabetically.
 */
function sortChildren(nodes: TreeNode[]): void {
    nodes.sort((a, b) => a.path.localeCompare(b.path))
    for (const node of nodes) {
        if (node.children.length > 0) {
            sortChildren(node.children)
        }
    }
}
