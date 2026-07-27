# Expression Engine Specification

## Purpose

Defines the core expression evaluation pipeline: Tokenizer → Parser → Evaluator, the AST node types, the FunctionInterface contract, and the FunctionRegistry. This replaces the regex-based ExpressionParser with a proper expression engine supporting Excel-style function syntax.

---

## Requirements

### Requirement: Tokenizer — Tokenize Expression Strings

The system SHALL tokenize expression strings into a flat list of typed tokens, recognizing function calls, variables, literals, and structural delimiters.

**Token types**: `FUNCTION`, `LPAREN`, `RPAREN`, `COMMA`, `VARIABLE`, `NUMBER`, `STRING`, `OPERATOR` (pipe, plus, dot), `EOF`.

The tokenizer MUST handle:
- Identifiers: word characters and dots (e.g., `price`, `client.name`, `orders[].total`)
- Numbers: integers and decimals (e.g., `42`, `3.14`)
- Strings: single-quoted and double-quoted (e.g., `'hello'`, `"world"`)
- Structural tokens: `(`, `)`, `,`
- Operators: `|`, `+`
- The `[]` array notation in variable paths

The tokenizer MUST raise an error on unterminated strings.

#### Scenario: Tokenize simple variable

- GIVEN the expression string `name`
- WHEN tokenized
- THEN the token list is `[VARIABLE("name"), EOF]`

#### Scenario: Tokenize function call with literal args

- GIVEN the expression string `ROUND(price, 2)`
- WHEN tokenized
- THEN the token list is `[FUNCTION("ROUND"), LPAREN, VARIABLE("price"), COMMA, NUMBER(2), RPAREN, EOF]`

#### Scenario: Tokenize nested array variable

- GIVEN the expression string `orders[].total`
- WHEN tokenized
- THEN the token list contains a single `VARIABLE` token with value `orders[].total`

#### Scenario: Tokenize string with single quotes

- GIVEN the expression string `'Total: '`
- WHEN tokenized
- THEN the token list contains a `STRING` token with value `Total: `

#### Scenario: Tokenize expression with pipe operator

- GIVEN the expression string `name | upper`
- WHEN tokenized
- THEN the token list contains `[VARIABLE("name"), OPERATOR("|"), FUNCTION("upper"), EOF]`

#### Scenario: Error on unterminated string

- GIVEN the expression string `'hello`
- WHEN tokenized
- THEN the tokenizer SHALL throw a `\InvalidArgumentException` with message indicating unterminated string

---

### Requirement: Parser — Build AST from Tokens

The system SHALL parse a token list into an Abstract Syntax Tree (AST) with typed nodes.

The parser MUST produce the following AST node types:
- `FunctionCallNode` — function name + array of argument nodes
- `VariableNode` — variable path string (may contain `[]` notation)
- `NumberLiteralNode` — numeric value
- `StringLiteralNode` — string value
- `BinaryOpNode` — operator + left node + right node
- `FilterChainNode` — base expression + array of filter applications (for backward compatibility)

The parser MUST handle:
- Function calls with zero or more arguments: `SUM()`, `MULTIPLY(price, qty)`
- Nested function calls: `ROUND(MULTIPLY(price, 0.21), 2)` (stretch — v2)
- Variable references with or without paths: `name`, `client.name`, `orders[].total`
- String and number literals as standalone or function arguments
- Pipe-based filter chains for backward compatibility: `name | upper`
- Concatenation with `+`: `'prefix' + name + 'suffix'`

The parser MUST raise an error for malformed expressions (mismatched parens, unexpected tokens).

#### Scenario: Parse simple variable

- GIVEN the token list `[VARIABLE("name"), EOF]`
- WHEN parsed
- THEN the AST root is a `VariableNode` with path `name`

#### Scenario: Parse function call

- GIVEN the token list `[FUNCTION("SUM"), LPAREN, VARIABLE("prices"), RPAREN, EOF]`
- WHEN parsed
- THEN the AST root is a `FunctionCallNode` with name `SUM` and one argument `VariableNode("prices")`

#### Scenario: Parse function call with multiple arguments

- GIVEN the expression `MULTIPLY(price, quantity)`
- WHEN parsed
- THEN the AST root is a `FunctionCallNode` with name `MULTIPLY` and two argument nodes: `VariableNode("price")` and `VariableNode("quantity")`

#### Scenario: Parse string literal

- GIVEN the token list `[STRING("hello"), EOF]`
- WHEN parsed
- THEN the AST root is a `StringLiteralNode` with value `hello`

#### Scenario: Parse concatenation

- GIVEN the expression `'Total: ' + price + ' end'`
- WHEN parsed
- THEN the AST root is a `BinaryOpNode` chaining two `+` operations over three leaf nodes

#### Scenario: Parse backward-compatible pipe filter chain

- GIVEN the expression `name | upper`
- WHEN parsed
- THEN the AST root is a `FilterChainNode` containing a `VariableNode` base and one filter application for `upper`

#### Scenario: Error on mismatched parentheses

- GIVEN the expression `SUM(price`
- WHEN parsed
- THEN the parser SHALL throw a `\InvalidArgumentException` indicating mismatched parentheses

#### Scenario: Error on unexpected token

- GIVEN the expression `SUM(price, , qty)`
- WHEN parsed
- THEN the parser SHALL throw a `\InvalidArgumentException` indicating unexpected comma

---

### Requirement: Evaluator — Evaluate AST Against Data Context

The system SHALL evaluate an AST node tree against a data context and produce a string result.

The evaluator MUST:
- Resolve `VariableNode` against local data first, then global data, preserving existing bracket notation semantics (`[]` for local iteration, `[N]` for specific index)
- Call registered functions for `FunctionCallNode` via the FunctionRegistry
- Return literal values for `NumberLiteralNode` and `StringLiteralNode`
- Apply binary operations for `BinaryOpNode`
- Execute filter chains for `FilterChainNode` by delegating to the FunctionRegistry
- Return the original `{{ expression }}` placeholder unchanged if resolution fails (no throw)

The evaluator MUST accept a resolve callback (callable) for variable resolution, allowing the host (Label or InterpolatesVariables) to inject its own resolution logic.

#### Scenario: Evaluate variable node with local data

- GIVEN an AST `VariableNode("name")` and local data `['name' => 'John']`
- WHEN evaluated
- THEN the result is `John`

#### Scenario: Evaluate variable node falls back to global data

- GIVEN an AST `VariableNode("client.name")` and empty local data and global data `['client' => ['name' => 'Acme']]`
- WHEN evaluated
- THEN the result is `Acme`

#### Scenario: Evaluate function call node

- GIVEN an AST `FunctionCallNode("SUM", [VariableNode("prices")])` and data `['prices' => [10, 20, 30]]`
- WHEN evaluated
- THEN the result is `60` (or `60.0` depending on function return type)

#### Scenario: Evaluate nested function call

- GIVEN an AST `FunctionCallNode("ROUND", [FunctionCallNode("MULTIPLY", [...]), NumberLiteral(2)])`
- WHEN evaluated
- THEN the evaluator SHALL evaluate inner functions first (depth-first), then pass results to outer function

#### Scenario: Evaluate string literal

- GIVEN an AST `StringLiteralNode("hello")`
- WHEN evaluated
- THEN the result is `hello`

#### Scenario: Evaluate unresolved variable leaves placeholder

- GIVEN an AST `VariableNode("missing")` and empty data context
- WHEN evaluated
- THEN the result is the original expression string (e.g., `missing`), not an error

#### Scenario: Evaluate with custom resolve callback

- GIVEN an evaluator with a resolve callback that returns `"custom"` for key `"x"`
- AND an AST `VariableNode("x")`
- WHEN evaluated
- THEN the result is `custom`

---

### Requirement: Fast Path — Bypass AST for Simple Variables

The system SHOULD detect simple `{{ variable }}` expressions (no parens, no pipes, no operators, no string literals) and resolve them directly via regex without invoking the tokenizer or parser.

The fast path MUST produce identical results to the full pipeline for simple variable expressions.

#### Scenario: Fast path for simple variable

- GIVEN the expression `name`
- WHEN evaluated via the evaluator's fast path check
- THEN the expression is resolved directly without tokenization

#### Scenario: Fast path does not activate for function calls

- GIVEN the expression `SUM(prices)`
- WHEN the evaluator checks for fast path
- THEN the evaluator SHALL use the full tokenizer → parser → evaluator pipeline

#### Scenario: Fast path does not activate for pipe expressions

- GIVEN the expression `name | upper`
- WHEN the evaluator checks for fast path
- THEN the evaluator SHALL use the full tokenizer → parser → evaluator pipeline

---

### Requirement: FunctionInterface — Contract for All Functions

The system SHALL define a `FunctionInterface` that all expression functions (math, text, date, logic, formatting) MUST implement.

The interface MUST define:
- `name(): string` — the function name used in expressions (uppercase, e.g., `SUM`, `MULTIPLY`)
- `apply(mixed $value, array $params, callable $resolve): mixed` — the function implementation

The `$resolve` callback MUST allow functions to resolve variable references within their arguments (e.g., `MULTIPLY(price, qty)` resolves `price` and `qty` before the function applies).

#### Scenario: Function returns its name

- GIVEN a concrete function implementing `FunctionInterface`
- WHEN `name()` is called
- THEN it returns the uppercase function name

#### Scenario: Function receives resolved values

- GIVEN a function `MULTIPLY` with `apply($value, $params, $resolve)`
- AND params `[VariableNode("price"), VariableNode("qty")]`
- WHEN `apply` is called
- THEN `$resolve` is available and the function receives the resolved values (or the nodes to resolve internally)

---

### Requirement: FunctionRegistry — Store and Retrieve Functions

The system SHALL provide a `FunctionRegistry` that stores function implementations by name and serves as the lookup mechanism for the evaluator.

The registry MUST:
- Register functions by name (case-insensitive lookup)
- Provide a `defaults()` factory method returning a registry pre-loaded with all built-in functions
- Throw an `\InvalidArgumentException` when a function name is not found
- Support listing all registered function names (for UI display)

#### Scenario: Register and retrieve function

- GIVEN a FunctionRegistry instance
- WHEN a function implementing `FunctionInterface` is registered with name `SUM`
- AND `get("sum")` is called
- THEN the registered function instance is returned

#### Scenario: Error on unknown function

- GIVEN a FunctionRegistry instance
- WHEN `get("UNKNOWN")` is called
- THEN an `\InvalidArgumentException` is thrown

#### Scenario: List all function names

- GIVEN a FunctionRegistry with `SUM`, `MULTIPLY`, `UPPER` registered
- WHEN `names()` is called
- THEN the result includes all three names

#### Scenario: Defaults factory includes all built-in functions

- GIVEN `FunctionRegistry::defaults()` is called
- WHEN `names()` is called on the result
- THEN it includes math functions (SUM, MULTIPLY, DIVIDE, ROUND, ABS, MIN, MAX, AVG, POW, SQRT, CEIL, FLOOR, CLAMP, MOD)
- AND it includes text functions (UPPER, LOWER, TRIM, CONCAT, LEFT, RIGHT, MID, LEN, REPLACE, IF)
- AND it includes date functions (DATE_FORMAT, DATE_ADD, DATE_DIFF)
- AND it includes formatting functions (FORMAT_CURRENCY, FORMAT_NUMBER)
