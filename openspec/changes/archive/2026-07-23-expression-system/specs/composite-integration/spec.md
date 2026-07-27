# Composite Engine Integration Specification

## Purpose

Defines how the composite PDF engine (Label primitive + ReportCompiler) integrates with the new ExpressionEvaluator. This brings the composite engine to feature parity with the dompdf engine's expression capabilities.

---

## Requirements

### Requirement: Label Receives ExpressionEvaluator Injection

The `Label` primitive SHALL accept an `ExpressionEvaluator` instance via a new `setExpressionEvaluator()` method. When an evaluator is present, the Label's `interpolate()` method MUST delegate to the evaluator instead of using its own regex-based variable resolution.

The Label MUST preserve its existing `resolvePath()` and `resolveSegments()` methods for the evaluator's variable resolution callback — the evaluator uses these for `[]` array iteration semantics.

#### Scenario: Label with evaluator uses evaluator for interpolation

- GIVEN a Label with text `{{ SUM(prices[].total) }}`
- AND an ExpressionEvaluator is set via `setExpressionEvaluator()`
- AND global data `['prices' => [['total' => 10], ['total' => 20]]]`
- WHEN `interpolate()` is called
- THEN the result is `30`

#### Scenario: Label without evaluator uses legacy regex

- GIVEN a Label with text `{{ name }}`
- AND no ExpressionEvaluator is set
- AND global data `['name' => 'John']`
- WHEN `interpolate()` is called
- THEN the result is `John` (resolved via legacy regex)

#### Scenario: Label evaluator receives local data

- GIVEN a Label inside a detail band iteration
- AND an ExpressionEvaluator is set
- AND local data `['price' => 100, 'quantity' => 3]`
- AND the Label text is `{{ MULTIPLY(price, quantity) }}`
- WHEN `interpolate()` is called
- THEN the result is `300`

---

### Requirement: ReportCompiler Creates and Injects Evaluator

The `ReportCompiler` SHALL create an `ExpressionEvaluator` instance with `FunctionRegistry::defaults()` during compilation and inject it into each Label via `setExpressionEvaluator()`.

The evaluator MUST be created once per compilation cycle and shared across all Labels.

The evaluator's variable resolution callback MUST delegate to Label's `resolvePath()` and `resolveSegments()` for proper `[]` array iteration and `[N]` index resolution.

#### Scenario: Compiler injects evaluator into labels

- GIVEN a ReportCompiler compiling a template with Labels containing function expressions
- WHEN compilation runs
- THEN each Label receives the same ExpressionEvaluator instance

#### Scenario: Compiler-created evaluator has all default functions

- GIVEN a ReportCompiler creates an ExpressionEvaluator
- WHEN a Label evaluates `{{ SUM(data) }}`
- THEN the SUM function is available and produces the correct result

---

### Requirement: Composite Engine Preserves Existing Variable Resolution

The composite engine's expression evaluation MUST preserve all existing variable resolution behaviors:
- Dot-notation paths: `client.name`
- Local-first resolution: check local data before global
- `[]` array iteration: `items[].total` iterates array and resolves field
- `[N]` specific index: `items[0].total` resolves specific array element
- Scalar, array, and object values are stringified consistently

#### Scenario: Dot-notation resolution unchanged

- GIVEN a Label with text `{{ client.name }}` and an evaluator
- AND global data `['client' => ['name' => 'Acme']]`
- WHEN evaluated
- THEN the result is `Acme`

#### Scenario: Local-first resolution unchanged

- GIVEN a Label in a detail band with local data `['name' => 'Local']` and global data `['name' => 'Global']`
- AND the Label text is `{{ name }}`
- WHEN evaluated
- THEN the result is `Local`

#### Scenario: Unresolved variable returns placeholder

- GIVEN a Label with text `{{ missing }}` and an evaluator
- AND no data contains key `missing`
- WHEN evaluated
- THEN the result is `{{ missing }}` (unchanged)
