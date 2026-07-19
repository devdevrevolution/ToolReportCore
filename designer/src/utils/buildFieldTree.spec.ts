// ──────────────────────────────────────────────
// Toolreport Designer — buildFieldTree Unit Tests
// ──────────────────────────────────────────────

import { describe, it, expect } from 'vitest'
import { buildFieldTree } from './buildFieldTree'
import type { TreeNode } from './buildFieldTree'
import type { DiscoveredField } from '@/types/designer'

// ── Helpers ──────────────────────────────────

function field(
    name: string,
    path: string,
    type: string,
    level: number,
    datasourceId = 'ds-1',
): DiscoveredField {
    return { name, path, type, level, datasourceId }
}

/** Collect all node labels in DFS order for snapshot comparison. */
function dfsLabels(nodes: TreeNode[]): string[] {
    const result: string[] = []
    for (const n of nodes) {
        result.push(n.label)
        if (n.children.length > 0) {
            result.push(...dfsLabels(n.children).map(c => `  ${c}`))
        }
    }
    return result
}

// ── Tests ────────────────────────────────────

describe('buildFieldTree', () => {
    it('returns empty array for empty input', () => {
        expect(buildFieldTree([])).toEqual([])
    })

    it('returns flat root nodes when fields have no hierarchy', () => {
        const fields = [
            field('Email', 'email', 'string', 0),
            field('Name', 'name', 'string', 0),
        ]
        const tree = buildFieldTree(fields)
        expect(tree).toHaveLength(2)
        expect(tree[0].label).toBe('Email')
        expect(tree[1].label).toBe('Name')
        expect(tree[0].children).toHaveLength(0)
        expect(tree[1].children).toHaveLength(0)
    })

    it('nests children under their parent object', () => {
        const fields = [
            field('Email', 'email', 'string', 0),
            field('Address', 'address', 'object', 0),
            field('City', 'address.city', 'string', 1),
            field('Zip', 'address.zip', 'string', 1),
        ]
        const tree = buildFieldTree(fields)
        expect(tree).toHaveLength(2)

        // email — leaf
        expect(tree[0].label).toBe('Address')
        expect(tree[0].children).toHaveLength(2)
        expect(tree[0].children[0].label).toBe('City')
        expect(tree[0].children[1].label).toBe('Zip')

        // address — leaf
        expect(tree[1].label).toBe('Email')
        expect(tree[1].children).toHaveLength(0)
    })

    it('nests children under array parents', () => {
        const fields = [
            field('Name', 'name', 'string', 0),
            field('Orders', 'orders', 'array', 0),
            field('Id', 'orders[].id', 'number', 1),
            field('Total', 'orders[].total', 'number', 1),
        ]
        const tree = buildFieldTree(fields)
        expect(tree).toHaveLength(2)

        // orders — has children
        const orders = tree[0]
        expect(orders.label).toBe('Name')

        const name = tree[1]
        expect(name.label).toBe('Orders')
        expect(name.children).toHaveLength(2)
        expect(name.children[0].label).toBe('Id')
        expect(name.children[1].label).toBe('Total')
    })

    it('handles multi-level nesting', () => {
        const fields = [
            field('Data', 'data', 'object', 0),
            field('User', 'data.user', 'object', 1),
            field('Name', 'data.user.name', 'string', 2),
            field('Email', 'data.user.email', 'string', 2),
        ]
        const tree = buildFieldTree(fields)
        expect(tree).toHaveLength(1)

        const data = tree[0]
        expect(data.label).toBe('Data')
        expect(data.children).toHaveLength(1)

        const user = data.children[0]
        expect(user.label).toBe('User')
        expect(user.children).toHaveLength(2)
        expect(user.children[0].label).toBe('Email')
        expect(user.children[1].label).toBe('Name')
    })

    it('handles empty arrays (no children)', () => {
        const fields = [
            field('Name', 'name', 'string', 0),
            field('Tags', 'tags', 'array', 0),
        ]
        const tree = buildFieldTree(fields)
        expect(tree).toHaveLength(2)
        expect(tree[0].children).toHaveLength(0) // Name
        expect(tree[1].children).toHaveLength(0) // Tags (no children)
    })

    it('preserves originalField reference for drag data', () => {
        const original = field('Email', 'email', 'string', 0)
        const tree = buildFieldTree([original])
        expect(tree[0].originalField).toBe(original)
        expect(tree[0].originalField.path).toBe('email')
    })

    it('sorts children alphabetically by path', () => {
        const fields = [
            field('Data', 'data', 'object', 0),
            field('ZField', 'data.zfield', 'string', 1),
            field('AField', 'data.afield', 'string', 1),
            field('MField', 'data.mfield', 'string', 1),
        ]
        const tree = buildFieldTree(fields)
        expect(tree).toHaveLength(1)
        const children = tree[0].children
        expect(children).toHaveLength(3)
        expect(children[0].label).toBe('AField')
        expect(children[1].label).toBe('MField')
        expect(children[2].label).toBe('ZField')
    })

    it('handles mixed primitive, object, and array siblings', () => {
        const fields = [
            field('Name', 'name', 'string', 0),
            field('Address', 'address', 'object', 0),
            field('City', 'address.city', 'string', 1),
            field('Orders', 'orders', 'array', 0),
            field('Id', 'orders[].id', 'number', 1),
        ]
        const tree = buildFieldTree(fields)

        // Root order: address, name, orders (sorted by path)
        expect(tree).toHaveLength(3)
        expect(tree[0].label).toBe('Address')
        expect(tree[0].children).toHaveLength(1)
        expect(tree[1].label).toBe('Name')
        expect(tree[1].children).toHaveLength(0)
        expect(tree[2].label).toBe('Orders')
        expect(tree[2].children).toHaveLength(1)
    })
})
