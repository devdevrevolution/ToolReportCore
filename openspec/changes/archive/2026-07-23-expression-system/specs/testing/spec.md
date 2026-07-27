# Testing Specification

## Purpose

Defines the testing requirements for the expression system change: PHP unit tests for the backend and Vitest component tests for the frontend.

---

## Requirements

### Requirement: Tokenizer Unit Tests

The system SHALL include `tests/Unit/Expression/TokenizerTest.php` with tests for the tokenizer.

Tests MUST cover:
- Tokenizing simple variables
- Tokenizing function calls with zero, one, and multiple arguments
- Tokenizing string literals (single and double quoted)
- Tokenizing number literals (integers and decimals)
- Tokenizing operators (`|`, `+`)
- Tokenizing bracket notation (`[]`, `[0]`)
- Error on unterminated strings
- Error on invalid characters

#### Scenario: Tokenizer test — simple variable

- GIVEN the tokenizer test class
- WHEN a test tokenizes `name`
- THEN the test asserts tokens are `[VARIABLE("name"), EOF]`

#### Scenario: Tokenizer test — function call

- GIVEN the tokenizer test class
- WHEN a test tokenizes `SUM(1, 2, 3)`
- THEN the test asserts the correct token sequence

---

### Requirement: ExpressionEvaluator Unit Tests

The system SHALL include `tests/Unit/Expression/ExpressionEvaluatorTest.php` with tests for the full evaluation pipeline.

Tests MUST cover:
- Evaluating simple variables (fast path)
- Evaluating function calls (all categories)
- Evaluating nested function calls
- Evaluating concatenation
- Evaluating pipe syntax (backward compat)
- Evaluating unresolved variables (returns placeholder)
- Evaluating with empty data context
- Evaluating with local vs global data precedence

#### Scenario: Evaluator test — SUM with array

- GIVEN the evaluator test class
- WHEN evaluating `{{ SUM(prices) }}` with data `['prices' => [10, 20, 30]]`
- THEN the test asserts the result is `60`

#### Scenario: Evaluator test — backward compat pipe

- GIVEN the evaluator test class
- WHEN evaluating `{{ name | upper }}` with data `['name' => 'john']`
- THEN the test asserts the result is `JOHN`

#### Scenario: Evaluator test — unresolved variable

- GIVEN the evaluator test class
- WHEN evaluating `{{ missing }}` with empty data
- THEN the test asserts the result is `missing` (unchanged)

---

### Requirement: Math Function Unit Tests

The system SHALL include `tests/Unit/Expression/FunctionMathTest.php` with tests for all math functions.

Tests MUST cover:
- Each math function with valid inputs
- Edge cases: zero values, negative values, empty arrays
- Error cases: division by zero, sqrt of negative
- Type coercion: string numbers, null values

#### Scenario: Math function test — SUM edge cases

- GIVEN the math function test class
- WHEN testing SUM with `[]` (empty array)
- THEN the test asserts result is `0`
- WHEN testing SUM with `[null, 10, null]`
- THEN the test asserts result is `10`

#### Scenario: Math function test — DIVIDE by zero

- GIVEN the math function test class
- WHEN testing DIVIDE with `(10, 0)`
- THEN the test asserts result is an empty string

---

### Requirement: Text Function Unit Tests

The system SHALL include tests for all text functions.

Tests MUST cover:
- Each text function with valid inputs
- Edge cases: empty strings, null values
- Multi-byte string handling (if applicable)

#### Scenario: Text function test — UPPER with empty string

- GIVEN the text function test class
- WHEN testing UPPER with `''`
- THEN the test asserts result is `''`

#### Scenario: Text function test — CONCAT with null

- GIVEN the text function test class
- WHEN testing CONCAT with `('hello', null)`
- THEN the test asserts result is `hello`

---

### Requirement: InterpolatesVariables Backward Compatibility

The system MUST ensure all 44 existing `InterpolatesVariablesTest` tests pass without modification after the expression system change.

This is a blocking requirement — if any existing test fails, the change MUST NOT be merged.

#### Scenario: All existing InterpolatesVariables tests pass

- GIVEN the InterpolatesVariablesTest test class with 44 test methods
- WHEN the test suite runs after the expression system integration
- THEN all 44 tests pass with identical results

---

### Requirement: Frontend Vitest Tests

The system SHALL include Vitest tests for:
- `expressionParser.ts` — parser and builder functions
- `FunctionsModal.vue` — rendering, search, insert behavior
- `ExpressionEditor.vue` — rendering, autocomplete, emit behavior
- `functionDefinitions.ts` — metadata completeness

#### Scenario: Frontend parser test — round-trip

- GIVEN a Vitest test for the expression parser
- WHEN a function expression is parsed, built, and parsed again
- THEN the resulting ASTs are equivalent

#### Scenario: Frontend modal test — renders all functions

- GIVEN a Vitest test for FunctionsModal
- WHEN the component is mounted
- THEN the rendered output contains all 25+ function names

#### Scenario: Frontend definitions test — complete metadata

- GIVEN a Vitest test for functionDefinitions
- WHEN the definitions array is checked
- THEN every entry has name, category, signature, description, and params
