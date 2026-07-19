// ──────────────────────────────────────────────
// Toolreport Designer — Expression Parser (Frontend)
// Parses and builds {{ variable | filter(args) }} expressions
// with concatenation support: {{ 'prefix' + var | filter + 'suffix' }}
// Mirrors the backend ExpressionParser for round-trip support.
// ──────────────────────────────────────────────

export interface ParsedFilter {
    name: string
    params: unknown[]
}

export interface ParsedTerm {
    type: 'variable' | 'literal'
    value: string
    filters: ParsedFilter[]
}

export interface ParsedExpression {
    terms: ParsedTerm[]
    hasConcatenation: boolean
    hasFilters: boolean
}

/**
 * Parse an expression string like "'prefix' + price | currency('$') + 'suffix'"
 * into an array of terms (literals and/or variables with optional filters).
 *
 * Operator precedence: | (filter) binds tighter than + (concatenation)
 * So: "a | upper + b" = (a | upper) + b
 */
export function parseExpression(expression: string): ParsedExpression {
    const trimmed = expression.trim()

    const hasConcat = hasConcatenationOp(trimmed)
    const hasFilters = hasPipeFilters(trimmed)

    const segments = hasConcat ? splitByPlus(trimmed) : [trimmed]
    const terms: ParsedTerm[] = []

    for (const segment of segments) {
        const s = segment.trim()
        if (s === '') continue
        terms.push(parseTerm(s))
    }

    return { terms, hasConcatenation: hasConcat, hasFilters }
}

/**
 * Build an expression string from terms.
 * This is the inverse of parseExpression for round-trip support.
 */
export function buildExpression(terms: ParsedTerm[]): string {
    return terms
        .map(term => {
            if (term.type === 'literal') {
                // Escape single quotes inside the literal
                const escaped = term.value.replace(/'/g, "\\'")
                return `'${escaped}'`
            }

            let expr = term.value
            if (term.filters.length > 0) {
                const filterParts = term.filters.map(f => {
                    if (f.params.length === 0) return f.name
                    const paramStr = f.params
                        .map(p => {
                            if (typeof p === 'string') return `"${p.replace(/"/g, '\\"')}"`
                            if (typeof p === 'boolean') return p ? 'true' : 'false'
                            return String(p)
                        })
                        .join(', ')
                    return `${f.name}(${paramStr})`
                })
                expr = `${term.value} | ${filterParts.join(' | ')}`
            }
            return expr
        })
        .join(' + ')
}

/**
 * Check if an expression contains pipe filters outside quotes.
 */
export function hasPipeFilters(expression: string): boolean {
    return containsOutsideQuotes(expression, '|')
}

/**
 * Check if an expression contains concatenation operators outside quotes.
 */
export function hasConcatenationOp(expression: string): boolean {
    return containsOutsideQuotes(expression, '+')
}

/**
 * Check if an expression is a quoted literal string.
 */
export function isQuotedLiteral(str: string): boolean {
    const s = str.trim()
    if (s.length < 2) return false
    return (s.startsWith("'") && s.endsWith("'")) || (s.startsWith('"') && s.endsWith('"'))
}

/**
 * Format params for display in the expression preview.
 */
export function formatParamForDisplay(param: unknown): string {
    if (typeof param === 'boolean') return param ? 'true' : 'false'
    if (typeof param === 'string') return `"${param}"`
    return String(param)
}

// ── Internal helpers ───────────────────────────

function parseTerm(segment: string): ParsedTerm {
    const trimmed = segment.trim()

    // Check if it's a literal string
    if (isQuotedLiteral(trimmed)) {
        const unquoted = trimmed.slice(1, -1)
        return {
            type: 'literal',
            value: unescapeString(unquoted),
            filters: [],
        }
    }

    // It's a variable, possibly with filters
    const parts = splitByPipe(trimmed)
    const variableKey = parts[0].trim()
    const filters: ParsedFilter[] = []

    for (let i = 1; i < parts.length; i++) {
        const filterSeg = parts[i].trim()
        const match = filterSeg.match(/^(\w+)\s*\((.+)\)$/s)
        if (match) {
            filters.push({ name: match[1], params: parseParams(match[2]) })
        } else {
            const nameOnly = filterSeg.match(/^(\w+)$/)
            if (nameOnly) {
                filters.push({ name: nameOnly[1], params: [] })
            }
        }
    }

    return { type: 'variable', value: variableKey, filters }
}

function unescapeString(str: string): string {
    return str
        .replace(/\\n/g, '\n')
        .replace(/\\t/g, '\t')
        .replace(/\\\\/g, '\\')
        .replace(/\\'/g, "'")
        .replace(/\\"/g, '"')
}

function splitByPlus(expression: string): string[] {
    const segments: string[] = []
    let current = ''
    let depth = 0
    let inSingle = false
    let inDouble = false

    for (let i = 0; i < expression.length; i++) {
        const char = expression[i]
        if (char === "'" && !inDouble) { inSingle = !inSingle; current += char }
        else if (char === '"' && !inSingle) { inDouble = !inDouble; current += char }
        else if (char === '(' && !inSingle && !inDouble) { depth++; current += char }
        else if (char === ')' && !inSingle && !inDouble) { depth--; current += char }
        else if (char === '+' && !inSingle && !inDouble && depth === 0) { segments.push(current); current = '' }
        else { current += char }
    }
    segments.push(current)
    return segments
}

function splitByPipe(expression: string): string[] {
    const segments: string[] = []
    let current = ''
    let inSingle = false
    let inDouble = false

    for (let i = 0; i < expression.length; i++) {
        const char = expression[i]
        if (char === "'" && !inDouble) { inSingle = !inSingle; current += char }
        else if (char === '"' && !inSingle) { inDouble = !inDouble; current += char }
        else if (char === '|' && !inSingle && !inDouble) { segments.push(current); current = '' }
        else { current += char }
    }
    segments.push(current)
    return segments
}

function parseParams(paramsString: string): unknown[] {
    const trimmed = paramsString.trim()
    if (trimmed === '') return []

    const params: unknown[] = []
    let current = ''
    let inSingle = false
    let inDouble = false

    for (let i = 0; i < trimmed.length; i++) {
        const char = trimmed[i]
        if (char === "'" && !inDouble) { inSingle = !inSingle; current += char }
        else if (char === '"' && !inSingle) { inDouble = !inDouble; current += char }
        else if (char === ',' && !inSingle && !inDouble) { params.push(coerceParam(current.trim())); current = '' }
        else { current += char }
    }
    params.push(coerceParam(current.trim()))
    return params
}

function coerceParam(raw: string): unknown {
    if (/^"(.*)"$/s.test(raw)) return raw.slice(1, -1)
    if (/^'(.*)'$/s.test(raw)) return raw.slice(1, -1)
    if (raw.toLowerCase() === 'true') return true
    if (raw.toLowerCase() === 'false') return false
    if (/^-?\d+$/.test(raw)) return parseInt(raw, 10)
    if (/^-?\d+\.\d+$/.test(raw)) return parseFloat(raw)
    return raw
}

function containsOutsideQuotes(expression: string, char: string): boolean {
    let inSingle = false
    let inDouble = false
    for (let i = 0; i < expression.length; i++) {
        const c = expression[i]
        if (c === "'" && !inDouble) inSingle = !inSingle
        else if (c === '"' && !inSingle) inDouble = !inDouble
        else if (c === char && !inSingle && !inDouble) return true
    }
    return false
}