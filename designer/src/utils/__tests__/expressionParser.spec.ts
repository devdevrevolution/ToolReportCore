// ──────────────────────────────────────────────
// Toolreport Designer — Expression Evaluator Unit Tests
// Tests for tokenizer, parser, evaluator, and round-trip.
// Mirrors tests/Unit/Expression/ tests on the PHP side.
// ──────────────────────────────────────────────

import { describe, it, expect } from 'vitest'
import {
    tokenize,
    parseExpression,
    evaluate,
    buildExpression,
    isFastPath,
} from '../ExpressionEvaluator'
import type { FunctionCallNode, VariableNode, BinaryOpNode } from '../ExpressionEvaluator'

// ── Tokenizer Tests ────────────────────────────

describe('ExpressionTokenizer', () => {
    it('tokenizes a simple variable', () => {
        const tokens = tokenize('name')
        expect(tokens).toHaveLength(2) // name + EOF
        expect(tokens[0].type).toBe('VARIABLE')
        expect(tokens[0].value).toBe('name')
        expect(tokens[1].type).toBe('EOF')
    })

    it('tokenizes a function call', () => {
        const tokens = tokenize('SUM(values)')
        expect(tokens[0].type).toBe('FUNCTION')
        expect(tokens[0].value).toBe('SUM')
        expect(tokens[1].type).toBe('LPAREN')
        expect(tokens[2].type).toBe('VARIABLE')
        expect(tokens[2].value).toBe('values')
        expect(tokens[3].type).toBe('RPAREN')
        expect(tokens[4].type).toBe('EOF')
    })

    it('tokenizes nested functions', () => {
        const tokens = tokenize('ROUND(MULTIPLY(price, 0.21), 2)')
        // ROUND FUNCTION, LPAREN, MULTIPLY FUNCTION, LPAREN, price VAR, COMMA, 0.21 NUM, RPAREN, COMMA, 2 NUM, RPAREN, EOF
        expect(tokens[0].type).toBe('FUNCTION')
        expect(tokens[0].value).toBe('ROUND')
        expect(tokens[2].type).toBe('FUNCTION')
        expect(tokens[2].value).toBe('MULTIPLY')
        expect(tokens[11].type).toBe('EOF')
    })

    it('tokenizes a string literal (double-quoted)', () => {
        const tokens = tokenize('"hello"')
        expect(tokens[0].type).toBe('STRING')
        expect(tokens[0].value).toBe('hello')
        expect(tokens[1].type).toBe('EOF')
    })

    it('tokenizes a string literal (single-quoted)', () => {
        const tokens = tokenize("'hello'")
        expect(tokens[0].type).toBe('STRING')
        expect(tokens[0].value).toBe('hello')
        expect(tokens[1].type).toBe('EOF')
    })

    it('tokenizes a number literal', () => {
        const tokens = tokenize('42')
        expect(tokens[0].type).toBe('NUMBER')
        expect(tokens[0].value).toBe('42')
        expect(tokens[1].type).toBe('EOF')
    })

    it('tokenizes a decimal number', () => {
        const tokens = tokenize('3.14')
        expect(tokens[0].type).toBe('NUMBER')
        expect(tokens[0].value).toBe('3.14')
    })

    it('tokenizes pipe operator', () => {
        const tokens = tokenize('name | upper')
        expect(tokens[0].type).toBe('VARIABLE')
        expect(tokens[1].type).toBe('OPERATOR')
        expect(tokens[1].value).toBe('|')
        // upper without parens is VARIABLE (no lookahead '(')
        expect(tokens[2].type).toBe('VARIABLE')
        expect(tokens[2].value).toBe('upper')
    })

    it('tokenizes concatenation operator', () => {
        const tokens = tokenize("'Hello ' + name")
        expect(tokens[0].type).toBe('STRING')
        expect(tokens[1].type).toBe('OPERATOR')
        expect(tokens[1].value).toBe('+')
        expect(tokens[2].type).toBe('VARIABLE')
    })
})

// ── Parser Tests ───────────────────────────────

describe('ExpressionParser', () => {
    it('parses a function call AST', () => {
        const ast = parseExpression('SUM(values)')
        expect(ast.type).toBe('functionCall')
        const node = ast as FunctionCallNode
        expect(node.name).toBe('SUM')
        expect(node.args).toHaveLength(1)
        expect(node.args[0].type).toBe('variable')
        expect((node.args[0] as VariableNode).path).toBe('values')
    })

    it('parses a binary operation AST', () => {
        const ast = parseExpression("'Hello ' + name")
        expect(ast.type).toBe('binaryOp')
        const node = ast as BinaryOpNode
        expect(node.operator).toBe('+')
        expect(node.left.type).toBe('stringLiteral')
        expect(node.right.type).toBe('variable')
    })

    it('parses nested function AST', () => {
        const ast = parseExpression('ROUND(MULTIPLY(price, 0.21), 2)')
        expect(ast.type).toBe('functionCall')
        const outer = ast as FunctionCallNode
        expect(outer.name).toBe('ROUND')
        expect(outer.args).toHaveLength(2)

        // First arg is a nested function call
        expect(outer.args[0].type).toBe('functionCall')
        const inner = outer.args[0] as FunctionCallNode
        expect(inner.name).toBe('MULTIPLY')
        expect(inner.args).toHaveLength(2)

        // Second arg is a number literal
        expect(outer.args[1].type).toBe('numberLiteral')
    })

    it('parses a variable AST', () => {
        const ast = parseExpression('client.name')
        expect(ast.type).toBe('variable')
        expect((ast as VariableNode).path).toBe('client.name')
    })

    it('parses a string literal AST', () => {
        const ast = parseExpression('"hello world"')
        expect(ast.type).toBe('stringLiteral')
    })

    it('parses a number literal AST', () => {
        const ast = parseExpression('42')
        expect(ast.type).toBe('numberLiteral')
    })

    it('parses pipe filter chain', () => {
        const ast = parseExpression('name | upper | trim')
        expect(ast.type).toBe('filterChain')
    })
})

// ── Evaluator Tests ────────────────────────────

describe('ExpressionEvaluator', () => {
    it('evaluates a simple variable with data', () => {
        const result = evaluate('name', { name: 'John' })
        expect(result).toBe('John')
    })

    it('evaluates SUM with array', () => {
        const result = evaluate('SUM(values)', { values: [10, 20, 30] })
        expect(result).toBe('60')
    })

    it('evaluates nested functions', () => {
        const result = evaluate('ROUND(MULTIPLY(price, 0.21), 2)', { price: 100 })
        expect(result).toBe('21')
    })

    it('evaluates IF function', () => {
        const result = evaluate('IF(status, "active", "Active", "Inactive")', { status: 'active' })
        expect(result).toBe('Active')
    })

    it('evaluates IF function (false branch)', () => {
        const result = evaluate('IF(status, "active", "Active", "Inactive")', { status: 'inactive' })
        expect(result).toBe('Inactive')
    })

    it('evaluates concatenation', () => {
        const result = evaluate("'Hello ' + name", { name: 'John' })
        expect(result).toBe('Hello John')
    })

    it('evaluates pipe filter', () => {
        const result = evaluate('name | upper', { name: 'John' })
        expect(result).toBe('JOHN')
    })

    it('evaluates chained pipe filters', () => {
        const result = evaluate('name | trim | upper', { name: '  john  ' })
        expect(result).toBe('JOHN')
    })

    it('evaluates UPPER function', () => {
        const result = evaluate('UPPER(name)', { name: 'hello' })
        expect(result).toBe('HELLO')
    })

    it('evaluates ADD function', () => {
        const result = evaluate('ADD(a, b)', { a: 10, b: 5 })
        expect(result).toBe('15')
    })

    it('evaluates DEFAULT function with null', () => {
        const result = evaluate('DEFAULT(phone, "N/A")', { phone: null })
        expect(result).toBe('N/A')
    })

    it('evaluates DEFAULT function with value', () => {
        const result = evaluate('DEFAULT(phone, "N/A")', { phone: '555-1234' })
        expect(result).toBe('555-1234')
    })

    it('returns fallback for unknown function', () => {
        // Unknown function should return the value of the first arg as-is
        const result = evaluate('UNKNOWN_FUNC(x)', { x: 'test' })
        expect(result).toBe('test')
    })

    it('evaluates simple variable with nested path', () => {
        const data = { client: { name: 'Acme Corp' } }
        const result = evaluate('client.name', data)
        expect(result).toBe('Acme Corp')
    })

    it('evaluates array iteration with SUM', () => {
        const data = { items: [{ total: 10 }, { total: 20 }, { total: 30 }] }
        const result = evaluate('SUM(items[].total)', data)
        expect(result).toBe('60')
    })

    it('evaluates ROUND function', () => {
        const result = evaluate('ROUND(value, 2)', { value: 3.14159 })
        expect(result).toBe('3.14')
    })

    it('evaluates MULTIPLY function', () => {
        const result = evaluate('MULTIPLY(price, qty)', { price: 12.5, qty: 4 })
        expect(result).toBe('50')
    })
})

// ── Round-trip Test ────────────────────────────

describe('Round-trip', () => {
    it('tokenize → parse → evaluate → result for simple variable', () => {
        const expression = 'name'
        const result = evaluate(expression, { name: 'John' })
        expect(result).toBe('John')
    })

    it('tokenize → parse → evaluate → result for function call', () => {
        const expression = 'SUM(values)'
        const result = evaluate(expression, { values: [1, 2, 3] })
        expect(result).toBe('6')
    })

    it('tokenize → parse → evaluate → result for nested functions', () => {
        const expression = 'ROUND(MULTIPLY(price, 0.21), 2)'
        const result = evaluate(expression, { price: 100 })
        expect(result).toBe('21')
    })

    it('tokenize → parse → buildExpression round-trip for variable', () => {
        const expression = 'name'
        const ast = parseExpression(expression)
        const rebuilt = buildExpression(ast)
        expect(rebuilt).toBe(expression)
    })

    it('tokenize → parse → buildExpression round-trip for function call', () => {
        const expression = 'SUM(values)'
        const ast = parseExpression(expression)
        const rebuilt = buildExpression(ast)
        expect(rebuilt).toBe(expression)
    })
})

// ── Error Handling Tests ────────────────────────

describe('Error handling', () => {
    it('returns fallback for unterminated expression (graceful)', () => {
        // Unterminated string should be caught by tokenizer and return fallback
        const result = evaluate('"hello', {})
        expect(result).toBe('{{ "hello }}')
    })

    it('returns fallback for syntax error (graceful)', () => {
        // Missing closing paren should be caught by parser
        const result = evaluate('SUM(values', { values: [1] })
        expect(result).toBe('{{ SUM(values }}')
    })

    it('returns fallback for completely invalid expression', () => {
        const result = evaluate('((((', {})
        expect(result).toBe('{{ (((( }}')
    })
})

// ── isFastPath Tests ──────────────────────────

describe('isFastPath', () => {
    it('returns true for simple variable', () => {
        expect(isFastPath('name')).toBe(true)
    })

    it('returns true for nested path', () => {
        expect(isFastPath('client.name')).toBe(true)
    })

    it('returns true for array iteration', () => {
        expect(isFastPath('orders[].total')).toBe(true)
    })

    it('returns false for function call', () => {
        expect(isFastPath('SUM(values)')).toBe(false)
    })

    it('returns false for string literal', () => {
        expect(isFastPath('"hello"')).toBe(false)
    })

    it('returns false for pipe expression', () => {
        expect(isFastPath('name | upper')).toBe(false)
    })
})
