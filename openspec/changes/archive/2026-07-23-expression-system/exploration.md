## Exploration: expression-system

### Current State

The expression system has **two parallel implementations** with different capabilities:

**1. DomPDF Engine — Full Expression Support**
- `ExpressionParser` (`src/Expression/ExpressionParser.php`) — Parses pipe syntax: `{{ variable | filter(param) }}` with concatenation (`+`), literal strings (`'text'`), and bracket notation (`[].field`).
- `FilterRegistry` (`src/Expression/FilterRegistry.php`) — Stores 10 filters: number, currency, upper, lower, trim, default, date, if, substr, replace. Each implements `FilterInterface` with `name()` and `apply()`.
- `InterpolatesVariables` trait (`src/Layout/InterpolatesVariables.php`) — The evaluation engine. Uses `ExpressionParser::parse()` → resolves variables via `resolveVariableKey()` (local-first, then global) → applies filters via `FilterRegistry`. Supports `[]` bracket notation, `[N]` indexed access, dot-notation, concatenation with literals.

**2. Composite Engine — Simple Regex Interpolation Only**
- `Label::interpolate()` (`src/Modules/PdfEngine/Primitives/Label.php`, line 125-147) — Uses a simple regex `\{\{\s*(\w+(?:\[\])?(?:\.\w+(?:\[\])?)*)\s*\}\}` to resolve variables. **NO filter support, NO concatenation, NO literal strings**. Only resolves `variable`, `parent.child`, `results[].name`, `orders[].items[].qty`.
- `ReportCompiler::buildLabel()` (`src/Modules/PdfEngine/Engine/ReportCompiler.php`, line 599-632) — Creates Label instances, calls `setGlobalData()` and `setLocalData()`. Data flows: `$this->data` (global) → `$label->setGlobalData()`, `$local_data` (per-iteration) → `$label->setLocalData()`.

**Key Gap**: The composite engine (the primary engine going forward) lacks all expression capabilities that the dompdf engine has. This is the core motivation for the change.

### Affected Areas

**Backend (PHP)**
- `src/Expression/ExpressionParser.php` — Current parser, will be replaced/superseded by new tokenizer+parser+evaluator
- `src/Expression/FilterRegistry.php` — Current filter registry, will be extended to a FunctionRegistry
- `src/Expression/Filter/FilterInterface.php` — Current filter contract, will need a new FunctionInterface
- `src/Expression/Filter/*.php` — 10 existing filter implementations, need to be migrated or wrapped
- `src/Layout/InterpolatesVariables.php` — Trait used by dompdf engine, must be updated to use new system
- `src/Modules/PdfEngine/Primitives/Label.php` — Composite engine Label, must gain expression support (primary target)
- `src/Modules/PdfEngine/Engine/ReportCompiler.php` — Must potentially pass ExpressionEvaluator to Label

**Frontend (Vue/TypeScript)**
- `designer/src/utils/expressionParser.ts` — Current frontend parser, mirrors backend pipe syntax
- `designer/src/utils/filterDefinitions.ts` — UI metadata for 10 filters
- `designer/src/components/modals/ExpressionBuilder.vue` — Current visual/manual expression builder modal
- `designer/src/components/layout/properties/LabelProperty.vue` — Label properties panel, no expression builder button currently

**Tests**
- `tests/Unit/InterpolatesVariablesTest.php` — 44 tests for the dompdf interpolation trait
- `tests/Modules/PdfEngine/Primitives/LabelTest.php` — 20 tests for Label, including variable interpolation
- **NO tests exist for ExpressionParser or FilterRegistry directly**

### Architecture Analysis

**What Works Well**
- The `InterpolatesVariables` trait is clean and well-tested — local-first resolution, bracket notation, filter chaining, concatenation all work correctly.
- The `FilterInterface` contract is minimal and extensible — any new filter just implements `name()` + `apply()`.
- Frontend `expressionParser.ts` mirrors backend logic for round-trip parsing/building.
- `ExpressionBuilder.vue` has a good UX: Visual mode (prefix/variable/filters/suffix) + Raw mode (manual expression).

**What Doesn't Work**
- Composite engine `Label::interpolate()` is completely separate — re-implements variable resolution with its own regex and `resolvePath()` method. No filters, no concatenation.
- No math operations exist anywhere — no SUM, MULTIPLY, DIVIDE, ROUND, ABS.
- No function call syntax exists — the current pipe syntax `{{ x | filter(args) }}` is filter-specific, not general function syntax.
- The ExpressionBuilder UI is locked to pipe-syntax — no way to build `SUM(prices[].total)` style expressions.
- `LabelProperty.vue` has no button to launch the expression builder — users must type raw expressions.

**Dependencies and Integration Points**
- `ReportCompiler` feeds data to Labels via `setGlobalData()` / `setLocalData()` — any new evaluator needs the same data context.
- `InterpolatesVariables` is a trait mixed into layout classes (used by dompdf engine) — changing it affects all users of that trait.
- The composite engine's Label is self-contained — it does its own interpolation. This is actually an advantage: we can upgrade Label independently.
- Frontend `FILTER_DEFINITIONS` must stay in sync with backend `FilterRegistry::registerDefaults()`.

### Approaches

1. **Unified ExpressionEvaluator (recommended)**
   Create a new `ExpressionEvaluator` with tokenizer → AST → evaluator pipeline. Support Excel-style function syntax `SUM(...)` alongside existing pipe syntax for backward compat. Create a `FunctionRegistry` that holds both legacy filters and new math functions. Update both Label (composite) and InterpolatesVariables (dompdf) to use it.
   - Pros: Single source of truth, both engines get same capabilities, clean separation of concerns
   - Cons: Higher upfront effort, need backward-compat layer for pipe syntax
   - Effort: High

2. **Extend Existing FilterRegistry with Math Filters**
   Add SUM, MULTIPLY, etc. as new "filters" in the existing FilterRegistry. Keep pipe syntax. Update Label to use InterpolatesVariables trait (or extract interpolation logic into shared class).
   - Pros: Lower effort, reuses existing infrastructure, no syntax change
   - Cons: `{{ prices[].total | sum() }}` is less natural than `SUM(prices[].total)`, pipe syntax is awkward for math, doesn't match Excel-style goal
   - Effort: Medium

3. **Separate Math Engine alongside existing filters**
   Create a parallel `MathEvaluator` that handles `SUM()`, `MULTIPLY()` etc. as function-call syntax. Keep existing pipe filters as-is. Label gets both systems.
   - Pros: Clean separation, existing filters untouched
   - Cons: Two systems to maintain, expressions can't mix pipe and function syntax naturally
   - Effort: Medium-High

### Recommendation

**Approach 1: Unified ExpressionEvaluator**

The new system should:
1. **Tokenizer** → splits expression into tokens (NUMBER, STRING, IDENTIFIER, OPERATOR, LPAREN, RPAREN, COMMA, DOT, PIPE, PLUS, etc.)
2. **Parser** → builds an AST with nodes: `FunctionCall(name, args[])`, `Variable(path)`, `StringLiteral(value)`, `NumberLiteral(value)`, `BinaryOp(left, op, right)`, `FilterChain(expression, filters[])`
3. **Evaluator** → walks the AST, resolves variables, calls functions, applies filters
4. **FunctionRegistry** → holds all functions (SUM, MULTIPLY, DIVIDE, ROUND, ABS, etc.) AND legacy filters (number, currency, upper, etc.)
5. **Backward compatibility** → pipe syntax `{{ x | filter(args) }}` is parsed as `FilterChain(Variable(x), [filter(args)])`, so existing expressions still work

The composite Label should get a `setExpressionEvaluator()` method (or receive one via constructor). The InterpolatesVariables trait should delegate to the same evaluator.

### Risks

- **Breaking existing expressions**: Any change to the parser must preserve backward compat for all 44 InterpolatesVariables tests and existing saved templates.
- **Performance**: AST-based evaluation is slower than regex for simple `{{ variable }}` cases. Need a fast path for simple variable resolution.
- **Two parser maintenance**: Backend PHP parser + frontend TS parser must stay in sync. Consider generating one from the other or sharing grammar definition.
- **Test coverage gap**: No tests for ExpressionParser itself — only tested indirectly via InterpolatesVariables. Must add direct parser tests.
- **Label complexity**: Label currently has its own `resolvePath()` with array iteration logic (`resolveSegments`). Merging with the evaluator's variable resolution needs care to preserve `[]` semantics.

### Ready for Proposal

Yes — the exploration is complete. The orchestrator should proceed to proposal with the recommended unified ExpressionEvaluator approach.
