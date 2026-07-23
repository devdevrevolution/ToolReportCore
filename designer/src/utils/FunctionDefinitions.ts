// ──────────────────────────────────────────────
// Toolreport Designer — Function Definitions
// Metadata for all built-in expression functions.
// Mirrors the backend FunctionRegistry::defaults() registrations.
// ──────────────────────────────────────────────

export type FunctionCategory = 'Math' | 'Text' | 'Date' | 'Logic' | 'Formatting'

export interface ParamDefinition {
    name: string
    type: 'number' | 'string' | 'boolean' | 'variable'
    required: boolean
    defaultValue?: unknown
}

export interface FunctionDefinition {
    name: string
    category: FunctionCategory
    description: string
    parameters: ParamDefinition[]
    syntax: string
    returnType: string
}

export const FUNCTION_DEFINITIONS: FunctionDefinition[] = [
    // ── Math ─────────────────────────────────────
    {
        name: 'SUM',
        category: 'Math',
        description: 'Sum all numeric values, flattening arrays automatically.',
        parameters: [{ name: 'values', type: 'variable', required: true }],
        syntax: 'SUM(prices[].total)',
        returnType: 'number',
    },
    {
        name: 'MULTIPLY',
        category: 'Math',
        description: 'Multiply a value by one or more factors.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'factor', type: 'number', required: true },
        ],
        syntax: 'MULTIPLY(price, quantity)',
        returnType: 'number',
    },
    {
        name: 'DIVIDE',
        category: 'Math',
        description: 'Divide a value by a divisor. Returns empty string if divisor is zero.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'divisor', type: 'number', required: true },
        ],
        syntax: 'DIVIDE(total, quantity)',
        returnType: 'number',
    },
    {
        name: 'ADD',
        category: 'Math',
        description: 'Add one or more values to a base value.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'addend', type: 'number', required: true },
        ],
        syntax: 'ADD(subtotal, tax)',
        returnType: 'number',
    },
    {
        name: 'SUBTRACT',
        category: 'Math',
        description: 'Subtract a value from a base value.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'subtrahend', type: 'number', required: true },
        ],
        syntax: 'SUBTRACT(total, discount)',
        returnType: 'number',
    },
    {
        name: 'MOD',
        category: 'Math',
        description: 'Get the remainder of a division. Returns empty string if divisor is zero.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'divisor', type: 'number', required: true },
        ],
        syntax: 'MOD(count, 3)',
        returnType: 'number',
    },
    {
        name: 'POW',
        category: 'Math',
        description: 'Raise a value to a power.',
        parameters: [
            { name: 'base', type: 'variable', required: true },
            { name: 'exponent', type: 'number', required: true },
        ],
        syntax: 'POW(price, 2)',
        returnType: 'number',
    },
    {
        name: 'SQRT',
        category: 'Math',
        description: 'Get the square root of a value. Returns empty string for negative values.',
        parameters: [{ name: 'value', type: 'variable', required: true }],
        syntax: 'SQRT(area)',
        returnType: 'number',
    },
    {
        name: 'ABS',
        category: 'Math',
        description: 'Get the absolute value (removes negative sign).',
        parameters: [{ name: 'value', type: 'variable', required: true }],
        syntax: 'ABS(difference)',
        returnType: 'number',
    },
    {
        name: 'ROUND',
        category: 'Math',
        description: 'Round a value to a specified number of decimal places.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'decimals', type: 'number', required: true, defaultValue: 0 },
        ],
        syntax: 'ROUND(price, 2)',
        returnType: 'number',
    },
    {
        name: 'CEIL',
        category: 'Math',
        description: 'Round a value up to the nearest integer.',
        parameters: [{ name: 'value', type: 'variable', required: true }],
        syntax: 'CEIL(price)',
        returnType: 'number',
    },
    {
        name: 'FLOOR',
        category: 'Math',
        description: 'Round a value down to the nearest integer.',
        parameters: [{ name: 'value', type: 'variable', required: true }],
        syntax: 'FLOOR(price)',
        returnType: 'number',
    },
    {
        name: 'MIN',
        category: 'Math',
        description: 'Get the smallest value from a list of numbers.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'values...', type: 'number', required: false },
        ],
        syntax: 'MIN(price, discount, 0)',
        returnType: 'number',
    },
    {
        name: 'MAX',
        category: 'Math',
        description: 'Get the largest value from a list of numbers.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'values...', type: 'number', required: false },
        ],
        syntax: 'MAX(price, threshold)',
        returnType: 'number',
    },
    {
        name: 'CLAMP',
        category: 'Math',
        description: 'Constrain a value between a minimum and maximum.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'min', type: 'number', required: true },
            { name: 'max', type: 'number', required: true },
        ],
        syntax: 'CLAMP(quantity, 1, 100)',
        returnType: 'number',
    },

    // ── Text ─────────────────────────────────────
    {
        name: 'UPPER',
        category: 'Text',
        description: 'Convert text to uppercase.',
        parameters: [{ name: 'text', type: 'variable', required: true }],
        syntax: 'UPPER(name)',
        returnType: 'string',
    },
    {
        name: 'LOWER',
        category: 'Text',
        description: 'Convert text to lowercase.',
        parameters: [{ name: 'text', type: 'variable', required: true }],
        syntax: 'LOWER(name)',
        returnType: 'string',
    },
    {
        name: 'TRIM',
        category: 'Text',
        description: 'Remove leading and trailing whitespace.',
        parameters: [{ name: 'text', type: 'variable', required: true }],
        syntax: 'TRIM(name)',
        returnType: 'string',
    },
    {
        name: 'SUBSTR',
        category: 'Text',
        description: 'Extract a portion of text by start position and length.',
        parameters: [
            { name: 'text', type: 'variable', required: true },
            { name: 'start', type: 'number', required: true, defaultValue: 0 },
            { name: 'length', type: 'number', required: false },
        ],
        syntax: 'SUBSTR(name, 0, 10)',
        returnType: 'string',
    },
    {
        name: 'REPLACE',
        category: 'Text',
        description: 'Replace all occurrences of a search string.',
        parameters: [
            { name: 'text', type: 'variable', required: true },
            { name: 'search', type: 'string', required: true },
            { name: 'replace', type: 'string', required: true },
        ],
        syntax: 'REPLACE(code, "_", " ")',
        returnType: 'string',
    },
    {
        name: 'CONCAT',
        category: 'Text',
        description: 'Concatenate two or more strings.',
        parameters: [
            { name: 'text', type: 'variable', required: true },
            { name: 'values...', type: 'string', required: false },
        ],
        syntax: 'CONCAT(firstName, " ", lastName)',
        returnType: 'string',
    },
    {
        name: 'LEFT',
        category: 'Text',
        description: 'Get the first N characters from a string.',
        parameters: [
            { name: 'text', type: 'variable', required: true },
            { name: 'count', type: 'number', required: true },
        ],
        syntax: 'LEFT(name, 5)',
        returnType: 'string',
    },
    {
        name: 'RIGHT',
        category: 'Text',
        description: 'Get the last N characters from a string.',
        parameters: [
            { name: 'text', type: 'variable', required: true },
            { name: 'count', type: 'number', required: true },
        ],
        syntax: 'RIGHT(code, 3)',
        returnType: 'string',
    },
    {
        name: 'MID',
        category: 'Text',
        description: 'Extract text from a start position with a given length.',
        parameters: [
            { name: 'text', type: 'variable', required: true },
            { name: 'start', type: 'number', required: true },
            { name: 'length', type: 'number', required: true },
        ],
        syntax: 'MID(name, 2, 5)',
        returnType: 'string',
    },
    {
        name: 'LEN',
        category: 'Text',
        description: 'Get the length of a string in characters.',
        parameters: [{ name: 'text', type: 'variable', required: true }],
        syntax: 'LEN(name)',
        returnType: 'number',
    },

    // ── Date ─────────────────────────────────────
    {
        name: 'FORMAT_DATE',
        category: 'Date',
        description: 'Format a date value using a PHP date format string.',
        parameters: [
            { name: 'date', type: 'variable', required: true },
            { name: 'format', type: 'string', required: true, defaultValue: 'd/m/Y' },
        ],
        syntax: 'FORMAT_DATE(created_at, "d/m/Y")',
        returnType: 'string',
    },
    {
        name: 'DATE_ADD',
        category: 'Date',
        description: 'Add days, months, or years to a date.',
        parameters: [
            { name: 'date', type: 'variable', required: true },
            { name: 'amount', type: 'number', required: true },
            { name: 'unit', type: 'string', required: true, defaultValue: 'days' },
        ],
        syntax: 'DATE_ADD(created_at, 30, "days")',
        returnType: 'string',
    },
    {
        name: 'DATE_DIFF',
        category: 'Date',
        description: 'Calculate the difference between two dates in days.',
        parameters: [
            { name: 'date1', type: 'variable', required: true },
            { name: 'date2', type: 'variable', required: true },
        ],
        syntax: 'DATE_DIFF(startDate, endDate)',
        returnType: 'number',
    },

    // ── Logic ────────────────────────────────────
    {
        name: 'IF',
        category: 'Logic',
        description: 'Return one value if condition matches, another if it does not.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'compare', type: 'string', required: true },
            { name: 'trueResult', type: 'string', required: true },
            { name: 'falseResult', type: 'string', required: true },
        ],
        syntax: 'IF(status, "active", "Yes", "No")',
        returnType: 'string',
    },
    {
        name: 'DEFAULT',
        category: 'Logic',
        description: 'Provide a fallback value when a variable is null or empty.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'fallback', type: 'string', required: true, defaultValue: 'N/A' },
        ],
        syntax: 'DEFAULT(phone, "N/A")',
        returnType: 'string',
    },

    // ── Formatting ───────────────────────────────
    {
        name: 'FORMAT_NUMBER',
        category: 'Formatting',
        description: 'Format a number with custom decimal and thousands separators.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'decimals', type: 'number', required: true, defaultValue: 2 },
            { name: 'decSep', type: 'string', required: false, defaultValue: '.' },
            { name: 'thousandsSep', type: 'string', required: false, defaultValue: ',' },
        ],
        syntax: 'FORMAT_NUMBER(total, 2, ".", ",")',
        returnType: 'string',
    },
    {
        name: 'FORMAT_CURRENCY',
        category: 'Formatting',
        description: 'Format a number as currency with a symbol and separators.',
        parameters: [
            { name: 'value', type: 'variable', required: true },
            { name: 'symbol', type: 'string', required: true, defaultValue: '$' },
            { name: 'decimals', type: 'number', required: false, defaultValue: 2 },
            { name: 'decSep', type: 'string', required: false, defaultValue: '.' },
            { name: 'thousandsSep', type: 'string', required: false, defaultValue: ',' },
        ],
        syntax: 'FORMAT_CURRENCY(price, "$", 2)',
        returnType: 'string',
    },
]

/**
 * Look up a function definition by name (case-insensitive).
 */
export function getFunctionDefinition(name: string): FunctionDefinition | undefined {
    return FUNCTION_DEFINITIONS.find(f => f.name === name.toUpperCase())
}

/**
 * Get all functions grouped by category.
 */
export function getFunctionsByCategory(): Record<FunctionCategory, FunctionDefinition[]> {
    const groups = {} as Record<FunctionCategory, FunctionDefinition[]>
    for (const fn of FUNCTION_DEFINITIONS) {
        if (!groups[fn.category]) {
            groups[fn.category] = []
        }
        groups[fn.category].push(fn)
    }
    return groups
}

/**
 * Get all category names.
 */
export function getCategoryNames(): FunctionCategory[] {
    const seen = new Set<FunctionCategory>()
    for (const fn of FUNCTION_DEFINITIONS) {
        seen.add(fn.category)
    }
    return Array.from(seen)
}
