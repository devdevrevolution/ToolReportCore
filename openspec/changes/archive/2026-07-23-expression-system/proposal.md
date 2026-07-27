# Proposal: expression-system

## Intent

The composite PDF engine (the PRIMARY engine for precise layouts) has **zero expression support** — it resolves only plain `{{ variable }}` via regex. Meanwhile, the dompdf engine has full pipe-syntax expression parsing with 10 filters. Users cannot use math operations (`SUM`, `MULTIPLY`, `DIVIDE`), concatenation, or any filter in the composite engine.

This change replaces the pipe-based syntax with Excel-style function syntax, adds math operations to the expression system, and brings the composite engine to feature parity with the dompdf engine — then beyond it with a richer function set.

**Why now**: v0.1.6 is early stage — a breaking syntax change is acceptable. The composite engine is the primary renderer going forward; it cannot launch without expression support.

## Scope

### In Scope

1. **New `ExpressionEvaluator`** — tokenizer → AST → evaluator pipeline replacing the current regex-based interpolation
2. **Excel-style function syntax** — `SUM(prices[].total)`, `MULTIPLY(price, qty)`, `ROUND(total, 2)` instead of `{{ price | sum }}`
3. **Math functions** — SUM, MULTIPLY, DIVIDE, ROUND, ABS, MIN, MAX, AVG, POW, SQRT, CEIL, FLOOR, CLAMP, MOD
4. **Text functions** — UPPER, LOWER, TRIM, CONCAT, LEFT, RIGHT, MID, LEN, REPLACE, IF
5. **Date functions** — DATE_FORMAT, DATE_ADD, DATE_DIFF
6. **FunctionRegistry** — replaces FilterRegistry, holds both math + text + date functions
7. **Composite engine integration** — Label gets ExpressionEvaluator injection, same capabilities as dompdf
8. **Backward compatibility layer** — pipe syntax `{{ x | filter(args) }}` parsed as legacy path, deprecated but functional
9. **Fast path** — simple `{{ variable }}` bypasses AST, direct regex resolution
10. **Functions modal** — UI panel showing available functions with drag-to-insert
11. **Expression editor** — syntax highlighting, autocomplete for function names and variable paths
12. **LabelProperty.vue integration** — button to launch expression builder for label text fields
13. **Frontend TypeScript parser** — mirrors backend tokenizer/AST for round-trip support

### Out of Scope

- **Custom user functions** — no runtime function registration API (future)
- **Nested function calls in v1** — `SUM(MULTIPLY(a, b), c)` is stretch, defer to v2
- **Cell references** — `{{ A1 + B2 }}` Excel-style cell grid (future)
- **Conditional expressions** — `IF(condition, then, else)` as standalone (already exists as filter, keep as-is for v1)
- **Performance optimization beyond fast path** — AST evaluation is sufficient for PDF label rendering (not real-time)

## Approach

### Architecture: Unified ExpressionEvaluator

```
Expression String
       ↓
   Tokenizer  →  Token[]
       ↓
     Parser   →  AST (FunctionCall | Variable | Literal | BinaryOp | FilterChain)
       ↓
   Evaluator  →  string result
```

**Key design decisions:**

1. **Tokenizer** splits input into tokens: `NUMBER`, `STRING`, `IDENTIFIER`, `OPERATOR`, `LPAREN`, `RPAREN`, `COMMA`, `DOT`, `PLUS`, `PIPE`
2. **Parser** builds AST nodes: `FunctionCall(name, args[])`, `Variable(path)`, `StringLiteral(value)`, `NumberLiteral(value)`, `BinaryOp(left, op, right)`, `FilterChain(expression, filters[])`
3. **Evaluator** walks AST, resolves variables against local+global data, calls registered functions
4. **FunctionRegistry** holds all functions — both legacy filters and new math/text/date functions. Each function implements `FunctionInterface::name()` + `apply(mixed $value, array $params): mixed`
5. **Backward compat** — pipe syntax `{{ x | filter(args) }}` is parsed into `FilterChain(Variable(x), [filter(args)])` — existing expressions still work
6. **Fast path** — before tokenizing, check if expression is a simple `{{ variable }}` (no parens, no pipes, no operators). If so, resolve directly without AST

### Syntax Examples

```
{{ variable }}                          → plain variable (fast path)
{{ SUM(prices[].total) }}               → sum array field
{{ MULTIPLY(price, qty) }}              → multiply two variables
{{ ROUND(SUM(totals), 2) }}             → nested functions (stretch)
{{ name | upper }}                      → deprecated pipe syntax (still works)
{{ 'Total: ' + price | currency('$') }}→ legacy concatenation (still works)
```

### Integration Points

- **Label** (composite): `setExpressionEvaluator(ExpressionEvaluator $evaluator)` method. `interpolate()` delegates to evaluator instead of regex. Receives evaluator from `ReportCompiler`.
- **InterpolatesVariables** (dompdf): `interpolate()` delegates to same `ExpressionEvaluator` instance. Existing `resolveVariableKey()` logic preserved as fallback.
- **ReportCompiler**: creates `ExpressionEvaluator` with `FunctionRegistry::defaults()`, injects into Labels.
- **Frontend**: `expressionParser.ts` updated to tokenize + parse new syntax. `ExpressionBuilder.vue` rebuilt for function call UI.

### Migration Strategy

1. New `ExpressionEvaluator` created alongside existing code (no disruption)
2. Composite Label gets evaluator injection (additive)
3. InterpolatesVariables delegates to evaluator (replaces ExpressionParser calls)
4. Old `ExpressionParser` deprecated, kept for reference, removed in next minor version
5. Frontend updated in parallel — same syntax, same grammar

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/Expression/ExpressionEvaluator.php` | New | Core tokenizer → AST → evaluator pipeline |
| `src/Expression/Tokenizer.php` | New | Expression tokenizer |
| `src/Expression/Ast/Node.php` | New | AST node base class + concrete nodes |
| `src/Expression/Ast/FunctionCallNode.php` | New | Function call AST node |
| `src/Expression/Ast/VariableNode.php` | New | Variable reference AST node |
| `src/Expression/Ast/LiteralNode.php` | New | String/number literal AST node |
| `src/Expression/Ast/BinaryOpNode.php` | New | Binary operation AST node |
| `src/Expression/Ast/FilterChainNode.php` | New | Legacy filter chain AST node |
| `src/Expression/FunctionRegistry.php` | New | Replaces FilterRegistry, holds all functions |
| `src/Expression/Function/FunctionInterface.php` | New | Contract for all functions |
| `src/Expression/Function/Math/*.php` | New | ~14 math functions (SUM, MULTIPLY, etc.) |
| `src/Expression/Function/Text/*.php` | New | ~10 text functions (UPPER, LOWER, etc.) |
| `src/Expression/Function/Date/*.php` | New | ~3 date functions (DATE_FORMAT, etc.) |
| `src/Expression/FilterRegistry.php` | Deprecated | Wrapped by FunctionRegistry for backward compat |
| `src/Expression/Filter/*.php` | Deprecated | Wrapped as legacy functions |
| `src/Expression/ExpressionParser.php` | Deprecated | Superseded by Tokenizer + Parser |
| `src/Layout/InterpolatesVariables.php` | Modified | Delegates to ExpressionEvaluator |
| `src/Modules/PdfEngine/Primitives/Label.php` | Modified | Gets ExpressionEvaluator injection |
| `src/Modules/PdfEngine/Engine/ReportCompiler.php` | Modified | Creates and injects evaluator |
| `designer/src/utils/expressionParser.ts` | Modified | New tokenizer + parser for function syntax |
| `designer/src/utils/functionDefinitions.ts` | New | UI metadata for all functions |
| `designer/src/components/modals/FunctionsModal.vue` | New | Functions reference panel |
| `designer/src/components/modals/ExpressionBuilder.vue` | Modified | Rebuilt for function call UI |
| `designer/src/components/layout/properties/LabelProperty.vue` | Modified | Expression builder button |
| `tests/Unit/Expression/ExpressionEvaluatorTest.php` | New | Core evaluator tests |
| `tests/Unit/Expression/TokenizerTest.php` | New | Tokenizer tests |
| `tests/Unit/Expression/FunctionMathTest.php` | New | Math function tests |
| `tests/Unit/InterpolatesVariablesTest.php` | Modified | Add function-syntax tests |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Breaking existing 44 InterpolatesVariables tests | High | Backward compat layer: pipe syntax parsed as FilterChain. Run full test suite after each integration step. |
| Performance regression for simple `{{ variable }}` | Medium | Fast path: regex check before tokenizer. Measure with benchmark. If slower, cache compiled expressions. |
| Backend PHP + frontend TS parsers drift | Medium | Share grammar definition (comment header). Write round-trip tests: parse in PHP → build string → parse in TS → compare AST. |
| Label's own `resolvePath()`/`resolveSegments()` conflicts with evaluator's variable resolution | Medium | Label delegates variable resolution to evaluator but keeps `[]` iteration semantics as a custom resolver passed to evaluator. |
| Composite engine integration breaks PDF rendering | Low | Label has 20 existing tests. Add expression-specific tests before integration. Test with real template data. |
| Frontend ExpressionBuilder.vue rewrite scope creep | Low | Define minimal UI first (function list + input), add autocomplete in follow-up. |

## Rollback Plan

1. **Phase 1 (before integration)**: New files only — delete `src/Expression/` new directory to revert
2. **Phase 2 (Label integration)**: Remove `setExpressionEvaluator()` call from Label, revert `interpolate()` to regex
3. **Phase 3 (InterpolatesVariables)**: Remove evaluator delegation, restore ExpressionParser calls
4. **Phase 4 (frontend)**: `git checkout` the designer files
5. **Full rollback**: `git revert` the merge commit(s). Old ExpressionParser, FilterRegistry, and Label regex are preserved until explicitly removed.

All phases are additive — no existing code is deleted until the next minor version.

## Dependencies

- PHP 8.3+ (already required)
- No new Composer dependencies
- No new npm dependencies (TypeScript parser is hand-written, same as current)

## Success Criteria

- [ ] All 44 existing InterpolatesVariables tests pass without modification
- [ ] All 20 existing Label tests pass without modification
- [ ] New ExpressionEvaluator tests pass (tokenizer, parser, evaluator, fast path)
- [ ] `{{ SUM(prices[].total) }}` resolves correctly in composite engine
- [ ] `{{ MULTIPLY(price, qty) }}` resolves correctly in both engines
- [ ] `{{ name | upper }}` (deprecated pipe syntax) still works in both engines
- [ ] Frontend parser can round-trip: parse → build → parse produces identical AST
- [ ] FunctionsModal shows all available functions with signatures
- [ ] LabelProperty.vue has working expression builder button
- [ ] No performance regression for simple `{{ variable }}` (fast path benchmark)
- [ ] Composite engine generates correct PDF with function expressions in labels
