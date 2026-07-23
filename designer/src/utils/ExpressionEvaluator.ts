// ──────────────────────────────────────────────
// Toolreport Designer — Expression Evaluator (Frontend)
// TypeScript expression evaluator matching the PHP parser grammar.
// Tokenizer → Parser → Evaluator pipeline.
// Mirrors src/Expression/Tokenizer.php, Parser.php, Evaluator.php
// ──────────────────────────────────────────────

// ── Token Types ────────────────────────────────

export type TokenType =
    | 'FUNCTION'
    | 'VARIABLE'
    | 'NUMBER'
    | 'STRING'
    | 'LPAREN'
    | 'RPAREN'
    | 'COMMA'
    | 'OPERATOR'
    | 'EOF'

export interface Token {
    type: TokenType
    value: string
    position: number
}

// ── AST Node Types ─────────────────────────────

export interface FunctionCallNode {
    type: 'functionCall'
    name: string
    args: ExprNode[]
}

export interface VariableNode {
    type: 'variable'
    path: string
}

export interface NumberLiteralNode {
    type: 'numberLiteral'
    value: number
}

export interface StringLiteralNode {
    type: 'stringLiteral'
    value: string
}

export interface BinaryOpNode {
    type: 'binaryOp'
    left: ExprNode
    operator: string
    right: ExprNode
}

export interface FilterApplication {
    name: string
    params: ExprNode[]
}

export interface FilterChainNode {
    type: 'filterChain'
    base: ExprNode
    filters: FilterApplication[]
}

export type ExprNode =
    | FunctionCallNode
    | VariableNode
    | NumberLiteralNode
    | StringLiteralNode
    | BinaryOpNode
    | FilterChainNode

// ── Tokenizer ──────────────────────────────────

export class ExpressionTokenizer {
    private input: string = ''
    private length: number = 0
    private pos: number = 0
    private tokens: Token[] = []

    tokenize(input: string): Token[] {
        this.input = input
        this.length = input.length
        this.pos = 0
        this.tokens = []

        while (this.pos < this.length) {
            const char = this.input[this.pos]

            // Skip whitespace
            if (char === ' ' || char === '\t' || char === '\n' || char === '\r') {
                this.pos++
                continue
            }

            // Single-character tokens
            if (char === '(') {
                this.tokens.push({ type: 'LPAREN', value: '(', position: this.pos })
                this.pos++
                continue
            }
            if (char === ')') {
                this.tokens.push({ type: 'RPAREN', value: ')', position: this.pos })
                this.pos++
                continue
            }
            if (char === ',') {
                this.tokens.push({ type: 'COMMA', value: ',', position: this.pos })
                this.pos++
                continue
            }

            // Operators: | or +
            if (char === '|' || char === '+') {
                this.tokens.push({ type: 'OPERATOR', value: char, position: this.pos })
                this.pos++
                continue
            }

            // Number literal
            if (this.isDigit(char) || (char === '.' && this.pos + 1 < this.length && this.isDigit(this.input[this.pos + 1]))) {
                this.tokenizeNumber()
                continue
            }

            // String literal (single or double quoted)
            if (char === "'" || char === '"') {
                this.tokenizeString(char)
                continue
            }

            // Identifier (variable or function)
            if (this.isIdentifierStart(char)) {
                this.tokenizeIdentifier()
                continue
            }

            // Unknown character — skip
            this.pos++
        }

        this.tokens.push({ type: 'EOF', value: '', position: this.length })
        return this.tokens
    }

    private tokenizeNumber(): void {
        const startPos = this.pos
        let number = ''

        while (this.pos < this.length && (this.isDigit(this.input[this.pos]) || this.input[this.pos] === '.')) {
            number += this.input[this.pos]
            this.pos++
        }

        this.tokens.push({ type: 'NUMBER', value: number, position: startPos })
    }

    private tokenizeString(quote: string): void {
        const startPos = this.pos
        this.pos++ // skip opening quote
        let value = ''

        while (this.pos < this.length && this.input[this.pos] !== quote) {
            value += this.input[this.pos]
            this.pos++
        }

        if (this.pos >= this.length) {
            throw new Error(`Unterminated string starting at position ${startPos}`)
        }

        this.pos++ // skip closing quote
        this.tokens.push({ type: 'STRING', value, position: startPos })
    }

    private tokenizeIdentifier(): void {
        const startPos = this.pos
        let identifier = ''

        while (this.pos < this.length && this.isIdentifierChar(this.input[this.pos])) {
            identifier += this.input[this.pos]
            this.pos++
        }

        // Lookahead: is the next non-whitespace char '('? → FUNCTION
        let lookaheadPos = this.pos
        while (lookaheadPos < this.length && (this.input[lookaheadPos] === ' ' || this.input[lookaheadPos] === '\t')) {
            lookaheadPos++
        }

        if (lookaheadPos < this.length && this.input[lookaheadPos] === '(') {
            this.tokens.push({ type: 'FUNCTION', value: identifier, position: startPos })
        } else {
            this.tokens.push({ type: 'VARIABLE', value: identifier, position: startPos })
        }
    }

    private isDigit(char: string): boolean {
        return char >= '0' && char <= '9'
    }

    private isIdentifierStart(char: string): boolean {
        return (char >= 'a' && char <= 'z') ||
               (char >= 'A' && char <= 'Z') ||
               char === '_' ||
               char === '['
    }

    private isIdentifierChar(char: string): boolean {
        return this.isIdentifierStart(char) ||
               this.isDigit(char) ||
               char === '.' ||
               char === ']'
    }
}

// ── Parser ─────────────────────────────────────

export class ExpressionParser {
    private tokens: Token[] = []
    private current: number = 0

    parse(tokens: Token[]): ExprNode {
        this.tokens = tokens
        this.current = 0

        const node = this.parseExpression()

        if (this.peek().type !== 'EOF') {
            throw new Error(
                `Unexpected token "${this.peek().value}" at position ${this.peek().position}`
            )
        }

        return node
    }

    private parseExpression(): ExprNode {
        // Parse the first pipe expression (| binds tighter than +)
        let left = this.parsePipeExpression()

        // Handle concatenation: left + right
        while (this.peek().type === 'OPERATOR' && this.peek().value === '+') {
            this.advance() // consume '+'
            const right = this.parsePipeExpression()
            left = { type: 'binaryOp', left, operator: '+', right }
        }

        return left
    }

    private parsePipeExpression(): ExprNode {
        let node = this.parsePrimary()

        while (this.peek().type === 'OPERATOR' && this.peek().value === '|') {
            this.advance() // consume '|'

            const filters: FilterApplication[] = [this.parseFilterApplication()]

            // Collect chained filters: name | f1 | f2
            while (this.peek().type === 'OPERATOR' && this.peek().value === '|') {
                this.advance() // consume '|'
                filters.push(this.parseFilterApplication())
            }

            node = { type: 'filterChain', base: node, filters }
        }

        return node
    }

    private parseFilterApplication(): FilterApplication {
        const nameToken = this.expect('FUNCTION', 'VARIABLE')
        const name = nameToken.value
        const params: ExprNode[] = []

        if (this.peek().type === 'LPAREN') {
            this.advance() // consume '('
            params.push(...this.parseArgumentList())
            this.expect('RPAREN')
        }

        return { name, params }
    }

    private parsePrimary(): ExprNode {
        // Parenthesized expression
        if (this.peek().type === 'LPAREN') {
            this.advance() // consume '('
            const node = this.parseExpression()
            this.expect('RPAREN')
            return node
        }

        // Number literal
        if (this.peek().type === 'NUMBER') {
            const token = this.advance()
            return { type: 'numberLiteral', value: parseFloat(token.value) }
        }

        // String literal
        if (this.peek().type === 'STRING') {
            const token = this.advance()
            return { type: 'stringLiteral', value: token.value }
        }

        // Function call: IDENTIFIER '(' argumentList? ')'
        if (this.peek().type === 'FUNCTION') {
            const name = this.advance().value
            this.expect('LPAREN')
            const args = this.parseArgumentList()
            this.expect('RPAREN')
            return { type: 'functionCall', name, args }
        }

        // Variable
        if (this.peek().type === 'VARIABLE') {
            const token = this.advance()
            return { type: 'variable', path: token.value }
        }

        throw new Error(
            `Unexpected token "${this.peek().value}" at position ${this.peek().position}`
        )
    }

    private parseArgumentList(): ExprNode[] {
        const args: ExprNode[] = []

        if (this.peek().type === 'RPAREN') {
            return args
        }

        args.push(this.parseExpression())

        while (this.peek().type === 'COMMA') {
            this.advance() // consume ','
            args.push(this.parseExpression())
        }

        return args
    }

    private advance(): Token {
        const token = this.tokens[this.current]
        if (this.current < this.tokens.length - 1) {
            this.current++
        }
        return token
    }

    private peek(): Token {
        return this.tokens[this.current]
    }

    private expect(...types: TokenType[]): Token {
        const current = this.peek()
        if (!types.includes(current.type)) {
            const expected = types.join(' or ')
            throw new Error(
                `Expected ${expected}, got "${current.value || current.type}" at position ${current.position}`
            )
        }
        return this.advance()
    }
}

// ── Evaluator ──────────────────────────────────

type ResolveFn = (key: string) => unknown

/**
 * Built-in function implementations (mirrors backend FunctionInterface::apply).
 * Each function receives the first resolved arg as `value` and additional resolved args as `params`.
 */
const BUILT_IN_FUNCTIONS: Record<string, (value: unknown, params: unknown[]) => unknown> = {
    // Math
    SUM: (value) => {
        const arr = Array.isArray(value) ? value : [value]
        return arr.reduce((sum: number, v: unknown) => sum + (parseFloat(String(v)) || 0), 0)
    },
    MULTIPLY: (value, params) => (parseFloat(String(value)) || 0) * (parseFloat(String(params[0])) || 0),
    DIVIDE: (value, params) => {
        const divisor = parseFloat(String(params[0]))
        if (!divisor) return ''
        return (parseFloat(String(value)) || 0) / divisor
    },
    ADD: (value, params) => (parseFloat(String(value)) || 0) + (parseFloat(String(params[0])) || 0),
    SUBTRACT: (value, params) => (parseFloat(String(value)) || 0) - (parseFloat(String(params[0])) || 0),
    MOD: (value, params) => {
        const divisor = parseFloat(String(params[0]))
        if (!divisor) return ''
        return (parseFloat(String(value)) || 0) % divisor
    },
    POW: (value, params) => Math.pow(parseFloat(String(value)) || 0, parseFloat(String(params[0])) || 0),
    SQRT: (value) => {
        const num = parseFloat(String(value))
        if (num < 0) return ''
        return Math.sqrt(num)
    },
    ABS: (value) => Math.abs(parseFloat(String(value)) || 0),
    ROUND: (value, params) => {
        const decimals = parseInt(String(params[0])) || 0
        const factor = Math.pow(10, decimals)
        return Math.round((parseFloat(String(value)) || 0) * factor) / factor
    },
    CEIL: (value) => Math.ceil(parseFloat(String(value)) || 0),
    FLOOR: (value) => Math.floor(parseFloat(String(value)) || 0),
    MIN: (value, params) => {
        const values = [parseFloat(String(value)) || 0, ...params.map(p => parseFloat(String(p)) || 0)]
        return Math.min(...values)
    },
    MAX: (value, params) => {
        const values = [parseFloat(String(value)) || 0, ...params.map(p => parseFloat(String(p)) || 0)]
        return Math.max(...values)
    },
    CLAMP: (value, params) => {
        const num = parseFloat(String(value)) || 0
        const min = parseFloat(String(params[0])) || 0
        const max = parseFloat(String(params[1])) || 0
        return Math.min(Math.max(num, min), max)
    },

    // Text
    UPPER: (value) => String(value ?? '').toUpperCase(),
    LOWER: (value) => String(value ?? '').toLowerCase(),
    TRIM: (value) => String(value ?? '').trim(),
    SUBSTR: (value, params) => {
        const str = String(value ?? '')
        const start = parseInt(String(params[0])) || 0
        const length = params[1] !== undefined ? parseInt(String(params[1])) : undefined
        return length !== undefined ? str.substring(start, start + length) : str.substring(start)
    },
    REPLACE: (value, params) => String(value ?? '').replace(
        new RegExp(String(params[0]), 'g'),
        String(params[1] ?? '')
    ),
    CONCAT: (value, params) => [String(value ?? ''), ...params.map(String)].join(''),
    LEFT: (value, params) => String(value ?? '').substring(0, parseInt(String(params[0])) || 0),
    RIGHT: (value, params) => {
        const str = String(value ?? '')
        const count = parseInt(String(params[0])) || 0
        return str.substring(str.length - count)
    },
    MID: (value, params) => {
        const str = String(value ?? '')
        const start = parseInt(String(params[0])) || 0
        const length = parseInt(String(params[1])) || 0
        return str.substring(start, start + length)
    },
    LEN: (value) => String(value ?? '').length,

    // Date
    FORMAT_DATE: (value, params) => {
        const format = String(params[0] ?? 'd/m/Y')
        const date = new Date(String(value))
        if (isNaN(date.getTime())) return String(value ?? '')
        return formatDate(date, format)
    },
    DATE_ADD: (value, params) => {
        const date = new Date(String(value))
        if (isNaN(date.getTime())) return String(value ?? '')
        const amount = parseInt(String(params[0])) || 0
        const unit = String(params[1] ?? 'days')
        if (unit === 'months') date.setMonth(date.getMonth() + amount)
        else if (unit === 'years') date.setFullYear(date.getFullYear() + amount)
        else date.setDate(date.getDate() + amount)
        return date.toISOString().split('T')[0]
    },
    DATE_DIFF: (value, params) => {
        const d1 = new Date(String(value))
        const d2 = new Date(String(params[0]))
        if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return ''
        const diffMs = Math.abs(d2.getTime() - d1.getTime())
        return Math.ceil(diffMs / (1000 * 60 * 60 * 24))
    },

    // Logic
    IF: (value, params) => {
        const compare = String(params[0] ?? '')
        const trueResult = String(params[1] ?? '')
        const falseResult = String(params[2] ?? '')
        return String(value) === compare ? trueResult : falseResult
    },
    DEFAULT: (value, params) => {
        const v = String(value ?? '')
        return v === '' || v === 'null' || v === 'undefined' ? String(params[0] ?? 'N/A') : v
    },

    // Formatting
    FORMAT_NUMBER: (value, params) => {
        const num = parseFloat(String(value))
        if (isNaN(num)) return String(value ?? '')
        const decimals = parseInt(String(params[0])) || 0
        const decSep = String(params[1] ?? '.')
        const thousandsSep = String(params[2] ?? ',')
        return formatNumber(num, decimals, decSep, thousandsSep)
    },
    FORMAT_CURRENCY: (value, params) => {
        const num = parseFloat(String(value))
        if (isNaN(num)) return String(value ?? '')
        const symbol = String(params[0] ?? '$')
        const decimals = parseInt(String(params[1])) || 2
        const decSep = String(params[2] ?? '.')
        const thousandsSep = String(params[3] ?? ',')
        const formatted = formatNumber(num, decimals, decSep, thousandsSep)
        return `${symbol}${formatted}`
    },
}

function formatNumber(value: number, decimals: number, decSep: string, thousandsSep: string): string {
    const parts = value.toFixed(decimals).split('.')
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep)
    return parts.join(decSep)
}

function formatDate(date: Date, format: string): string {
    const pad = (n: number) => String(n).padStart(2, '0')
    const tokens: Record<string, string> = {
        Y: String(date.getFullYear()),
        m: pad(date.getMonth() + 1),
        d: pad(date.getDate()),
        H: pad(date.getHours()),
        i: pad(date.getMinutes()),
        s: pad(date.getSeconds()),
    }
    return format.replace(/[YmdHis]/g, c => tokens[c] ?? c)
}

export class ExpressionEvaluator {
    private functions: Record<string, (value: unknown, params: unknown[]) => unknown>

    constructor() {
        this.functions = { ...BUILT_IN_FUNCTIONS }
    }

    evaluate(node: ExprNode, resolve: ResolveFn): string {
        const result = this.visit(node, resolve)
        return this.stringify(result)
    }

    private visit(node: ExprNode, resolve: ResolveFn): unknown {
        switch (node.type) {
            case 'variable':
                return resolve(node.path)
            case 'numberLiteral':
                return node.value
            case 'stringLiteral':
                return node.value
            case 'functionCall':
                return this.visitFunctionCall(node, resolve)
            case 'binaryOp':
                return this.visitBinaryOp(node, resolve)
            case 'filterChain':
                return this.visitFilterChain(node, resolve)
        }
    }

    private visitFunctionCall(node: FunctionCallNode, resolve: ResolveFn): unknown {
        const resolvedArgs = node.args.map(arg => this.visit(arg, resolve))
        const value = resolvedArgs[0] ?? null
        const params = resolvedArgs.slice(1)

        const fn = this.functions[node.name.toUpperCase()]
        if (fn) {
            return fn(value, params)
        }

        // Unknown function — return the value as-is
        return value
    }

    private visitBinaryOp(node: BinaryOpNode, resolve: ResolveFn): unknown {
        const left = this.stringify(this.visit(node.left, resolve))
        const right = this.stringify(this.visit(node.right, resolve))

        switch (node.operator) {
            case '+':
                return left + right
            default:
                throw new Error(`Unknown operator: ${node.operator}`)
        }
    }

    private visitFilterChain(node: FilterChainNode, resolve: ResolveFn): unknown {
        let value = this.visit(node.base, resolve)

        for (const filter of node.filters) {
            const resolvedParams = filter.params.map(p => this.visit(p, resolve))
            const fn = this.functions[filter.name.toUpperCase()]
            if (fn) {
                value = fn(value, resolvedParams)
            }
        }

        return value
    }

    private stringify(value: unknown): string {
        if (value === null || value === undefined) return ''
        if (typeof value === 'number') {
            return Object.is(value, -0) ? '0' : String(value)
        }
        return String(value)
    }
}

// ── Public API ─────────────────────────────────

const tokenizer = new ExpressionTokenizer()
const parser = new ExpressionParser()
const evaluator = new ExpressionEvaluator()

const FAST_PATH = /^[\w.\[\]]+$/

/**
 * Evaluate an expression string against a data object.
 * Fast path for simple variable references (e.g. "name", "client.name", "items[].total").
 */
export function evaluate(expression: string, data: Record<string, unknown>): string {
    const trimmed = expression.trim()

    // Fast path: simple variable reference
    if (FAST_PATH.test(trimmed)) {
        const value = resolvePath(data, trimmed)
        return value !== undefined && value !== null ? stringify(value) : `{{ ${trimmed} }}`
    }

    try {
        const tokens = tokenizer.tokenize(trimmed)
        const ast = parser.parse(tokens)
        const resolve = (key: string) => resolvePath(data, key)
        const result = evaluator.evaluate(ast, resolve)
        return result !== '' ? result : `{{ ${trimmed} }}`
    } catch {
        return `{{ ${trimmed} }}`
    }
}

/**
 * Tokenize an expression string (for external use).
 */
export function tokenize(input: string): Token[] {
    return tokenizer.tokenize(input)
}

/**
 * Parse a token list into an AST (for external use).
 */
export function parse(tokens: Token[]): ExprNode {
    return parser.parse(tokens)
}

/**
 * Parse an expression string into an AST (convenience).
 */
export function parseExpression(input: string): ExprNode {
    return parser.parse(tokenizer.tokenize(input))
}

/**
 * Build an expression string from an AST node (round-trip support).
 */
export function buildExpression(node: ExprNode): string {
    switch (node.type) {
        case 'variable':
            return node.path
        case 'numberLiteral':
            return String(node.value)
        case 'stringLiteral': {
            const escaped = node.value.replace(/'/g, "\\'")
            return `'${escaped}'`
        }
        case 'functionCall': {
            const args = node.args.map(buildExpression).join(', ')
            return `${node.name}(${args})`
        }
        case 'binaryOp':
            return `${buildExpression(node.left)} ${node.operator} ${buildExpression(node.right)}`
        case 'filterChain': {
            let result = buildExpression(node.base)
            for (const filter of node.filters) {
                if (filter.params.length === 0) {
                    result += ` | ${filter.name}`
                } else {
                    const params = filter.params.map(buildExpression).join(', ')
                    result += ` | ${filter.name}(${params})`
                }
            }
            return result
        }
    }
}

/**
 * Check if an expression is a simple variable (fast path).
 */
export function isFastPath(expression: string): boolean {
    return FAST_PATH.test(expression.trim())
}

// ── Helpers ────────────────────────────────────

function resolvePath(obj: Record<string, unknown>, path: string): unknown {
    // Handle array iteration: items[].total → iterate items, get .total from each
    const arrayMatch = path.match(/^([^[.\]]+)\[\]\.?(.*)/)
    if (arrayMatch) {
        const key = arrayMatch[1]
        const rest = arrayMatch[2]
        const arr = obj[key]
        if (!Array.isArray(arr)) return undefined
        if (!rest) return arr
        return arr.map((item) => {
            if (item && typeof item === 'object') {
                return resolvePath(item as Record<string, unknown>, rest)
            }
            return undefined
        })
    }

    // Handle bracket notation: [key]
    const bracketMatch = path.match(/^\[(.+)\]$/)
    if (bracketMatch) {
        return obj[bracketMatch[1]]
    }

    // Handle dot notation: a.b.c
    const parts = path.split('.')
    let current: unknown = obj

    for (const part of parts) {
        if (current === null || current === undefined) return undefined
        if (typeof current !== 'object') return undefined
        current = (current as Record<string, unknown>)[part]
    }

    return current
}

function stringify(value: unknown): string {
    if (value === null || value === undefined) return ''
    if (typeof value === 'number') {
        return Object.is(value, -0) ? '0' : String(value)
    }
    if (Array.isArray(value)) {
        return value.map(stringify).join(', ')
    }
    return String(value)
}

// ── Re-export helpers from existing parser ─────

export { hasPipeFilters, hasConcatenationOp, isQuotedLiteral } from './expressionParser'
