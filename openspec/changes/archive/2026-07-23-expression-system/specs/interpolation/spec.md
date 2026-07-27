# Interpolation Specification

## Purpose

Defines how the `InterpolatesVariables` trait (used by the dompdf engine) integrates with the new ExpressionEvaluator, including backward compatibility for pipe-based syntax.

---

## Requirements

### Requirement: InterpolatesVariables Delegates to ExpressionEvaluator

The `InterpolatesVariables` trait SHALL delegate expression evaluation to the `ExpressionEvaluator` when one is available. When no evaluator is set, the trait MUST fall back to its current `ExpressionParser`-based logic.

The delegation MUST produce identical results to the current implementation for all existing expressions.

#### Scenario: Trait with evaluator evaluates function syntax

- GIVEN a class using `InterpolatesVariables` with an ExpressionEvaluator set
- AND data `['price' => 100, 'quantity' => 3]`
- WHEN `interpolate("{{ MULTIPLY(price, quantity) }}", $data)` is called
- THEN the result is `300`

#### Scenario: Trait without evaluator uses legacy parser

- GIVEN a class using `InterpolatesVariables` without an evaluator set
- AND data `['name' => 'John']`
- WHEN `interpolate("{{ name }}", $data)` is called
- THEN the result is `John`

---

### Requirement: Backward Compatibility for Pipe Syntax

The system MUST continue to support existing pipe-based expression syntax. The ExpressionEvaluator SHALL parse pipe expressions (`{{ variable | filter(args) }}`) as `FilterChainNode` AST nodes and delegate to the existing Filter implementations (wrapped as functions in the FunctionRegistry).

All 44 existing `InterpolatesVariablesTest` test cases MUST pass without modification.

#### Scenario: Pipe filter still works

- GIVEN data `['price' => 1234.56]`
- WHEN `{{ price | currency("$") }}` is evaluated
- THEN the result is `$1,234.56`

#### Scenario: Chained pipe filters still work

- GIVEN data `['name' => '  hello  ']`
- WHEN `{{ name | trim | upper }}` is evaluated
- THEN the result is `HELLO`

#### Scenario: Concatenation with pipe still works

- GIVEN data `['price' => 100]`
- WHEN `{{ 'Total: ' + price | currency("$") }}` is evaluated
- THEN the result is `Total: $100.00`

#### Scenario: Literal string with escape sequences

- GIVEN data `['name' => 'John']`
- WHEN `{{ 'Hello\\nWorld' }}` is evaluated
- THEN the result contains a literal newline

#### Scenario: Bracket notation with pipe filter

- GIVEN data `['items' => ['hello', ' world ']]`
- WHEN `{{ [].items | trim | upper }}` is evaluated (or equivalent iteration)
- THEN each item is trimmed and uppercased

---

### Requirement: Interpolation Preserves Existing FilterRegistry

The `InterpolatesVariables` trait's `getFilterRegistry()` method SHALL continue to function. The ExpressionEvaluator's FunctionRegistry MUST include all existing filters (registered as legacy functions) in addition to the new math/text/date functions.

The existing 10 filters (Number, Currency, Upper, Lower, Trim, Default, DateFormat, If, Substr, Replace) MUST remain available by name for pipe-syntax expressions.

#### Scenario: Existing filter names are available

- GIVEN the ExpressionEvaluator with default FunctionRegistry
- WHEN a pipe expression uses `currency`
- THEN the `currency` function is found and applied

#### Scenario: New function names are available

- GIVEN the ExpressionEvaluator with default FunctionRegistry
- WHEN a function expression uses `SUM`
- THEN the `SUM` function is found and applied

---

### Requirement: Interpolation Fast Path

When the `InterpolatesVariables` trait detects a simple variable expression (no parens, no pipes, no operators, no string literals), it SHOULD resolve the variable directly without invoking the ExpressionEvaluator, preserving current performance for the common case.

#### Scenario: Simple variable uses fast path

- GIVEN a class using `InterpolatesVariables` with an evaluator set
- AND data `['name' => 'John']`
- WHEN `interpolate("{{ name }}", $data)` is called
- THEN the result is `John`
- AND the evaluator's tokenizer is NOT invoked

#### Scenario: Simple variable with dot path uses fast path

- GIVEN data `['client' => ['name' => 'Acme']]`
- WHEN `interpolate("{{ client.name }}", $data)` is called
- THEN the result is `Acme`
