# Tasks: Expression System

## Phase 1: Core Types & Tokenizer

- [x] 1.1 Create `src/Expression/TokenType.php` — PHP 8.3 enum with cases: `FUNCTION`, `VARIABLE`, `NUMBER`, `STRING`, `LPAREN`, `RPAREN`, `COMMA`, `OPERATOR`, `EOF` (~30 lines)
- [x] 1.2 Create `src/Expression/Token.php` — readonly class with `TokenType $type`, `string $value`, `int $position` (~15 lines)
- [x] 1.3 Create `src/Expression/Tokenizer.php` — state machine: `tokenize(string $input): Token[]`. Regex patterns per state: INITIAL → IN_VARIABLE (`/[a-zA-Z_]\w*(?:\[\])?(?:\.\w+(?:\[\])?)*/`), IN_NUMBER (`/\d+(?:\.\d+)?/`), IN_STRING (`/'[^']*'/` or `/"[^"]*"/`), OPERATOR (`/\|/` or `/\+/`), LPAREN/RPAREN/COMMA. One-token lookahead for FUNCTION vs VARIABLE (check next non-whitespace char for `(`). Error handling: unterminated string throws `\InvalidArgumentException` with position (~180 lines)
- [x] 1.4 Create `tests/Unit/Expression/TokenizerTest.php` — ~15 scenarios: single variable, function call, number literal, string literal, operator, parenthesized expression, nested parens, concatenated expression, pipe chain, whitespace handling, unterminated string error, position tracking. Run: `vendor/bin/phpunit tests/Unit/Expression/TokenizerTest.php` (~120 lines)

## Phase 2: AST Nodes

- [x] 2.1 Create `src/Expression/Ast/ExpressionNode.php` — abstract base class with `abstract public function accept(Evaluator $evaluator): mixed` (~15 lines)
- [x] 2.2 Create `src/Expression/Ast/VariableNode.php` — extends `ExpressionNode`, `string $path`, `accept()` calls `$evaluator->visitVariable($this)` (~20 lines)
- [x] 2.3 Create `src/Expression/Ast/NumberLiteralNode.php` — extends `ExpressionNode`, `float $value`, `accept()` calls `$evaluator->visitNumberLiteral($this)` (~20 lines)
- [x] 2.4 Create `src/Expression/Ast/StringLiteralNode.php` — extends `ExpressionNode`, `string $value`, `accept()` calls `$evaluator->visitStringLiteral($this)` (~20 lines)
- [x] 2.5 Create `src/Expression/Ast/FunctionCallNode.php` — extends `ExpressionNode`, `string $name`, `ExpressionNode[] $args`, `accept()` calls `$evaluator->visitFunctionCall($this)` (~25 lines)
- [x] 2.6 Create `src/Expression/Ast/BinaryOpNode.php` — extends `ExpressionNode`, `ExpressionNode $left`, `string $operator`, `ExpressionNode $right`, `accept()` calls `$evaluator->visitBinaryOp($this)` (~25 lines)
- [x] 2.7 Create `src/Expression/Ast/FilterApplication.php` — value object: `string $name`, `ExpressionNode[] $params` (~15 lines)
- [x] 2.8 Create `src/Expression/Ast/FilterChainNode.php` — extends `ExpressionNode`, `ExpressionNode $base`, `FilterApplication[] $filters`, `accept()` calls `$evaluator->visitFilterChain($this)` (~25 lines)

**Dependencies**: Phase 1 (Token types used by parser, not by AST nodes — AST nodes are standalone). Phase 1.1–1.3 must be complete before Phase 3 (Parser).

**Estimated lines**: ~165 new PHP

## Phase 3: Parser

- [x] 3.1 Create `src/Expression/Parser.php` — recursive descent parser: `parse(Token[]): ExpressionNode`. Two precedence levels: expression (pipe/concatenation) and primary (function calls, variables, literals). Grammar: `expression = primary (('|' functionCall) | ('+' primary))*`, `primary = functionCall | variable | numberLiteral | stringLiteral | '(' expression ')'`, `functionCall = IDENTIFIER '(' argumentList? ')'`. Error handling: mismatched parens, unexpected token, trailing comma. (~200 lines)
- [x] 3.2 Create `tests/Unit/Expression/ParserTest.php` — ~12 scenarios: simple variable, function call, function with multiple args, string literal, number literal, binary concatenation, pipe chain, nested function calls, parenthesized sub-expression, precedence (pipe binds tighter than +), error on mismatched parens, error on unexpected token. Run: `vendor/bin/phpunit tests/Unit/Expression/ParserTest.php` (~150 lines)

**Dependencies**: Phase 1 (Tokenizer + Token types), Phase 2 (AST nodes).

**Estimated lines**: ~350 new PHP

## Phase 4: Function Interface & Registry

- [x] 4.1 Create `src/Expression/FunctionInterface.php` — contract: `name(): string`, `apply(mixed $value, array $params, callable $resolve): mixed` (~20 lines)
- [x] 4.2 Create `src/Expression/LegacyFilterAdapter.php` — wraps `FilterInterface` as `FunctionInterface`. `apply()` delegates to `$this->filter->apply($value, $params)` (ignores `$resolve`). (~25 lines)
- [x] 4.3 Create `src/Expression/FunctionRegistry.php` — `register(FunctionInterface)`, `get(string): FunctionInterface` (case-insensitive), `has(string): bool`, `names(): list<string>`, `static defaults(): self` (registers all legacy filters via LegacyFilterAdapter + new math/text/date/formatting functions). (~120 lines)

**Dependencies**: None (standalone). Can be built in parallel with Phases 1–3.

**Estimated lines**: ~165 new PHP

## Phase 5: Evaluator

- [x] 5.1 Create `src/Expression/Evaluator.php` — visitor pattern. `__construct(FunctionRegistry)`. Methods: `evaluate(ExpressionNode, callable $resolve): string`, `visitFunctionCall()`, `visitVariable()`, `visitNumberLiteral()`, `visitStringLiteral()`, `visitBinaryOp()`, `visitFilterChain()`. Fast path: `isFastPath(string): bool` using `/^[\w.\[\]]+$/`. `evaluateExpression(string, callable): string` is the public entry point. (~200 lines)
- [x] 5.2 Create `src/Expression/ExpressionEvaluator.php` — public facade wrapping Tokenizer + Parser + Evaluator. Single method: `evaluateExpression(string $expression, callable $resolve): string`. Holds `Tokenizer`, `Parser`, `Evaluator` internally. (~50 lines)
- [x] 5.3 Create `tests/Unit/Expression/EvaluatorTest.php` — ~10 scenarios: variable resolution, function call dispatch, concatenation, pipe chain, string/number literals, nested function calls, fast path bypass, unresolved variable placeholder, binary op error on unknown operator. Run: `vendor/bin/phpunit tests/Unit/Expression/EvaluatorTest.php` (~120 lines)

**Dependencies**: Phase 2 (AST nodes), Phase 3 (Parser for integration), Phase 4 (FunctionRegistry).

**Estimated lines**: ~370 new PHP

## Phase 6: Math Functions

- [x] 6.1 Create `src/Expression/Function/Math/SumFunction.php` — SUM: flattens arrays, sums all numeric values. `name(): "SUM"` (~40 lines)
- [x] 6.2 Create `src/Expression/Function/Math/MultiplyFunction.php` — MULTIPLY: product of value × all params. `name(): "MULTIPLY"` (~25 lines)
- [x] 6.3 Create `src/Expression/Function/Math/DivideFunction.php` — DIVIDE: value / first param, zero divisor → empty string. `name(): "DIVIDE"` (~25 lines)
- [x] 6.4 Create `src/Expression/Function/Math/AddFunction.php` — ADD: value + sum of params. `name(): "ADD"` (~20 lines)
- [x] 6.5 Create `src/Expression/Function/Math/SubtractFunction.php` — SUBTRACT: value - first param. `name(): "SUBTRACT"` (~20 lines)
- [x] 6.6 Create `src/Expression/Function/Math/ModFunction.php` — MOD: value % first param, zero divisor → empty string. `name(): "MOD"` (~20 lines)
- [x] 6.7 Create `src/Expression/Function/Math/PowFunction.php` — POW: value ^ first param. `name(): "POW"` (~20 lines)
- [x] 6.8 Create `src/Expression/Function/Math/SqrtFunction.php` — SQRT: √value, negative → empty string. `name(): "SQRT"` (~20 lines)
- [x] 6.9 Create `src/Expression/Function/Math/AbsFunction.php` — ABS: |value|. `name(): "ABS"` (~15 lines)
- [x] 6.10 Create `src/Expression/Function/Math/RoundFunction.php` — ROUND: round(value, decimals). `name(): "ROUND"` (~20 lines)
- [x] 6.11 Create `src/Expression/Function/Math/CeilFunction.php` — CEIL: ⌈value⌉. `name(): "CEIL"` (~15 lines)
- [x] 6.12 Create `src/Expression/Function/Math/FloorFunction.php` — FLOOR: ⌊value⌋. `name(): "FLOOR"` (~15 lines)
- [x] 6.13 Create `src/Expression/Function/Math/MinFunction.php` — MIN: min(value, ...params). `name(): "MIN"` (~20 lines)
- [x] 6.14 Create `src/Expression/Function/Math/MaxFunction.php` — MAX: max(value, ...params). `name(): "MAX"` (~20 lines)
- [x] 6.15 Create `src/Expression/Function/Math/ClampFunction.php` — CLAMP: clamp(value, min, max). `name(): "CLAMP"` (~20 lines)

**Dependencies**: Phase 4 (FunctionInterface).

**Estimated lines**: ~315 new PHP

## Phase 7: Text, Date, Logic & Formatting Functions

- [x] 7.1 Create `src/Expression/Function/Text/UpperFunction.php` — UPPER: strtoupper. `name(): "UPPER"` (~15 lines)
- [x] 7.2 Create `src/Expression/Function/Text/LowerFunction.php` — LOWER: strtolower. `name(): "LOWER"` (~15 lines)
- [x] 7.3 Create `src/Expression/Function/Text/TrimFunction.php` — TRIM: trim. `name(): "TRIM"` (~15 lines)
- [x] 7.4 Create `src/Expression/Function/Text/SubstrFunction.php` — SUBSTR: substr(value, start, length). `name(): "SUBSTR"` (~25 lines)
- [x] 7.5 Create `src/Expression/Function/Text/ReplaceFunction.php` — REPLACE: str_replace(search, replace, value). `name(): "REPLACE"` (~20 lines)
- [x] 7.6 Create `src/Expression/Function/Date/FormatDateFunction.php` — FORMAT_DATE: date(format, strtotime(value)). `name(): "FORMAT_DATE"` (~20 lines)
- [x] 7.7 Create `src/Expression/Function/Logic/IfFunction.php` — IF: if value == compare then trueResult else falseResult. `name(): "IF"` (~25 lines)
- [x] 7.8 Create `src/Expression/Function/Logic/DefaultFunction.php` — DEFAULT: value ?? fallback. `name(): "DEFAULT"` (~15 lines)
- [x] 7.9 Create `src/Expression/Function/Formatting/FormatNumberFunction.php` — FORMAT_NUMBER: number_format(value, decimals, dec_sep, thousands_sep). `name(): "FORMAT_NUMBER"` (~25 lines)
- [x] 7.10 Create `src/Expression/Function/Formatting/FormatCurrencyFunction.php` — FORMAT_CURRENCY: number_format + symbol placement. `name(): "FORMAT_CURRENCY"` (~35 lines)

**Dependencies**: Phase 4 (FunctionInterface).

**Estimated lines**: ~210 new PHP

## Phase 8: Integration — Backend Wiring

- [x] 8.1 Modify `src/Modules/PdfEngine/Primitives/Label.php` — add `private ?ExpressionEvaluator $expressionEvaluator = null`, `setExpressionEvaluator()`, update `interpolate()` to delegate when evaluator present (create resolve closure calling `resolvePath()` + `resolveSegments()`, keep legacy fallback when evaluator is null) (~50 lines changed)
- [x] 8.2 Modify `src/Modules/PdfEngine/Engine/ReportCompiler.php` — add `private ?ExpressionEvaluator $expressionEvaluator = null`, create in `compile()` via `new ExpressionEvaluator(FunctionRegistry::defaults())`, inject into each Label via `setExpressionEvaluator()` in `buildLabel()` (~20 lines changed)
- [x] 8.3 Modify `src/Layout/InterpolatesVariables.php` — add `private ?ExpressionEvaluator $expressionEvaluator = null`, `setExpressionEvaluator()`, update `interpolate()` to delegate when evaluator present (create resolve closure calling `resolveVariableKey()`, keep legacy ExpressionParser fallback when evaluator is null) (~40 lines changed)
- [x] 8.4 Create `tests/Modules/PdfEngine/Primitives/LabelExpressionTest.php` — ~6 scenarios: Label with evaluator resolves function calls, Label with evaluator handles pipe syntax, Label without evaluator uses legacy path, Label with unresolved variable keeps placeholder, Label with SUM on array field, Label with nested function calls. Run: `vendor/bin/phpunit tests/Modules/PdfEngine/Primitives/LabelExpressionTest.php` (~100 lines)

**Dependencies**: Phase 5 (ExpressionEvaluator), Phase 6+7 (all functions registered via FunctionRegistry::defaults()).

**Estimated lines**: ~210 new/modified PHP

## Phase 9: Backward Compatibility Tests

- [x] 9.1 Create `tests/Unit/Expression/BackwardCompatTest.php` — ~8 scenarios: `name | upper`, `price | currency("$")`, `'Total: ' + price | currency("$")`, `name | trim | upper`, `phone | default("N/A")`, `desc | substr(0, 50)`, `code | replace("_", " ")`, `status | if("active", "Yes", "No")`. All inputs go through the NEW pipeline (Tokenizer → Parser → Evaluator), not the legacy ExpressionParser. Run: `vendor/bin/phpunit tests/Unit/Expression/BackwardCompatTest.php` (~100 lines)
- [x] 9.2 Create `tests/Unit/Expression/Functions/MathTest.php` — ~14 scenarios: one per math function + edge cases (zero, negative, null, array input for SUM). Run: `vendor/bin/phpunit tests/Unit/Expression/Functions/MathTest.php` (~120 lines)
- [x] 9.3 Create `tests/Unit/Expression/Functions/TextTest.php` — ~9 scenarios: one per text function (UPPER, LOWER, TRIM, SUBSTR, REPLACE) + edge cases (empty string, null, special chars). Run: `vendor/bin/phpunit tests/Unit/Expression/Functions/TextTest.php` (~80 lines)
- [x] 9.4 Create `tests/Unit/Expression/Functions/FormattingTest.php` — ~8 scenarios: FORMAT_NUMBER with various separators, FORMAT_CURRENCY with symbol placement, FORMAT_DATE, IF, DEFAULT, edge cases. Run: `vendor/bin/phpunit tests/Unit/Expression/Functions/FormattingTest.php` (~80 lines)
- [x] 9.5 Run ALL existing tests to verify zero regressions: `vendor/bin/phpunit` — must pass with no failures (especially `InterpolatesVariablesTest`, `LabelTest`, `ReportCompilerTest`)

**Dependencies**: Phase 5 (Evaluator), Phase 6+7 (functions), Phase 8 (integration).

**Estimated lines**: ~460 new PHP

## Phase 10: Frontend — TypeScript Parser

- [ ] 10.1 Rewrite `designer/src/utils/expressionParser.ts` — new tokenizer + parser matching PHP grammar. Token types: `FUNCTION | VARIABLE | NUMBER | STRING | LPAREN | RPAREN | COMMA | OPERATOR | EOF`. AST node types: `FunctionCallNode | VariableNode | NumberLiteralNode | StringLiteralNode | BinaryOpNode | FilterChainNode`. Exports: `tokenize()`, `parse()`, `parseExpression()`, `buildExpression()` for round-trip. Preserve existing `hasPipeFilters()`, `hasConcatenationOp()`, `isQuotedLiteral()` helpers. (~350 lines)

**Dependencies**: None (can be built in parallel with backend phases).

**Estimated lines**: ~350 new/modified TS

## Phase 11: Frontend — Function Metadata & UI Components

- [ ] 11.1 Create `designer/src/utils/functionDefinitions.ts` — `FunctionDefinition` interface with name, category, signature, description, params. All 40 functions defined (14 math + 9 text + 3 date + 2 logic + 2 formatting + 10 legacy wrapped). `FUNCTION_DEFINITIONS` array, `getFunctionDefinition()`, `getFunctionsByCategory()`. (~250 lines)
- [ ] 11.2 Create `designer/src/components/modals/FunctionsModal.vue` — modal with search input, categorized function list, click-to-insert. Props: `modelValue?: string`. Emits: `insert(functionText: string)`. Categories: Math, Text, Date, Logic, Formatting. Search filters by name/description. (~180 lines)
- [ ] 11.3 Create `designer/src/components/inputs/ExpressionEditor.vue` — advanced editor with raw textarea mode, syntax preview showing `{{ }}` delimiters, "Insert Function" button opening FunctionsModal. Props: `modelValue: string`, `placeholder?: string`. Emits: `update:modelValue`. (~150 lines)
- [ ] 11.4 Modify `designer/src/components/layout/CompositeNodeProperties.vue` — use ExpressionEditor for label text input instead of plain TextInput. Wire up expression builder integration. (~30 lines changed)

**Dependencies**: Phase 10 (new parser types).

**Estimated lines**: ~610 new/modified Vue/TS

## Phase 12: Frontend Tests

- [ ] 12.1 Create `designer/src/components/__tests__/FunctionsModal.spec.ts` — ~5 scenarios: renders function categories, search filters functions, click emits insert, empty search shows all, keyboard navigation. Run: `cd designer && npx vitest run src/components/__tests__/FunctionsModal.spec.ts` (~80 lines)
- [ ] 12.2 Create `designer/src/components/__tests__/ExpressionEditor.spec.ts` — ~5 scenarios: renders with model value, typing updates model, insert function button opens modal, apply inserts at cursor, preview shows delimiters. Run: `cd designer && npx vitest run src/components/__tests__/ExpressionEditor.spec.ts` (~80 lines)
- [ ] 12.3 Create `designer/src/utils/__tests__/expressionParser.spec.ts` — ~10 scenarios: tokenize simple variable, tokenize function call, parse to AST, parse concatenation, parse pipe chain, round-trip (parse → build → parse), error on unterminated string, error on mismatched parens, fast path detection. Run: `cd designer && npx vitest run src/utils/__tests__/expressionParser.spec.ts` (~120 lines)

**Dependencies**: Phase 10, Phase 11.

**Estimated lines**: ~280 new TS/Vue

## Phase 13: Polish & Verification

- [ ] 13.1 Mark `src/Expression/ExpressionParser.php` as `@deprecated` — add `@deprecated Use ExpressionEvaluator instead` to class docblock. Keep file for reference. (~2 lines)
- [ ] 13.2 Mark `src/Expression/FilterRegistry.php` as `@deprecated` — add `@deprecated Use FunctionRegistry instead` to class docblock. Keep file for reference. (~2 lines)
- [ ] 13.3 Run full test suite: `vendor/bin/phpunit` (backend) + `cd designer && npx vitest run` (frontend). Verify zero failures.
- [ ] 13.4 Performance smoke test: evaluate 1000 simple `{{ name }}` expressions via fast path vs full pipeline. Log timing to verify fast path is measurably faster.

**Dependencies**: All previous phases.

**Estimated lines**: ~10 modified PHP

---

## Summary

| Phase | Tasks | Focus | Est. Lines |
|-------|-------|-------|-----------|
| Phase 1 | 4 | Core types & Tokenizer | ~345 |
| Phase 2 | 7 | AST nodes | ~165 |
| Phase 3 | 2 | Parser | ~350 |
| Phase 4 | 3 | Function interface & registry | ~165 |
| Phase 5 | 3 | Evaluator | ~370 |
| Phase 6 | 15 | Math functions | ~315 |
| Phase 7 | 10 | Text/date/logic/formatting functions | ~210 |
| Phase 8 | 4 | Backend integration wiring | ~210 |
| Phase 9 | 5 | Backward compat & function tests | ~460 |
| Phase 10 | 1 | TS parser rewrite | ~350 |
| Phase 11 | 4 | Frontend UI components | ~610 |
| Phase 12 | 3 | Frontend tests | ~280 |
| Phase 13 | 4 | Polish & verification | ~10 |
| **Total** | **65** | | **~4,240** |

### Parallelization Opportunities

- **Phase 4** (FunctionInterface + Registry) can run in parallel with Phases 1–3
- **Phases 6 & 7** (all functions) can run in parallel with each other
- **Phase 10** (TS parser) can run in parallel with all backend phases
- **Phase 11** (frontend UI) can run in parallel with Phases 6–9

### Critical Path

```
Phase 1 → Phase 2 → Phase 3 → Phase 5 → Phase 8 → Phase 9 (integration tests)
                                  ↑
Phase 4 ──────────────────────────┘
```

### Next Step

Ready for implementation (sdd-apply).
