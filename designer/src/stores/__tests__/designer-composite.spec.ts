// ──────────────────────────────────────────────
// Toolreport Designer — Composite hybrid model tests
// ──────────────────────────────────────────────

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore, isCompositeRoot } from '@/stores/designer'
import type { CompositeRoot, VBoxNode, HBoxNode, LabelNode, ShapeNode, ImageNode } from '@/types/designer'

// Mock localStorage for Node test environment
const localStorageMock = (() => {
    let store: Record<string, string> = {}
    return {
        getItem: (key: string) => store[key] ?? null,
        setItem: (key: string, value: string) => { store[key] = value },
        removeItem: (key: string) => { delete store[key] },
        clear: () => { store = {} },
    }
})()
vi.stubGlobal('localStorage', localStorageMock)

describe('Composite hybrid model', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        localStorageMock.clear()
    })

    describe('addCompositeNode (band root, absolute)', () => {
        it('creates a CompositeRoot in band.children with x/y defaults', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'Label')

            const band = store.page.bands!.find(b => b.id === 'detail')!
            expect(band.children).toHaveLength(1)
            const root = band.children![0] as CompositeRoot
            expect(root.id).toBe(rootId)
            expect(root.x).toBe(10)
            expect(root.y).toBe(10)
            expect(root.node.type).toBe('Label')
        })

        it('selects the new root and clears element selection', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            expect(store.selectedCompositeNodeId).toBe(rootId)
            expect(store.selectedElementIds).toEqual([])
            expect(store.selectedBandId).toBe('detail')
        })

        it('creates VBox roots with default width and height', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.id).toBe(rootId)
            expect(root.width).toBe(80)
            expect(root.height).toBe(60)
            expect(root.node.type).toBe('VBox')
            expect((root.node as VBoxNode).width).toBe(80)
            expect((root.node as VBoxNode).height).toBe(60)
        })

        it('creates HBox roots with default width and height', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')

            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.id).toBe(rootId)
            expect(root.width).toBe(100)
            expect(root.height).toBe(30)
            expect(root.node.type).toBe('HBox')
            expect((root.node as HBoxNode).width).toBe(100)
            expect((root.node as HBoxNode).height).toBe(30)
        })
    })

    describe('addChildCompositeNode (nested flow)', () => {
        it('adds a child inside a VBox root when the parent is a container', () => {
            const store = useDesignerStore()
            // Create a VBox root
            const vBoxRootId = store.addCompositeNode('detail', 'VBox')
            const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const vBoxNode = vBoxRoot.node as { type: string; children: unknown[] }

            // Drop a Label inside the VBox
            const childId = store.addChildCompositeNode(vBoxRoot.node.id, 'Label')
            expect(childId).not.toBeNull()
            expect(vBoxNode.children).toHaveLength(1)
            expect((vBoxNode.children[0] as { type: string }).type).toBe('Label')
        })

        it('returns null when the parent is a leaf (Label)', () => {
            const store = useDesignerStore()
            const labelRootId = store.addCompositeNode('detail', 'Label')
            const labelRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            const result = store.addChildCompositeNode(labelRoot.node.id, 'Label')
            expect(result).toBeNull()
        })

        it('returns null when the parent id does not exist', () => {
            const store = useDesignerStore()
            const result = store.addChildCompositeNode('nonexistent-id', 'Label')
            expect(result).toBeNull()
        })
    })

    describe('moveCompositeRoot (absolute drag, clamped)', () => {
        it('moves a root within the band content area', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'Label')

            store.moveCompositeRoot(rootId, 30, 20)
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.x).toBe(30)
            expect(root.y).toBe(20)
        })

        it('clamps negative coordinates to 0', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'Label')

            store.moveCompositeRoot(rootId, -50, -10)
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.x).toBe(0)
            expect(root.y).toBe(0)
        })
    })

    describe('resizeCompositeRoot', () => {
        it('sets width and height with clamping', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            store.resizeCompositeRoot(rootId, 0, 0, 100, 50)
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.width).toBe(100)
            expect(root.height).toBe(50)
        })

        it('enforces a minimum size of 5mm', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            store.resizeCompositeRoot(rootId, 0, 0, 1, 1)
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.width).toBe(5)
            expect(root.height).toBe(5)
        })

        it('syncs resized dimensions to the inner VBox node', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            store.resizeCompositeRoot(rootId, 5, 5, 120, 90)
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.width).toBe(120)
            expect(root.height).toBe(90)
            expect((root.node as VBoxNode).width).toBe(120)
            expect((root.node as VBoxNode).height).toBe(90)
        })

        it('syncs resized dimensions to the inner HBox node', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')

            store.resizeCompositeRoot(rootId, 5, 5, 140, 45)
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.width).toBe(140)
            expect(root.height).toBe(45)
            expect((root.node as HBoxNode).width).toBe(140)
            expect((root.node as HBoxNode).height).toBe(45)
        })
    })

    describe('removeCompositeNode', () => {
        it('removes a root by id from band.children', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'Label')

            store.removeCompositeNode(rootId)
            const band = store.page.bands!.find(b => b.id === 'detail')!
            expect(band.children).toHaveLength(0)
            expect(store.selectedCompositeNodeId).toBeNull()
        })

        it('removes an inner node from a container tree', () => {
            const store = useDesignerStore()
            const vBoxRootId = store.addCompositeNode('detail', 'VBox')
            const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(vBoxRoot.node.id, 'Label')!

            store.removeCompositeNode(childId)
            const vBoxNode = vBoxRoot.node as { type: string; children: unknown[] }
            expect(vBoxNode.children).toHaveLength(0)
        })
    })

    describe('legacy migration on loadTemplate', () => {
        it('migrates band.content VBox with children to band.children roots', () => {
            const store = useDesignerStore()
            store.setEngine('pdf-engine')

            const legacyContent = {
                type: 'VBox',
                id: 'legacy-root',
                children: [
                    { type: 'Label', id: 'lbl-1', text: 'Hello' },
                    { type: 'Label', id: 'lbl-2', text: 'World' },
                ],
            }

            const template = {
                id: 1,
                name: 'Legacy',
                slug: 'legacy',
                description: null,
                page: {
                    bands: [
                        {
                            id: 'detail',
                            type: 'detail',
                            anchor: 'fill',
                            label: 'Detail',
                            height: 120,
                            enabled: true,
                            elements: [],
                            content: legacyContent,
                        },
                    ],
                },
                config: {},
                is_active: true,
                element_count: 0,
                created_at: '',
                updated_at: '',
                engine: 'pdf-engine',
            }

            store.loadTemplate(template as never)

            const band = store.page.bands!.find(b => b.id === 'detail')!
            expect(band.children).toHaveLength(2)
            expect(band.content).toBeUndefined()
            expect((band.children![0] as CompositeRoot).node.type).toBe('Label')
            expect((band.children![1] as CompositeRoot).node.type).toBe('Label')
            // Stacked vertically
            expect(band.children![0].y).toBe(0)
            expect(band.children![1].y).toBeGreaterThan(0)
        })

        it('wraps a legacy leaf content as a single root at origin', () => {
            const store = useDesignerStore()
            store.setEngine('pdf-engine')

            const template = {
                id: 2,
                name: 'Legacy leaf',
                slug: 'legacy-leaf',
                description: null,
                page: {
                    bands: [
                        {
                            id: 'detail',
                            type: 'detail',
                            anchor: 'fill',
                            label: 'Detail',
                            height: 120,
                            enabled: true,
                            elements: [],
                            content: { type: 'Shape', id: 'shape-1', shapeType: 'rect', w: 40, h: 20 },
                        },
                    ],
                },
                config: {},
                is_active: true,
                element_count: 0,
                created_at: '',
                updated_at: '',
                engine: 'pdf-engine',
            }

            store.loadTemplate(template as never)

            const band = store.page.bands!.find(b => b.id === 'detail')!
            expect(band.children).toHaveLength(1)
            expect(band.content).toBeUndefined()
            expect(band.children![0].x).toBe(0)
            expect(band.children![0].y).toBe(0)
            expect((band.children![0] as CompositeRoot).node.type).toBe('Shape')
        })
    })

    describe('selectCompositeNode', () => {
        it('selects a nested child inside a container', () => {
            const store = useDesignerStore()
            const vBoxRootId = store.addCompositeNode('detail', 'VBox')
            const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(vBoxRoot.node.id, 'Label')!

            store.selectCompositeNode(childId)

            expect(store.selectedCompositeNodeId).toBe(childId)
            expect(store.selectedCompositeNode?.id).toBe(childId)
            expect((store.selectedCompositeNode as LabelNode).type).toBe('Label')
        })
    })

    describe('resizeCompositeNode', () => {
        it('updates the width field of a Label inside an HBox', () => {
            const store = useDesignerStore()
            const hBoxRootId = store.addCompositeNode('detail', 'HBox')
            const hBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(hBoxRoot.node.id, 'Label')!

            store.resizeCompositeNode(childId, 'width', 55)

            const label = (hBoxRoot.node as HBoxNode).children[0] as LabelNode
            expect(label.width).toBe(55)
        })

        it('updates the h field of a Shape inside a VBox', () => {
            const store = useDesignerStore()
            const vBoxRootId = store.addCompositeNode('detail', 'VBox')
            const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(vBoxRoot.node.id, 'Shape')!

            store.resizeCompositeNode(childId, 'height', 42)

            const shape = (vBoxRoot.node as VBoxNode).children[0] as ShapeNode
            expect(shape.h).toBe(42)
        })

        it('clamps nested resizes to a minimum of 5mm', () => {
            const store = useDesignerStore()
            const hBoxRootId = store.addCompositeNode('detail', 'HBox')
            const hBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(hBoxRoot.node.id, 'Shape')!

            store.resizeCompositeNode(childId, 'width', 2)

            const shape = (hBoxRoot.node as HBoxNode).children[0] as ShapeNode
            expect(shape.w).toBe(5)
        })

        it('propagates VBox width change to Image child', () => {
            const store = useDesignerStore()
            const vBoxRootId = store.addCompositeNode('detail', 'VBox')
            const vBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            store.addChildCompositeNode(vBoxRoot.node.id, 'Image')!

            store.resizeCompositeRoot(vBoxRootId, 0, 0, 60, 60)

            const image = (vBoxRoot.node as VBoxNode).children[0] as ImageNode
            expect(image.width).toBe(60)
        })

        it('propagates HBox height change to Image child', () => {
            const store = useDesignerStore()
            const hBoxRootId = store.addCompositeNode('detail', 'HBox')
            const hBoxRoot = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            store.addChildCompositeNode(hBoxRoot.node.id, 'Image')!

            store.resizeCompositeRoot(hBoxRootId, 0, 0, 100, 35)

            const image = (hBoxRoot.node as HBoxNode).children[0] as ImageNode
            expect(image.height).toBe(35)
        })
    })

    describe('selectedCompositeRoot getter', () => {
        it('returns the CompositeRoot when a root is selected', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            store.selectCompositeNode(rootId)

            const root = store.selectedCompositeRoot
            expect(root).not.toBeNull()
            expect(root!.id).toBe(rootId)
            expect(root!.node.type).toBe('VBox')
        })

        it('returns null when an inner node is selected', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(root.node.id, 'Label')!
            store.selectCompositeNode(childId)

            expect(store.selectedCompositeRoot).toBeNull()
        })

        it('returns null when nothing is selected', () => {
            const store = useDesignerStore()
            expect(store.selectedCompositeRoot).toBeNull()
        })
    })

    describe('selectedCompositeInnerNode getter', () => {
        it('returns root.node when a root is selected', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            store.selectCompositeNode(rootId)

            const inner = store.selectedCompositeInnerNode
            expect(inner).not.toBeNull()
            expect(inner!.type).toBe('VBox')
        })

        it('returns the inner node directly when a child is selected', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(root.node.id, 'Label')!
            store.selectCompositeNode(childId)

            const inner = store.selectedCompositeInnerNode
            expect(inner).not.toBeNull()
            expect(inner!.type).toBe('Label')
            expect(inner!.id).toBe(childId)
        })
    })

    describe('updateCompositeNode on root id', () => {
        it('updates root.x when patch contains x', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            store.updateCompositeNode(rootId, { x: 42 })

            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.x).toBe(42)
        })

        it('updates root.width when patch contains width', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            store.updateCompositeNode(rootId, { width: 150 })

            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.width).toBe(150)
        })

        it('does not touch inner node when updating root', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')

            store.updateCompositeNode(rootId, { width: 200, height: 100 })

            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            expect(root.width).toBe(200)
            expect(root.height).toBe(100)
            // Inner node stays at defaults
            expect((root.node as VBoxNode).width).toBe(80)
            expect((root.node as VBoxNode).height).toBe(60)
        })
    })

    describe('resizeCompositeNode — parent-container constraints', () => {
        it('clamps child width to parent HBox width (single child)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(root.node.id, 'Label')!

            // HBox default width = 100, try to resize Label to 150
            store.resizeCompositeNode(childId, 'width', 150)

            const label = (root.node as HBoxNode).children[0] as LabelNode
            expect(label.width).toBe(100) // clamped to parent width
        })

        it('clamps child height to parent VBox height (single child)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            // VBox default height = 60, try to resize Shape to 100
            store.resizeCompositeNode(childId, 'height', 100)

            const shape = (root.node as VBoxNode).children[0] as ShapeNode
            expect(shape.h).toBe(60) // clamped to parent height
        })

        it('clamps child to remaining space when siblings exist (HBox main axis)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Add two children: each 40mm wide
            const child1Id = store.addChildCompositeNode(root.node.id, 'Label')!
            store.resizeCompositeNode(child1Id, 'width', 40)
            const child2Id = store.addChildCompositeNode(root.node.id, 'Label')!
            store.resizeCompositeNode(child2Id, 'width', 40)

            // HBox width = 100, children use 40+40=80
            // Resize child2 to 50 → total would be 40+50=90 ≤ 100, allowed
            store.resizeCompositeNode(child2Id, 'width', 50)
            const children1 = (root.node as HBoxNode).children as LabelNode[]
            expect(children1[1].width).toBe(50)

            // Resize child2 to 80 → total would be 40+80=120 > 100, clamped to 60
            store.resizeCompositeNode(child2Id, 'width', 80)
            const children2 = (root.node as HBoxNode).children as LabelNode[]
            expect(children2[1].width).toBe(60)
        })

        it('clamps child cross-axis to parent cross dimension (height in HBox)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Set HBox height to 25
            store.updateCompositeNode(root.node.id, { height: 25 })

            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            // Try to resize Shape height to 50 → clamped to 25 (HBox height)
            store.resizeCompositeNode(childId, 'height', 50)

            const shape = (root.node as HBoxNode).children[0] as ShapeNode
            expect(shape.h).toBe(25)
        })

        it('clamps child cross-axis to parent cross dimension (width in VBox)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Set VBox width to 50
            store.updateCompositeNode(root.node.id, { width: 50 })

            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            // Try to resize Shape width to 80 → clamped to 50 (VBox width)
            store.resizeCompositeNode(childId, 'width', 80)

            const shape = (root.node as VBoxNode).children[0] as ShapeNode
            expect(shape.w).toBe(50)
        })
    })

    describe('addChildCompositeNode — dimension clamping on drop', () => {
        it('clamps dropped Shape height to HBox height (cross-axis)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Set HBox height to 15
            store.updateCompositeNode(root.node.id, { height: 15 })

            // Drop a Shape (default h=20) → should be clamped to 15
            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            const shape = (root.node as HBoxNode).children[0] as ShapeNode
            expect(shape.h).toBe(15)
        })

        it('clamps dropped Shape width to VBox width (cross-axis)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Set VBox width to 30
            store.updateCompositeNode(root.node.id, { width: 30 })

            // Drop a Shape (default w=40) → should be clamped to 30
            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            const shape = (root.node as VBoxNode).children[0] as ShapeNode
            expect(shape.w).toBe(30)
        })

        it('clamps dropped VBox height to HBox height (cross-axis)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Set HBox height to 20
            store.updateCompositeNode(root.node.id, { height: 20 })

            // Drop a VBox (default h=60) → should be clamped to 20
            const childId = store.addChildCompositeNode(root.node.id, 'VBox')!

            const vBox = (root.node as HBoxNode).children[0] as VBoxNode
            expect(vBox.height).toBe(20)
        })

        it('clamps dropped child to remaining space when siblings exist', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Add first child with width=70
            const child1Id = store.addChildCompositeNode(root.node.id, 'Shape')!
            store.resizeCompositeNode(child1Id, 'width', 70)

            // Drop second Shape (default w=40) → available = 100-70=30, clamped to 30
            const child2Id = store.addChildCompositeNode(root.node.id, 'Shape')!

            const children = (root.node as HBoxNode).children as ShapeNode[]
            expect(children[1].w).toBe(30)
        })

        it('does not clamp Label cross-axis (uses default width=10)', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // Set HBox height to 10
            store.updateCompositeNode(root.node.id, { height: 10 })

            // Drop a Label — Label defaults to width=10 in HBox, cross-axis is height (no explicit height)
            const childId = store.addChildCompositeNode(root.node.id, 'Label')!

            const label = (root.node as HBoxNode).children[0] as LabelNode
            expect(label.width).toBe(10)
        })

        it('locks Image width to VBox width on drop', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            store.updateCompositeNode(root.node.id, { width: 45 })

            const childId = store.addChildCompositeNode(root.node.id, 'Image')!

            const image = (root.node as VBoxNode).children[0] as ImageNode
            expect(image.width).toBe(45)
        })

        it('locks Image height to HBox height on drop', () => {
            const store = useDesignerStore()
            const rootId = store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            store.updateCompositeNode(root.node.id, { height: 25 })

            const childId = store.addChildCompositeNode(root.node.id, 'Image')!

            const image = (root.node as HBoxNode).children[0] as ImageNode
            expect(image.height).toBe(25)
        })
    })

    describe('compositeConflicts', () => {
        it('returns empty set when no conflicts', () => {
            const store = useDesignerStore()
            store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot
            store.addChildCompositeNode(root.node.id, 'Label')

            expect(store.compositeConflicts.size).toBe(0)
        })

        it('detects main axis overflow when parent is shrunk', () => {
            const store = useDesignerStore()
            store.addCompositeNode('detail', 'VBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // VBox default height=60, add two Shapes (default h=20 each)
            store.addChildCompositeNode(root.node.id, 'Shape')
            store.addChildCompositeNode(root.node.id, 'Shape')

            // No conflicts initially
            expect(store.compositeConflicts.size).toBe(0)

            // Shrink VBox height to 30 — now 20+20=40 > 30
            store.updateCompositeNode(root.node.id, { height: 30 })

            expect(store.compositeConflicts.size).toBe(2)
        })

        it('detects cross axis overflow when parent is shrunk', () => {
            const store = useDesignerStore()
            store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // HBox default height=30, add a Shape (default h=20)
            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            expect(store.compositeConflicts.size).toBe(0)

            // Shrink HBox height to 10 — now h=20 > 10
            store.updateCompositeNode(root.node.id, { height: 10 })

            expect(store.compositeConflicts.has(childId)).toBe(true)
        })
    })

    describe('fitCompositeChildren', () => {
        it('proportionally shrinks main axis children to fit parent', () => {
            const store = useDesignerStore()
            store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // HBox width=100, add two Shapes (default w=40 each → sum=80, fits)
            store.addChildCompositeNode(root.node.id, 'Shape')
            store.addChildCompositeNode(root.node.id, 'Shape')

            // Shrink HBox width to 50 — now 40+40=80 > 50
            store.updateCompositeNode(root.node.id, { width: 50 })

            expect(store.compositeConflicts.size).toBe(2)

            store.fitCompositeChildren(root.node.id)

            // After fit: children proportionally shrunk, sum ≤ 50
            const c1 = (root.node as HBoxNode).children[0] as ShapeNode
            const c2 = (root.node as HBoxNode).children[1] as ShapeNode
            expect(c1.w + c2.w).toBeLessThanOrEqual(50)
            expect(store.compositeConflicts.size).toBe(0)
        })

        it('clamps cross axis children to parent dimension', () => {
            const store = useDesignerStore()
            store.addCompositeNode('detail', 'HBox')
            const root = store.page.bands!.find(b => b.id === 'detail')!.children![0] as CompositeRoot

            // HBox default height=30, add a Shape (default h=20)
            const childId = store.addChildCompositeNode(root.node.id, 'Shape')!

            // Shrink HBox height to 10 — now h=20 > 10
            store.updateCompositeNode(root.node.id, { height: 10 })

            expect(store.compositeConflicts.has(childId)).toBe(true)

            store.fitCompositeChildren(root.node.id)

            const child = (root.node as HBoxNode).children[0] as ShapeNode
            expect(child.h).toBe(10)
            expect(store.compositeConflicts.size).toBe(0)
        })
    })

    describe('clipboard — copyCompositeRoot', () => {
        it('copies a CompositeRoot to clipboard with fresh UUIDs', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'VBox')
            const childId = store.addChildCompositeNode(rootId, 'Label')

            const result = store.copyCompositeRoot(rootId)
            expect(result).toBe(true)
            expect(store.clipboard).not.toBeNull()
            expect(store.clipboard!.element.id).not.toBe(rootId)
            expect(store.clipboard!.element.node.id).not.toBe(rootId)
            expect(store.clipboard!.sourceEngine).toBe('pdf-engine')
        })

        it('returns false for nonexistent root', () => {
            const store = useDesignerStore()
            expect(store.copyCompositeRoot('nonexistent')).toBe(false)
            expect(store.clipboard).toBeNull()
        })

        it('persists to localStorage', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)

            const raw = localStorage.getItem('pdf-designer-clipboard')
            expect(raw).not.toBeNull()
            const parsed = JSON.parse(raw!)
            expect(parsed.sourceEngine).toBe('pdf-engine')
        })
    })

    describe('clipboard — pasteCompositeRoot', () => {
        it('pastes a copied element into a band', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)

            const newId = store.pasteCompositeRoot('detail')
            expect(newId).not.toBeNull()

            const band = store.page.bands!.find(b => b.id === 'detail')!
            expect(band.children).toHaveLength(2)
            expect(band.children[1].id).toBe(newId)
        })

        it('returns null when clipboard is empty', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            expect(store.pasteCompositeRoot('detail')).toBeNull()
        })

        it('returns null on engine mismatch', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)

            // Switch engine
            store.engine = 'dompdf'
            expect(store.pasteCompositeRoot('detail')).toBeNull()
        })

        it('generates fresh UUIDs for the pasted element', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'VBox')
            store.addChildCompositeNode(rootId, 'Label')
            store.copyCompositeRoot(rootId)

            const newId = store.pasteCompositeRoot('detail')!
            const band = store.page.bands!.find(b => b.id === 'detail')!
            const pastedRoot = band.children[1] as CompositeRoot

            expect(pastedRoot.id).not.toBe(rootId)
            expect(pastedRoot.node.id).not.toBe(rootId)
        })

        it('falls back to selectedBand when targetBandId not specified', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            store.addCompositeNode('pageHeader', 'Label')
            store.selectedBandId = 'pageHeader'
            const rootId = store.page.bands!.find(b => b.id === 'pageHeader')!.children[0].id
            store.copyCompositeRoot(rootId)

            const newId = store.pasteCompositeRoot()
            expect(newId).not.toBeNull()
            const band = store.page.bands!.find(b => b.id === 'pageHeader')!
            expect(band.children).toHaveLength(2)
        })

        it('pushes history for undo support', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)
            store.history = [] // reset history from addCompositeNode

            store.pasteCompositeRoot('detail')
            expect(store.history.length).toBeGreaterThan(0)
        })

        it('marks store as dirty after paste', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)
            store.isDirty = false

            store.pasteCompositeRoot('detail')
            expect(store.isDirty).toBe(true)
        })
    })

    describe('clipboard — clearClipboard', () => {
        it('clears both in-memory and localStorage', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)

            expect(store.clipboard).not.toBeNull()
            store.clearClipboard()
            expect(store.clipboard).toBeNull()
            expect(localStorage.getItem('pdf-designer-clipboard')).toBeNull()
        })
    })

    describe('clipboard — hasCompatibleClipboard getter', () => {
        it('returns true when clipboard matches engine', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)

            expect(store.hasCompatibleClipboard).toBe(true)
        })

        it('returns false when engine mismatch', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            const rootId = store.addCompositeNode('detail', 'Label')
            store.copyCompositeRoot(rootId)
            store.engine = 'dompdf'

            expect(store.hasCompatibleClipboard).toBe(false)
        })

        it('returns false when clipboard is empty', () => {
            const store = useDesignerStore()
            store.engine = 'pdf-engine'
            expect(store.hasCompatibleClipboard).toBe(false)
        })
    })
})