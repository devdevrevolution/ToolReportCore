// ──────────────────────────────────────────────
// Toolreport Designer — Expression Filter Definitions
// Mirror of backend filters with metadata for the UI builder.
// ──────────────────────────────────────────────

export interface FilterParam {
    /** Parameter name shown in UI */
    name: string
    /** Parameter type for input rendering */
    type: 'string' | 'number' | 'boolean' | 'select'
    /** Default value when filter is added */
    defaultValue: unknown
    /** Available options when type is 'select' */
    options?: { label: string; value: string }[]
    /** Whether this parameter is required */
    required?: boolean
}

export interface FilterDefinition {
    /** Machine name matching the backend FilterInterface::name() */
    name: string
    /** Human-readable label shown in dropdown */
    label: string
    /** Category for grouping in the UI */
    category: 'format' | 'transform' | 'logic' | 'text'
    /** Short description for tooltips */
    description: string
    /** Parameter definitions in order */
    params: FilterParam[]
    /** Example expression for preview */
    example: string
}

/**
 * All available expression filters.
 * Keep in sync with backend: FilterRegistry::registerDefaults()
 */
export const FILTER_DEFINITIONS: FilterDefinition[] = [
    {
        name: 'number',
        label: 'Number Format',
        category: 'format',
        description: 'Format a number with decimal and thousands separators.',
        params: [
            { name: 'decimals', type: 'number', defaultValue: 2, required: true },
            { name: 'decimal_sep', type: 'string', defaultValue: '.' },
            { name: 'thousands_sep', type: 'string', defaultValue: ',' },
        ],
        example: '{{ total | number(2, ",", ".") }}',
    },
    {
        name: 'currency',
        label: 'Currency',
        category: 'format',
        description: 'Format a number as currency with symbol placement.',
        params: [
            { name: 'symbol', type: 'string', defaultValue: '$', required: true },
            { name: 'decimals', type: 'number', defaultValue: 2 },
            { name: 'decimal_sep', type: 'string', defaultValue: '.' },
            { name: 'thousands_sep', type: 'string', defaultValue: ',' },
            {
                name: 'position',
                type: 'select',
                defaultValue: 'before',
                options: [
                    { label: 'Before ($1,234)', value: 'before' },
                    { label: 'After (1,234 $)', value: 'after' },
                ],
            },
        ],
        example: '{{ price | currency("$", 2) }}',
    },
    {
        name: 'date',
        label: 'Date Format',
        category: 'format',
        description: 'Format a date or timestamp using PHP date format.',
        params: [
            { name: 'format', type: 'string', defaultValue: 'd/m/Y', required: true },
        ],
        example: '{{ created_at | date("d/m/Y") }}',
    },
    {
        name: 'upper',
        label: 'UPPERCASE',
        category: 'transform',
        description: 'Convert text to uppercase.',
        params: [],
        example: '{{ name | upper }}',
    },
    {
        name: 'lower',
        label: 'lowercase',
        category: 'transform',
        description: 'Convert text to lowercase.',
        params: [],
        example: '{{ name | lower }}',
    },
    {
        name: 'trim',
        label: 'Trim',
        category: 'text',
        description: 'Remove leading and trailing whitespace.',
        params: [],
        example: '{{ name | trim }}',
    },
    {
        name: 'default',
        label: 'Default',
        category: 'logic',
        description: 'Provide a fallback value when the variable is null or empty.',
        params: [
            { name: 'fallback', type: 'string', defaultValue: 'N/A', required: true },
        ],
        example: '{{ phone | default("N/A") }}',
    },
    {
        name: 'if',
        label: 'Conditional (if)',
        category: 'logic',
        description: 'Return one value if the variable matches, another if it doesn\'t.',
        params: [
            { name: 'compare', type: 'string', defaultValue: '', required: true },
            { name: 'true_result', type: 'string', defaultValue: '', required: true },
            { name: 'false_result', type: 'string', defaultValue: '', required: true },
        ],
        example: '{{ status | if("active", "Activo", "Inactivo") }}',
    },
    {
        name: 'substr',
        label: 'Substring',
        category: 'text',
        description: 'Extract a portion of the text.',
        params: [
            { name: 'start', type: 'number', defaultValue: 0, required: true },
            { name: 'length', type: 'number', defaultValue: 50 },
        ],
        example: '{{ desc | substr(0, 50) }}',
    },
    {
        name: 'replace',
        label: 'Replace',
        category: 'text',
        description: 'Replace all occurrences of a string.',
        params: [
            { name: 'search', type: 'string', defaultValue: '', required: true },
            { name: 'replace', type: 'string', defaultValue: '', required: true },
        ],
        example: '{{ code | replace("_", " ") }}',
    },
]

/**
 * Look up a filter definition by its machine name.
 */
export function getFilterDefinition(name: string): FilterDefinition | undefined {
    return FILTER_DEFINITIONS.find(f => f.name === name)
}

/**
 * Get filters grouped by category.
 */
export function getFiltersByCategory(): Record<string, FilterDefinition[]> {
    const groups: Record<string, FilterDefinition[]> = {}
    for (const filter of FILTER_DEFINITIONS) {
        if (!groups[filter.category]) {
            groups[filter.category] = []
        }
        groups[filter.category].push(filter)
    }
    return groups
}