import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { useDesignerStore } from '@/stores/designer'
import type { DesignerElement, TableColumn, CellMerge } from '@/types/designer'
import { useTableElement } from '../composables/useTableElement'
import TableElement from '../TableElement.vue'
import TableProperty from '../TableProperty.vue'

// ── Merge helper tests ──────────────────────

import {
  isCellCovered,
  getMergeAt,
  getMergesContaining,
  wouldOverlap,
  clampMerge,
  createMergeFromSelection,
  removeMerge,
} from '../helpers/merge'

// ── Helpers ──────────────────────────────────

function createTableElement(overrides: Partial<DesignerElement> = {}): DesignerElement {
  return {
    id: 'table-1',
    type: 'table',
    x: 0,
    y: 0,
    width: 200,
    height: 100,
    rotation: 0,
    styles: {
      fontFamily: 'Helvetica',
      fontSize: 12,
      fontWeight: 'normal',
      fontStyle: 'normal',
      color: '#000000',
      textAlign: 'left',
      verticalAlign: 'top',
      lineHeight: 1.2,
      backgroundColor: null,
      border: null,
      borderRadius: 0,
      padding: { top: 0, right: 0, bottom: 0, left: 0 },
    },
    content: { type: 'table', columns: [], variable: undefined },
    visible: true,
    locked: false,
    ...overrides,
  } as DesignerElement
}

const sampleColumns: TableColumn[] = [
  { id: 'col-1', key: 'name', header: 'Name', width: 40, align: 'left' },
  { id: 'col-2', key: 'price', header: 'Price', width: 30, align: 'right' },
  { id: 'col-3', key: 'qty', header: 'Qty', width: 20, align: 'center' },
]

const threeColKeys = () => sampleColumns.map(c => ({ key: c.key }))

const sampleRows = () => [
  { name: { text: 'Widget' }, price: { text: '10' }, qty: { text: '5' } },
  { name: { text: 'Gadget' }, price: { text: '25' }, qty: { text: '3' } },
  { name: { text: 'Thing' }, price: { text: '15' }, qty: { text: '8' } },
]

// ────────────────────────────────────────────
// Merge helper tests
// ────────────────────────────────────────────

describe('isCellCovered', () => {
  const merges: CellMerge[] = [
    { row: 0, col: 0, rowspan: 2, colspan: 2 },
  ]

  it('returns false for origin cell', () => {
    expect(isCellCovered(0, 0, merges)).toBe(false)
  })

  it('returns true for cell within merge range but not origin', () => {
    expect(isCellCovered(0, 1, merges)).toBe(true)
    expect(isCellCovered(1, 0, merges)).toBe(true)
    expect(isCellCovered(1, 1, merges)).toBe(true)
  })

  it('returns false for cell outside merge range', () => {
    expect(isCellCovered(0, 2, merges)).toBe(false)
    expect(isCellCovered(2, 0, merges)).toBe(false)
    expect(isCellCovered(2, 2, merges)).toBe(false)
  })

  it('returns false when no merges', () => {
    expect(isCellCovered(0, 0, [])).toBe(false)
  })
})

describe('getMergeAt', () => {
  const merges: CellMerge[] = [
    { row: 0, col: 0, rowspan: 2, colspan: 2 },
  ]

  it('returns merge for origin cell', () => {
    const m = getMergeAt(0, 0, merges)
    expect(m).not.toBeNull()
    expect(m!.colspan).toBe(2)
  })

  it('returns null for covered cell', () => {
    expect(getMergeAt(0, 1, merges)).toBeNull()
    expect(getMergeAt(1, 0, merges)).toBeNull()
  })

  it('returns null for uncovered cell', () => {
    expect(getMergeAt(3, 3, merges)).toBeNull()
  })
})

describe('getMergesContaining', () => {
  const merges: CellMerge[] = [
    { row: 0, col: 0, rowspan: 2, colspan: 2 },
  ]

  it('finds merge for origin cell', () => {
    expect(getMergesContaining(0, 0, merges)).toHaveLength(1)
  })

  it('finds merge for covered cell', () => {
    expect(getMergesContaining(0, 1, merges)).toHaveLength(1)
    expect(getMergesContaining(1, 1, merges)).toHaveLength(1)
  })

  it('returns empty for uncovered cell', () => {
    expect(getMergesContaining(3, 3, merges)).toHaveLength(0)
  })
})

describe('wouldOverlap', () => {
  const existing: CellMerge[] = [
    { row: 0, col: 0, rowspan: 2, colspan: 2 },
  ]

  it('detects overlap with existing merge', () => {
    expect(wouldOverlap({ row: 0, col: 0, rowspan: 1, colspan: 1 }, existing)).toBe(true)
    expect(wouldOverlap({ row: 1, col: 1, rowspan: 1, colspan: 1 }, existing)).toBe(true)
  })

  it('allows non-overlapping merge', () => {
    expect(wouldOverlap({ row: 3, col: 3, rowspan: 1, colspan: 1 }, existing)).toBe(false)
  })
})

describe('clampMerge', () => {
  it('clamps rowspan and colspan to table boundaries', () => {
    const m = clampMerge({ row: 0, col: 0, rowspan: 10, colspan: 10 }, 3, 2)
    expect(m.rowspan).toBe(3)
    expect(m.colspan).toBe(2)
  })

  it('keeps valid merge unchanged', () => {
    const m = clampMerge({ row: 1, col: 1, rowspan: 1, colspan: 1 }, 5, 5)
    expect(m.rowspan).toBe(1)
    expect(m.colspan).toBe(1)
  })
})

describe('createMergeFromSelection', () => {
  const rows = sampleRows()
  const cols = threeColKeys()

  it('creates a merge for 2x2 selection', () => {
    const result = createMergeFromSelection(
      { startRow: 0, startCol: 0, endRow: 1, endCol: 1 },
      [],
      rows,
      cols,
    )
    expect('error' in result).toBe(false)
    if (!('error' in result)) {
      expect(result.merge.rowspan).toBe(2)
      expect(result.merge.colspan).toBe(2)
      // Top-left keeps its value
      expect(result.mergedRows[0].name.text).toBe('Widget')
      // Other cell in merge range gets cleared
      expect(result.mergedRows[0].price?.text).toBe('')
      expect(result.mergedRows[1].name?.text).toBe('')
    }
  })

  it('returns error for single cell selection', () => {
    const result = createMergeFromSelection(
      { startRow: 0, startCol: 0, endRow: 0, endCol: 0 },
      [],
      rows,
      cols,
    )
    expect('error' in result).toBe(true)
  })

  it('returns error when selection overlaps existing merge', () => {
    const existing = [{ row: 0, col: 0, rowspan: 2, colspan: 2 }]
    const result = createMergeFromSelection(
      { startRow: 0, startCol: 0, endRow: 0, endCol: 1 },
      existing,
      rows,
      cols,
    )
    expect('error' in result).toBe(true)
  })
})

describe('removeMerge', () => {
  it('removes merge and clears covered cells', () => {
    const rows = sampleRows()
    const merges: CellMerge[] = [{ row: 0, col: 0, rowspan: 2, colspan: 2 }]
    const cols = threeColKeys()

    const result = removeMerge(merges[0], merges, rows, cols)
    expect(result.merges).toHaveLength(0)
    // Top-left keeps its value
    expect(result.rows[0].name.text).toBe('Widget')
    // Covered cells get cleared
    expect(result.rows[0].price?.text).toBe('')
    expect(result.rows[1].name?.text).toBe('')
  })
})

// ────────────────────────────────────────────
// useTableElement composable tests
// ────────────────────────────────────────────

describe('useTableElement composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns hasColumns=true when columns are set', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const { hasColumns, columns } = useTableElement(el)
    expect(hasColumns.value).toBe(true)
    expect(columns.value).toHaveLength(3)
  })

  it('returns hasColumns=false when columns are empty', () => {
    const el = createTableElement()
    const { hasColumns, columns } = useTableElement(el)
    expect(hasColumns.value).toBe(false)
    expect(columns.value).toHaveLength(0)
  })

  it('returns hasVariable=true when variable is set', () => {
    const el = createTableElement({
      content: { type: 'table', columns: [], variable: 'data.items' },
    })
    const { hasVariable, variable } = useTableElement(el)
    expect(hasVariable.value).toBe(true)
    expect(variable.value).toBe('data.items')
  })

  it('returns hasVariable=false when variable is undefined', () => {
    const el = createTableElement()
    const { hasVariable, variable } = useTableElement(el)
    expect(hasVariable.value).toBe(false)
    expect(variable.value).toBeUndefined()
  })

  it('returns rows/merges/rowCount/mergeCount from content', () => {
    const el = createTableElement({
      content: {
        type: 'table',
        columns: sampleColumns,
        rows: sampleRows(),
        merges: [{ row: 0, col: 0, rowspan: 1, colspan: 2 }],
      },
    })
    const { rows, merges, rowCount, mergeCount, hasRows } = useTableElement(el)
    expect(rows.value).toHaveLength(3)
    expect(merges.value).toHaveLength(1)
    expect(rowCount.value).toBe(3)
    expect(mergeCount.value).toBe(1)
    expect(hasRows.value).toBe(true)
  })

  it('returns rowCount=0 and mergeCount=0 by default', () => {
    const el = createTableElement()
    const { rowCount, mergeCount, hasRows } = useTableElement(el)
    expect(rowCount.value).toBe(0)
    expect(mergeCount.value).toBe(0)
    expect(hasRows.value).toBe(false)
  })

  it('getEffectiveStyle merges cell overrides over element defaults', () => {
    const el = createTableElement()
    const { getEffectiveStyle } = useTableElement(el)

    const style = getEffectiveStyle(
      { text: 'test', fontWeight: 'bold', color: '#ff0000' },
      el.styles,
    )
    expect(style.fontWeight).toBe('bold')
    expect(style.color).toBe('#ff0000')
    expect(style.fontFamily).toBe('Helvetica') // inherited from element
    // When no scale is provided, s = 1 → ptToPx(12, 1) = (12 * 25.4) / 72
    expect(style.fontSize).toBe('4.2333333333333325px')
  })

  it('getEffectiveStyle returns element defaults for empty cell', () => {
    const el = createTableElement()
    const { getEffectiveStyle } = useTableElement(el)

    const style = getEffectiveStyle({ text: '' }, el.styles)
    expect(style.fontFamily).toBe('Helvetica')
    expect(style.fontSize).toBe('4.2333333333333325px')
  })

  it('getEffectiveStyle includes nowrap CSS when nowrap is set', () => {
    const el = createTableElement()
    const { getEffectiveStyle } = useTableElement(el)

    const style = getEffectiveStyle({ text: 'test', nowrap: true }, el.styles)
    expect(style.whiteSpace).toBe('nowrap')
    expect(style.overflow).toBe('hidden')
    expect(style.textOverflow).toBe('ellipsis')
  })

  it('getEffectiveStyle omits nowrap CSS when nowrap is not set', () => {
    const el = createTableElement()
    const { getEffectiveStyle } = useTableElement(el)

    const style = getEffectiveStyle({ text: 'test' }, el.styles)
    expect(style.whiteSpace).toBeUndefined()
    expect(style.overflow).toBeUndefined()
  })

  it('getCellText returns text from row cell', () => {
    const el = createTableElement()
    const { getCellText } = useTableElement(el)

    const row = { name: { text: 'Widget' } }
    expect(getCellText(row, 'name')).toBe('Widget')
    expect(getCellText(row, 'nonexistent')).toBe('')
  })

  it('tablePreviewStyle uses ptToPx with scale defaulting to 1', () => {
    const el = createTableElement()
    const { tablePreviewStyle } = useTableElement(el)
    // ptToPx(12, 1) = (12 * 25.4) / 72
    expect(tablePreviewStyle.value.fontSize).toBe('4.2333333333333325px')
  })

  it('tablePreviewStyle clamps fontSize to minimum 6', () => {
    const el = createTableElement({
      styles: {
        ...createTableElement().styles,
        fontSize: 4,
      },
    })
    const { tablePreviewStyle } = useTableElement(el)
    // ptToPx(Math.max(6, 4), 1) = ptToPx(6, 1) = (6 * 25.4) / 72
    expect(tablePreviewStyle.value.fontSize).toBe('2.1166666666666663px')
  })

  it('tablePreviewStyle includes fontFamily', () => {
    const el = createTableElement()
    const { tablePreviewStyle } = useTableElement(el)
    expect(tablePreviewStyle.value.fontFamily).toBe('Helvetica')
  })

  it('expressionPreview formats variable in mustache braces', () => {
    const el = createTableElement()
    const { expressionPreview } = useTableElement(el)
    expect(expressionPreview('data.items')).toBe('{{ data.items }}')
  })

  it('updateContent calls store.updateElement', () => {
    const store = useDesignerStore()
    const elId = store.addElement('table')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    const { updateContent } = useTableElement(el)

    updateContent('variable', 'data.products')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    expect((updated.content as any).variable).toBe('data.products')
  })

  it('onColumnEditorSave updates columns via store', () => {
    const store = useDesignerStore()
    const elId = store.addElement('table')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    const { onColumnEditorSave } = useTableElement(el)

    onColumnEditorSave(sampleColumns)

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    expect((updated.content as any).columns).toEqual(sampleColumns)
  })

  it('onRowEditorSave updates rows via store', () => {
    const store = useDesignerStore()
    const elId = store.addElement('table')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    const { onRowEditorSave } = useTableElement(el)

    onRowEditorSave(sampleRows())

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    expect((updated.content as any).rows).toEqual(sampleRows())
  })
})

// ────────────────────────────────────────────
// TableElement canvas rendering tests
// ────────────────────────────────────────────

describe('TableElement canvas rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders table with thead and tbody when columns exist', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.find('table').exists()).toBe(true)
    expect(wrapper.find('thead').exists()).toBe(true)
    expect(wrapper.find('tbody').exists()).toBe(true)
  })

  it('renders column headers with correct text', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.text()).toContain('Name')
    expect(wrapper.text()).toContain('Price')
    expect(wrapper.text()).toContain('Qty')
  })

  it('renders fallback preview rows when no static data', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    // Fallback rows show column key references
    expect(wrapper.text()).toContain('name')
    expect(wrapper.text()).toContain('price')
  })

  it('renders static row data when available', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns, rows: sampleRows() },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.text()).toContain('Widget')
    expect(wrapper.text()).toContain('Gadget')
    expect(wrapper.text()).toContain('Thing')
  })

  it('renders empty state when no columns configured', () => {
    const el = createTableElement()
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.text()).toContain('Configure columns')
  })

  it('renders data binding badge when variable is set', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns, variable: 'data.items' },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.text()).toContain('data.items')
  })

  it('does not render data binding badge when variable is unset', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.find('.bg-blue-100').exists()).toBe(false)
  })

  it('renders column header fallback to key when header is empty', () => {
    const columns: TableColumn[] = [
      { id: 'col-1', key: 'product', header: '', width: 40 },
    ]
    const el = createTableElement({
      content: { type: 'table', columns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.text()).toContain('product')
  })

  it('renders non-breaking space in header when both header and key are empty', () => {
    const columns: TableColumn[] = [
      { id: 'col-1', key: '', header: '', width: 40 },
    ]
    const el = createTableElement({
      content: { type: 'table', columns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    const th = wrapper.find('th')
    expect(th.exists()).toBe(true)
  })

  it('has table element with border-collapse class', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableElement, { props: { element: el } })
    expect(wrapper.find('table.border-collapse').exists()).toBe(true)
  })
})

// ────────────────────────────────────────────
// TableProperty property editing tests
// ────────────────────────────────────────────

describe('TableProperty property editing', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders variable input with current value', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns, variable: 'data.items' },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    const input = wrapper.find('input[type="text"]')
    expect((input.element as HTMLInputElement).value).toBe('data.items')
  })

  it('renders variable input as empty when no variable set', () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    const input = wrapper.find('input[type="text"]')
    expect((input.element as HTMLInputElement).value).toBe('')
  })

  it('renders fx and field selector buttons', () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    const buttons = wrapper.findAll('button')
    const buttonTexts = buttons.map(b => b.text())
    expect(buttonTexts).toContain('fx')
    expect(buttonTexts).toContain('↳')
  })

  it('renders Configure button', () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('Configure')
  })

  it('shows empty state when no columns', () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('No columns configured.')
  })

  it('shows column summaries for configured columns', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('Name')
    expect(wrapper.text()).toContain('Price')
    expect(wrapper.text()).toContain('Qty')
  })

  it('shows "+ N more" when more than 3 columns', () => {
    const fourColumns: TableColumn[] = [
      { id: 'c1', key: 'a', header: 'A' },
      { id: 'c2', key: 'b', header: 'B' },
      { id: 'c3', key: 'c', header: 'C' },
      { id: 'c4', key: 'd', header: 'D' },
    ]
    const el = createTableElement({
      content: { type: 'table', columns: fourColumns },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('+ 1 more')
  })

  it('shows rows section when columns exist', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('Rows')
    expect(wrapper.text()).toContain('Add Rows')
  })

  it('shows row count when static rows exist', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns, rows: sampleRows() },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('3 rows')
    expect(wrapper.text()).toContain('Edit Rows')
  })

  it('shows merge count when merges exist', () => {
    const el = createTableElement({
      content: {
        type: 'table',
        columns: sampleColumns,
        rows: sampleRows(),
        merges: [{ row: 0, col: 0, rowspan: 1, colspan: 2 }],
      },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('1 merge')
  })

  it('shows FieldSelector when ↳ button clicked', async () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    const buttons = wrapper.findAll('button')
    const fieldButton = buttons.find(b => b.text() === '↳')
    expect(fieldButton).toBeDefined()
    await fieldButton!.trigger('click')

    const fieldSelector = wrapper.findComponent({ name: 'FieldSelector' })
    expect(fieldSelector.exists()).toBe(true)
  })

  it('shows ExpressionBuilder when fx button clicked', async () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    const buttons = wrapper.findAll('button')
    const fxButton = buttons.find(b => b.text() === 'fx')
    expect(fxButton).toBeDefined()
    await fxButton!.trigger('click')

    const exprBuilder = wrapper.findComponent({ name: 'ExpressionBuilder' })
    expect(exprBuilder.exists()).toBe(true)
  })

  it('shows TableColumnEditor when Configure clicked', async () => {
    const el = createTableElement()
    const wrapper = mount(TableProperty, { props: { element: el } })
    const configureButton = wrapper.findAll('button').find(b => b.text() === 'Configure')
    expect(configureButton).toBeDefined()
    await configureButton!.trigger('click')

    const columnEditor = wrapper.findComponent({ name: 'TableColumnEditor' })
    expect(columnEditor.exists()).toBe(true)
  })

  it('renders expression preview when variable is set', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns, variable: 'data.items' },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('{{ data.items }}')
  })

  it('does not render expression preview when variable is unset', () => {
    const el = createTableElement({
      content: { type: 'table', columns: sampleColumns },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).not.toContain('{{')
  })

  it('updateContent on variable input change', async () => {
    const store = useDesignerStore()
    const elId = store.addElement('table')
    const el = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    const wrapper = mount(TableProperty, { props: { element: el } })

    const input = wrapper.find('input[type="text"]')
    await input.setValue('data.products')

    const updated = store.page.bands!.flatMap(b => b.children).find(e => e.id === elId)! as DesignerElement
    expect((updated.content as any).variable).toBe('data.products')
  })

  it('renders column list with key fallback display', () => {
    const columns: TableColumn[] = [
      { id: 'c1', key: 'product_name', header: '' },
    ]
    const el = createTableElement({
      content: { type: 'table', columns },
    })
    const wrapper = mount(TableProperty, { props: { element: el } })
    expect(wrapper.text()).toContain('product_name')
  })
})
