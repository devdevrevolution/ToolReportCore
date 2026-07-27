# Design: Expression System

## 1. Architecture Overview

### Component Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Expression Pipeline                          │
│                                                                     │
│  Input: "SUM(prices[].total)"                                       │
│     │                                                               │
│     ▼                                                               │
│  ┌──────────┐  Token[]  ┌────────┐  AST    ┌───────────┐  string   │
│  │ Tokenizer │────────→│ Parser │──────→│ Evaluator │────────→│
│  └──────────┘         └────────┘        └───────────┘          │
│                                                        │         │
│                              ┌──────────────────────────┘         │
│                              │  FunctionRegistry                  │
│                              │  ┌─────────────────────────┐      │
│                              │  │ Math:  SUM, MULTIPLY, …  │      │
│                              │  │ Text:  UPPER, LOWER, …   │      │
│                              │  │ Date:  DATE_FORMAT, …    │      │
│                              │  │ Logic: IF, DEFAULT       │      │
│                              │  │ Fmt:   FORMAT_CURRENCY, …│      │
│                              │  └─────────────────────────┘      │
│                              │                                     │
│                              ▼                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │                 Variable Resolution (resolve)               │  │
│  │  Label.resolvePath() / Label.resolveSegments()              │  │
│  │  InterpolatesVariables.resolveVariableKey()                 │  │
│  └─────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### Integration into PDF Rendering Pipeline

```
ReportCompiler::compile()
  │
  ├─ Creates ExpressionEvaluator(FunctionRegistry::defaults())
  │
  ├─ For each Label node in buildLabel():
  │     $label->setExpressionEvaluator($evaluator)
  │
  └─ Label::interpolate($text):
       │
       ├─ If evaluator present → delegate to evaluator.evaluate($text, $resolveCallback)
       │     The $resolveCallback calls Label::resolvePath() + resolveSegments()
       │
       └─ If no evaluator → legacy regex path (unchanged)
```

### InterpolatesVariables (dompdf engine) Integration

```
InterpolatesVariables::interpolate($text, $data, $localData)
  │
  ├─ If ExpressionEvaluator is set:
  │     $evaluator->evaluate($text, function($key) use ($data, $localData) {
  │         return $this->resolveVariableKey($key, $data, $localData);
  │     })
  │
  └─ If no evaluator: legacy ExpressionParser path (unchanged)
```

## 2. Architecture Decisions

### Decision: Tokenizer as State Machine (not regex splits)

**Choice**: Explicit state machine with 5 states: INITIAL, IN_VARIABLE, IN_FUNCTION, IN_STRING, IN_NUMBER

**Alternatives considered**:
- Single regex pattern (like current `splitByPipe`/`splitByPlus`)
- Library-based tokenizer (Phalex, PhpToken)

**Rationale**: The current ExpressionParser uses iterative char-by-char scanning with quote tracking — essentially an ad-hoc state machine formalized into `splitByPlus` and `splitByPipe`. A formal state machine is the natural evolution: it handles nested parens, string escapes, and multi-char operators correctly, is testable state-by-state, and has zero dependencies. A single regex can't handle arbitrary nesting. A library adds a dependency for something we already do manually.

### Decision: Visitor Pattern for Evaluator

**Choice**: Each AST node implements `accept(Evaluator $evaluator): mixed` — double dispatch.

**Alternatives considered**:
- Type-switching in evaluator (`match ($node::class)`)
- Node method `evaluate($context)` directly on each node

**Rationale**: Visitor pattern decouples node types from evaluation logic. Adding new node types doesn't require modifying the evaluator class. It's the standard pattern for AST evaluation and matches the existing codebase's preference for interfaces/contracts (see `FilterInterface`, `Component` contract). Type-switching works but violates open-closed principle. Putting evaluation on nodes couples AST structure to runtime behavior — harder to test nodes independently.

### Decision: Fast Path Before Tokenization

**Choice**: Regex check `/^[\w.\[\]]+$/` on trimmed expression — if it matches, resolve directly without entering the tokenizer.

**Alternatives considered**:
- Always run through tokenizer (even for `{{ name }}`)
- Cache compiled ASTs by expression string

**Rationale**: The fast path is critical for the composite engine where labels often contain plain `{{ variable }}` expressions (hundreds per template). Tokenizing and parsing those adds measurable overhead for zero benefit. The regex check is O(n) and matches the same pattern the current `InterpolatesVariables` already uses. AST caching adds complexity we don't need — PDF rendering is not real-time, and label text expressions are evaluated once per render.

### Decision: FunctionInterface Accepts Resolve Callback

**Choice**: `FunctionInterface::apply(mixed $value, array $params, callable $resolve): mixed`

**Alternatives considered**:
- Pre-resolved values passed to apply (no resolve callback)
- Evaluator resolves all args before calling function

**Rationale**: Functions like `SUM(items[].total)` need to resolve `items[].total` which involves Label's `resolvePath()` and `resolveSegments()` for array iteration. The evaluator can't pre-resolve because it doesn't know which args are array paths vs simple scalars. Passing the resolve callback lets each function decide how to resolve its arguments. This is the pattern used in Twig filters and Blade directives.

### Decision: Label Keeps resolvePath()/resolveSegments() as Private

**Choice**: Label retains its existing `resolvePath()` and `resolveSegments()` methods. The evaluator receives a closure that delegates to these. Label does NOT expose them as public.

**Alternatives considered**:
- Make resolvePath()/resolveSegments() public and pass Label to evaluator
- Extract resolution into a separate ResolvableInterface

**Rationale**: The existing methods handle Label's specific `[]` iteration semantics correctly and are already tested (20 Label tests). Making them public would expand Label's API surface unnecessarily. A closure callback achieves the same coupling with minimal surface area. Extracting to an interface is premature — we have exactly one consumer (Label) for composite and one (InterpolatesVariables) for dompdf. The evaluator doesn't need to know about Label at all.

### Decision: Backward Compatibility via FilterChainNode

**Choice**: Pipe syntax `name | upper` is parsed into `FilterChainNode(VariableNode, [FilterApplication])`. FilterChainNode delegates to FunctionRegistry using the same case-insensitive lookup.

**Alternatives considered**:
- Keep ExpressionParser alongside new system
- Parse pipe syntax into FunctionCallNode internally

**Rationale**: The specs require backward compat for all 44 InterpolatesVariables tests. FilterChainNode is the cleanest path: the parser detects `|` operator and produces a FilterChainNode. The evaluator iterates the chain, looking up each filter in the FunctionRegistry (which already wraps legacy filters as FunctionInterface). This means old expressions work through the NEW pipeline, not a parallel legacy path. We can deprecate ExpressionParser without breaking anything.

### Decision: FunctionRegistry Wraps Existing FilterRegistry

**Choice**: FunctionRegistry contains legacy FilterInterface implementations wrapped as FunctionInterface adapters, plus all new functions.

**Alternatives considered**:
- Duplicate all 10 existing filters as new FunctionInterface implementations
- Remove FilterRegistry entirely

**Rationale**: The 10 existing filters (Number, Currency, Upper, Lower, Trim, Default, DateFormat, If, Substr, Replace) already work. Wrapping them as FunctionInterface via a simple adapter class avoids re-implementing tested logic. The adapter calls the underlying FilterInterface::apply() and is transparent to the evaluator. FilterRegistry is kept as a deprecated import, not deleted.

### Decision: Frontend Parser Mirrors Backend Grammar

**Choice**: TypeScript `expressionParser.ts` rewritten to tokenize + parse using the same grammar as PHP Tokenizer + Parser. Same token types, same AST node types, same precedence rules.

**Alternatives considered**:
- Share grammar via JSON schema
- Use a grammar generator (PEG.js) for both

**Rationale**: Sharing grammar via JSON adds a build step and serialization complexity. PEG.js generates different AST shapes than our hand-rolled PHP parser. The current frontend parser (expressionParser.ts) is already hand-written to mirror the backend — we continue that pattern with the new grammar. Round-trip tests validate alignment.

## 3. Data Flow

### Expression Evaluation Flow (Composite Engine)

```
Label::interpolate("Hello {{ UPPER(name) }}, total: {{ SUM(prices) }}")
  │
  ├─ preg_replace_callback for each {{ ... }} match
  │     │
  │     ├─ Match 1: "UPPER(name)"
  │     │    ├─ Fast path check: /[\w.\[\]]+/ → FAIL (has parens)
  │     │    ├─ Tokenizer: [FUNCTION("UPPER"), LPAREN, VARIABLE("name"), RPAREN, EOF]
  │     │    ├─ Parser: FunctionCallNode("UPPER", [VariableNode("name")])
  │     │    └─ Evaluator:
  │     │         ├─ resolve("name") → via closure → Label::resolvePath() → "John"
  │     │         ├─ FunctionRegistry::get("UPPER") → UpperFilter
  │     │         └─ apply("John", []) → "JOHN"
  │     │
  │     └─ Match 2: "SUM(prices)"
  │          ├─ Fast path: FAIL (has parens)
  │          ├─ Tokenizer: [FUNCTION("SUM"), LPAREN, VARIABLE("prices"), RPAREN, EOF]
  │          ├─ Parser: FunctionCallNode("SUM", [VariableNode("prices")])
  │          └─ Evaluator:
  │               ├─ resolve("prices") → [10, 20, 30]
  │               ├─ FunctionRegistry::get("SUM") → SumFunction
  │               └─ apply(null, [[10,20,30]], $resolve) → 60
  │
  └─ Result: "Hello JOHN, total: 60"
```

### Backward Compatibility Flow

```
Label::interpolate("{{ name | upper }}")
  │
  ├─ preg_replace_callback match: "name | upper"
  │     ├─ Fast path: FAIL (has pipe)
  │     ├─ Tokenizer: [VARIABLE("name"), OPERATOR("|"), FUNCTION("upper"), EOF]
  │     ├─ Parser: FilterChainNode(VariableNode("name"), [FilterApplication("upper", [])])
  │     └─ Evaluator:
  │          ├─ resolve("name") → "John"
  │          ├─ FilterChain: look up "upper" in FunctionRegistry → UpperFilter (wrapped)
  │          └─ apply("John", []) → "JOHN"
  │
  └─ Result: "JOHN"
```

## 4. Tokenizer Design

### State Machine

```
         ┌──────────┐
         │ INITIAL  │◄──────────────────────────┐
         └────┬─────┘                            │
              │                                  │
   ┌──────────┼──────────┬──────────┬──────────┐ │
   ▼          ▼          ▼          ▼          ▼ │
IN_VARIABLE IN_NUMBER IN_STRING  OPERATOR  LPAREN/RPAREN/COMMA
   │          │          │          │          │
   │ [^a-zA-Z0-9._\[\]]│ [quote]  │ |  +     │ emit, → INITIAL
   │ emit     │ emit     │ emit    │ emit     │
   └──────────┴──────────┴─────────┴──────────┘
```

### Token Types

```php
enum TokenType: string {
    case FUNCTION   = 'FUNCTION';     // identifiers followed by '('
    case VARIABLE   = 'VARIABLE';     // identifiers NOT followed by '('
    case NUMBER     = 'NUMBER';       // digits, optional decimal
    case STRING     = 'STRING';       // single or double quoted
    case LPAREN     = 'LPAREN';       // (
    case RPAREN     = 'RPAREN';       // )
    case COMMA      = 'COMMA';        // ,
    case OPERATOR   = 'OPERATOR';     // | or +
    case EOF        = 'EOF';          // end of input
}
```

### Disambiguation: FUNCTION vs VARIABLE

The tokenizer uses a one-token lookahead: when it encounters an identifier (word chars + dots + brackets), it reads ahead to check if the next non-whitespace character is `(`. If yes → `FUNCTION`. If no → `VARIABLE`.

This matches how Excel/Spreadsheet functions work: `SUM(` is a function, `prices` is a variable.

### Regex Patterns per State

| State | Pattern | Produces |
|-------|---------|----------|
| INITIAL → IN_VARIABLE | `/[a-zA-Z_]\w*(?:\[\])?(?:\.\w+(?:\[\])?)*/` | VARIABLE or FUNCTION (lookahead for `(`) |
| INITIAL → IN_NUMBER | `/\d+(?:\.\d+)?/` | NUMBER |
| INITIAL → IN_STRING | `/'[^']*'/` or `/"[^"]*"/` | STRING (with quote stripping) |
| INITIAL → OPERATOR | `/\|/` or `/\+/` | OPERATOR |
| INITIAL → LPAREN | `/\(/` | LPAREN |
| INITIAL → RPAREN | `/\)/` | RPAREN |
| INITIAL → COMMA | `/,/` | COMMA |

### Token Structure

```php
readonly class Token {
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $position,  // char offset in input (for error messages)
    ) {}
}
```

### Error Handling

- Unterminated string → `\InvalidArgumentException` with position info
- Invalid character → skip and continue (with optional warning)
- EOF with open parenthesis → error raised by parser, not tokenizer

## 5. Parser Design

### Recursive Descent Parser

The parser implements a Pratt-inspired recursive descent with two precedence levels:

1. **Expression** (lowest): pipe/concatenation — `BinaryOpNode` or `FilterChainNode`
2. **Primary** (highest): function calls, variables, literals — leaf nodes

### Grammar (EBNF)

```
expression     = primary ( ( '|' functionCall ) | ( '+' primary ) )*
primary        = functionCall | variable | numberLiteral | stringLiteral | '(' expression ')'
functionCall   = IDENTIFIER '(' argumentList? ')'
argumentList   = expression ( ',' expression )*
variable       = IDENTIFIER ( '.' IDENTIFIER | '[' ']' | '[' NUMBER ']' )*
numberLiteral  = NUMBER
stringLiteral  = STRING
```

### Precedence Rules

| Operator | Associativity | Precedence | Produces |
|----------|---------------|------------|----------|
| `+` (concatenation) | Left | 1 (lowest) | `BinaryOpNode('+', left, right)` |
| `\|` (pipe/filter) | Left | 2 | `FilterChainNode(base, filters[])` |
| Function call | N/A | 3 (highest) | `FunctionCallNode(name, args[])` |

The pipe operator binds tighter than concatenation:
- `a | upper + b` → `(a | upper) + b` ✓ (matches current ExpressionParser behavior)
- `'Total: ' + price | currency('$')` → `'Total: ' + (price | currency('$'))` ✓

### AST Node Hierarchy

```
ExpressionNode (abstract)
├── FunctionCallNode      → name: string, args: ExpressionNode[]
├── VariableNode          → path: string (may contain [])
├── NumberLiteralNode     → value: float
├── StringLiteralNode     → value: string
├── BinaryOpNode          → left: ExpressionNode, operator: string, right: ExpressionNode
└── FilterChainNode       → base: ExpressionNode, filters: FilterApplication[]
                              FilterApplication { name: string, params: ExpressionNode[] }
```

### Parser Output Examples

| Input | AST |
|-------|-----|
| `name` | `VariableNode("name")` |
| `SUM(prices)` | `FunctionCallNode("SUM", [VariableNode("prices")])` |
| `ROUND(price, 2)` | `FunctionCallNode("ROUND", [VariableNode("price"), NumberLiteralNode(2)])` |
| `'hello'` | `StringLiteralNode("hello")` |
| `name \| upper` | `FilterChainNode(VariableNode("name"), [FilterApplication("upper", [])])` |
| `'Total: ' + price` | `BinaryOpNode(StringLiteralNode("Total: "), '+', VariableNode("price"))` |
| `SUM(items[].total)` | `FunctionCallNode("SUM", [VariableNode("items[].total")])` |

### Error Handling

- Mismatched parentheses: track paren depth, error at EOF if depth ≠ 0
- Unexpected token: throw with token position and expected token types
- Empty argument list: `SUM()` is valid → FunctionCallNode with empty args
- Trailing comma: `ROUND(price, 2,)` → error

## 6. Evaluator Design

### Visitor Pattern

```php
interface Visitable {
    public function accept(Evaluator $evaluator): mixed;
}

// Each node implements:
class FunctionCallNode extends ExpressionNode implements Visitable {
    public function accept(Evaluator $evaluator): mixed {
        return $evaluator->visitFunctionCall($this);
    }
}

class Evaluator {
    public function evaluate(ExpressionNode $node, callable $resolve): string {
        return (string) $node->accept($this);
    }

    public function visitFunctionCall(FunctionCallNode $node): mixed { ... }
    public function visitVariable(VariableNode $node): mixed { ... }
    public function visitNumberLiteral(NumberLiteralNode $node): mixed { ... }
    public function visitStringLiteral(StringLiteralNode $node): mixed { ... }
    public function visitBinaryOp(BinaryOpNode $node): mixed { ... }
    public function visitFilterChain(FilterChainNode $node): mixed { ... }
}
```

### Evaluator Construction

```php
class Evaluator {
    private FunctionRegistry $functions;
    private ?callable $resolve = null;

    public function __construct(FunctionRegistry $functions) {
        $this->functions = $functions;
    }

    /**
     * @param callable $resolve  function(string $key): mixed
     */
    public function evaluate(ExpressionNode $node, callable $resolve): string {
        $this->resolve = $resolve;
        $result = $node->accept($this);
        return $this->stringify($result);
    }
}
```

### Variable Resolution

```php
public function visitVariable(VariableNode $node): mixed {
    $value = ($this->resolve)($node->path);
    return $value; // null means unresolved — caller handles placeholder
}
```

### Function Dispatch

```php
public function visitFunctionCall(FunctionCallNode $node): mixed {
    $fn = $this->functions->get($node->name); // case-insensitive lookup

    // Resolve all argument nodes
    $resolvedArgs = array_map(
        fn(ExpressionNode $arg) => $arg->accept($this),
        $node->args
    );

    // First arg is $value (the "primary"), rest are params
    $value = $resolvedArgs[0] ?? null;
    $params = array_slice($resolvedArgs, 1);

    return $fn->apply($value, $params, $this->resolve);
}
```

### FilterChain Evaluation (Backward Compat)

```php
public function visitFilterChain(FilterChainNode $node): mixed {
    $value = $node->base->accept($this);

    foreach ($node->filters as $filter) {
        $fn = $this->functions->get($filter->name);
        $resolvedParams = array_map(
            fn(ExpressionNode $p) => $p->accept($this),
            $filter->params
        );
        $value = $fn->apply($value, $resolvedParams, $this->resolve);
    }

    return $value;
}
```

### BinaryOp Evaluation (Concatenation)

```php
public function visitBinaryOp(BinaryOpNode $node): mixed {
    $left = $this->stringify($node->left->accept($this));
    $right = $this->stringify($node->right->accept($this));

    return match ($node->operator) {
        '+' => $left . $right,
        default => throw new \InvalidArgumentException("Unknown operator: {$node->operator}"),
    };
}
```

### Fast Path Integration

```php
// In the top-level Evaluator or in InterpolatesVariables/Label
private static function isFastPath(string $expression): bool {
    return preg_match('/^[\w.\[\]]+$/', trim($expression)) === 1;
}

public function evaluateExpression(string $expression, callable $resolve): string {
    $trimmed = trim($expression);

    if (self::isFastPath($trimmed)) {
        $value = $resolve($trimmed);
        return $value !== null ? $this->stringify($value) : "{{ {$trimmed} }}";
    }

    $tokens = $this->tokenizer->tokenize($trimmed);
    $ast = $this->parser->parse($tokens);
    $result = $this->evaluate($ast, $resolve);

    return $result !== '' ? $result : "{{ {$trimmed} }}";
}
```

## 7. FunctionRegistry Design

### Interface

```php
interface FunctionInterface {
    public function name(): string;
    /**
     * @param mixed $value    Primary value (first argument)
     * @param array $params   Additional parameters
     * @param callable $resolve Variable resolution callback
     */
    public function apply(mixed $value, array $params, callable $resolve): mixed;
}
```

### Registry

```php
class FunctionRegistry {
    /** @var array<string, FunctionInterface> normalized to lowercase keys */
    private array $functions = [];

    public function register(FunctionInterface $fn): self {
        $this->functions[strtolower($fn->name())] = $fn;
        return $this;
    }

    public function get(string $name): FunctionInterface {
        $key = strtolower($name);
        if (!isset($this->functions[$key])) {
            throw new \InvalidArgumentException("Unknown function: \"{$name}\"");
        }
        return $this->functions[$key];
    }

    public function has(string $name): bool {
        return isset($this->functions[strtolower($name)]);
    }

    /** @return list<string> */
    public function names(): array {
        return array_values(array_unique(array_map(
            fn(FunctionInterface $fn) => $fn->name(),
            $this->functions
        )));
    }

    public static function defaults(): self {
        $registry = new self();

        // Legacy filters (wrapped)
        $registry->register(new LegacyFilterAdapter(new Filter\NumberFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\CurrencyFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\UpperFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\LowerFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\TrimFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\DefaultFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\DateFormatFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\IfFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\SubstrFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\ReplaceFilter()));

        // New math functions
        $registry->register(new Function\Math\SumFunction());
        $registry->register(new Function\Math\MultiplyFunction());
        $registry->register(new Function\Math\DivideFunction());
        $registry->register(new Function\Math\RoundFunction());
        $registry->register(new Function\Math\AbsFunction());
        $registry->register(new Function\Math\MinFunction());
        $registry->register(new Function\Math\MaxFunction());
        $registry->register(new Function\Math\AvgFunction());
        $registry->register(new Function\Math\PowFunction());
        $registry->register(new Function\Math\SqrtFunction());
        $registry->register(new Function\Math\CeilFunction());
        $registry->register(new Function\Math\FloorFunction());
        $registry->register(new Function\Math\ClampFunction());
        $registry->register(new Function\Math\ModFunction());

        // New text functions
        $registry->register(new Function\Text\ConcatFunction());
        $registry->register(new Function\Text\LeftFunction());
        $registry->register(new Function\Text\RightFunction());
        $registry->register(new Function\Text\MidFunction());
        $registry->register(new Function\Text\LenFunction());

        // New date functions
        $registry->register(new Function\Date\DateAddFunction());
        $registry->register(new Function\Date\DateDiffFunction());

        // New formatting functions
        $registry->register(new Function\Formatting\FormatCurrencyFunction());
        $registry->register(new Function\Formatting\FormatNumberFunction());

        return $registry;
    }
}
```

### LegacyFilterAdapter

```php
class LegacyFilterAdapter implements FunctionInterface {
    public function __construct(private FilterInterface $filter) {}

    public function name(): string {
        return $this->filter->name();
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed {
        return $this->filter->apply($value, $params);
    }
}
```

### Function Categories

| Category | Functions | Count |
|----------|-----------|-------|
| Math | SUM, MULTIPLY, DIVIDE, ROUND, ABS, MIN, MAX, AVG, POW, SQRT, CEIL, FLOOR, CLAMP, MOD | 14 |
| Text | UPPER, LOWER, TRIM, CONCAT, LEFT, RIGHT, MID, LEN, REPLACE | 9 |
| Date | DATE_FORMAT, DATE_ADD, DATE_DIFF | 3 |
| Logic | IF, DEFAULT | 2 |
| Formatting | FORMAT_CURRENCY, FORMAT_NUMBER | 2 |
| **Legacy (wrapped)** | number, currency, upper, lower, trim, default, dateFormat, if, substr, replace | 10 |
| **Total** | | **40** |

Note: UPPER/LOWER/TRIM/IF exist as both new functions (FunctionCallNode path) and legacy wrapped filters (FilterChainNode path). Both resolve to the same underlying logic. The FunctionCallNode path is the primary API going forward.

## 8. Label Integration

### Label Changes

```php
class Label implements Component {
    // NEW: optional evaluator injection
    private ?ExpressionEvaluator $expressionEvaluator = null;

    public function setExpressionEvaluator(ExpressionEvaluator $evaluator): void {
        $this->expressionEvaluator = $evaluator;
    }

    private function interpolate(string $input): string {
        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function (array $matches): string {
            $expression = $matches[1];

            // NEW: delegate to evaluator if present
            if ($this->expressionEvaluator !== null) {
                $resolveCallback = function (string $key): mixed {
                    // Check local data first (Label's existing semantics)
                    $value = $this->resolvePath($this->local_data, $key);
                    if ($value !== null) {
                        return $value;
                    }
                    // Then global data
                    return $this->resolvePath($this->global_data, $key);
                };

                $result = $this->expressionEvaluator->evaluateExpression($expression, $resolveCallback);

                // If evaluator returned the expression unchanged (unresolved), keep placeholder
                if ($result === $expression) {
                    return $matches[0];
                }

                return $result;
            }

            // LEGACY: regex-based resolution (unchanged)
            // ... existing code ...
        }, $input);
    }

    // resolvePath() and resolveSegments() remain PRIVATE — only accessible via closure
}
```

### ReportCompiler Changes

```php
class ReportCompiler {
    // NEW: shared evaluator per compilation cycle
    private ?ExpressionEvaluator $expressionEvaluator = null;

    public function compile(array $config, array $data = []): string {
        $this->data = $data;
        $this->expressionEvaluator = new ExpressionEvaluator(FunctionRegistry::defaults());
        // ... rest unchanged ...
    }

    private function buildLabel(array $node, array $local_data): Label {
        $label = new Label($node['text'] ?? '', $this->getFontMetrics());
        // ... existing property setup ...

        // NEW: inject evaluator
        $label->setExpressionEvaluator($this->expressionEvaluator);

        $label->setGlobalData($this->data);
        $label->setLocalData($local_data);

        return $label;
    }
}
```

### InterpolatesVariables Changes

```php
trait InterpolatesVariables {
    // NEW: optional evaluator
    private ?ExpressionEvaluator $expressionEvaluator = null;

    public function setExpressionEvaluator(ExpressionEvaluator $evaluator): void {
        $this->expressionEvaluator = $evaluator;
    }

    protected function interpolate(string $text, array $data, array $localData = []): string {
        // NEW: delegate to evaluator if present
        if ($this->expressionEvaluator !== null) {
            return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($matches) use ($data, $localData) {
                $expression = $matches[1];

                $resolveCallback = function (string $key) use ($data, $localData): mixed {
                    return $this->resolveVariableKey($key, $data, $localData);
                };

                $result = $this->expressionEvaluator->evaluateExpression($expression, $resolveCallback);

                // Unresolved → keep placeholder
                if ($result === $expression || $result === trim($expression)) {
                    return $matches[0];
                }

                return $result;
            }, $text);
        }

        // LEGACY: existing ExpressionParser path (unchanged)
        // ... existing code ...
    }
}
```

## 9. InterpolatesVariables Delegation Pattern

The delegation pattern is critical for the dompdf engine. The key design point:

1. **ExpressionEvaluator is stateless per evaluation** — it receives a `callable $resolve` on each `evaluate()` call, not during construction.
2. **InterpolatesVariables creates the resolve callback** per call to `interpolate()`, capturing `$data` and `$localData` in the closure.
3. **The same ExpressionEvaluator instance** is shared across all InterpolatesVariables consumers (Labels, Images, etc.) — the resolve callback provides per-context isolation.

```
ExpressionEvaluator (shared, stateless)
     │
     │  evaluateExpression("SUM(prices)", $resolve)
     │  evaluateExpression("name | upper", $resolve2)
     ▼
$resolve (closure, captures $data + $localData per call)
     │
     ├─ resolveVariableKey("prices", $data, $localData) → [10, 20, 30]
     └─ resolveVariableKey("name", $data, $localData) → "John"
```

## 10. Frontend Design

### TypeScript Parser (`expressionParser.ts`)

The existing `expressionParser.ts` is rewritten to match the backend grammar:

```typescript
// Token types (mirrors PHP TokenType enum)
type TokenType = 'FUNCTION' | 'VARIABLE' | 'NUMBER' | 'STRING' | 'LPAREN' | 'RPAREN' | 'COMMA' | 'OPERATOR' | 'EOF'

interface Token {
    type: TokenType
    value: string
    position: number
}

// AST nodes (mirrors PHP AST node hierarchy)
interface FunctionCallNode { type: 'functionCall'; name: string; args: ExprNode[] }
interface VariableNode { type: 'variable'; path: string }
interface NumberLiteralNode { type: 'numberLiteral'; value: number }
interface StringLiteralNode { type: 'stringLiteral'; value: string }
interface BinaryOpNode { type: 'binaryOp'; left: ExprNode; operator: string; right: ExprNode }
interface FilterChainNode { type: 'filterChain'; base: ExprNode; filters: FilterApplication[] }
type ExprNode = FunctionCallNode | VariableNode | NumberLiteralNode | StringLiteralNode | BinaryOpNode | FilterChainNode

interface FilterApplication { name: string; params: ExprNode[] }

// Core API
function tokenize(input: string): Token[]
function parse(tokens: Token[]): ExprNode
function buildExpression(node: ExprNode): string  // round-trip
function parseExpression(input: string): ExprNode // tokenize + parse convenience
```

### FunctionsModal.vue

New component at `designer/src/components/modals/FunctionsModal.vue`:

```
┌──────────────────────────────────────────────┐
│ Functions                           [Search] │
├──────────────────────────────────────────────┤
│ ▼ Math                                        │
│   SUM(values[])        Sum of array values    │
│   MULTIPLY(a, b)       Product of two values  │
│   ROUND(value, dec)    Round to N decimals     │
│   ...                                         │
│ ▼ Text                                        │
│   UPPER(text)          Convert to uppercase    │
│   ...                                         │
│ ▼ Date                                        │
│   FORMAT_DATE(date, fmt) Format a date        │
│   ...                                         │
│ ▼ Logic                                       │
│   IF(cond, then, else)  Conditional return    │
│   ...                                         │
│ ▼ Formatting                                  │
│   FORMAT_CURRENCY(val, sym) Format as currency│
│   ...                                         │
└──────────────────────────────────────────────┘
```

- Search input filters functions by name/description
- Click inserts `FUNCTION_NAME()` at cursor position in the parent's expression input
- Props: `modelValue?: string` (expression), emits `insert` with function text

### functionDefinitions.ts

Replaces `filterDefinitions.ts` with expanded metadata:

```typescript
interface FunctionDefinition {
    name: string
    category: 'math' | 'text' | 'date' | 'logic' | 'formatting'
    signature: string        // e.g. "(values[])"
    description: string      // one-line
    params: ParamDefinition[]
}

interface ParamDefinition {
    name: string
    type: 'number' | 'string' | 'boolean' | 'variable'
    required: boolean
    defaultValue?: unknown
}
```

All 40 functions (new + legacy wrapped) are defined here. The `FILTER_DEFINITIONS` export is renamed to `FUNCTION_DEFINITIONS` and expanded.

### LabelProperty.vue Integration

Add an expression builder button adjacent to the text textarea:

```vue
<template>
    <PropertyGroup title="Text" collapsible>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Content</label>
            <div class="relative">
                <textarea
                    class="w-full rounded border border-gray-300 px-2 py-1.5 pr-8 text-xs focus:border-blue-500 focus:outline-none"
                    rows="3"
                    :value="props.node.text"
                    @input="update({ text: ($event.target as HTMLTextAreaElement).value })"
                />
                <button
                    class="absolute right-1 top-1 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-blue-600"
                    title="Open expression builder"
                    @click="showExpressionBuilder = true"
                >
                    f(x)
                </button>
            </div>
        </div>
        <!-- ... rest unchanged ... -->
    </PropertyGroup>

    <ExpressionBuilder
        v-if="showExpressionBuilder"
        :model-value="props.node.text"
        :current-text="props.node.text"
        @apply="onExpressionApply"
        @close="showExpressionBuilder = false"
    />
</template>
```

### ExpressionBuilder.vue Updates

The existing ExpressionBuilder is updated to support the new function syntax:

1. **Visual mode**: restructured to show function calls instead of filter chains. User selects a function from a dropdown (populated from `FUNCTION_DEFINITIONS`), parameters are filled in.
2. **Raw mode**: textarea accepts new function syntax. Preview shows `{{ SUM(prices) }}` instead of `{{ prices | sum }}`.
3. **FunctionsModal integration**: "Insert Function" button opens FunctionsModal.
4. **Preview**: shows the expression with `{{ }}` delimiters.

## 11. Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| **Unit — Tokenizer** | Each token type, state transitions, error cases | PHPUnit: `TokenizerTest.php` — token-by-token assertions |
| **Unit — Parser** | AST construction, precedence, error cases | PHPUnit: `ParserTest.php` — AST structure assertions |
| **Unit — Evaluator** | Each node type evaluation, variable resolution, function dispatch | PHPUnit: `EvaluatorTest.php` — evaluate + compare result |
| **Unit — Math functions** | All 14 math functions, edge cases (zero, null, negative) | PHPUnit: `MathFunctionTest.php` — one test per function |
| **Unit — Text functions** | All 9 text functions | PHPUnit: `TextFunctionTest.php` |
| **Unit — Date functions** | All 3 date functions | PHPUnit: `DateFunctionTest.php` |
| **Unit — Backward compat** | Pipe syntax, concatenation, filter chains | PHPUnit: `BackwardCompatTest.php` — existing ExpressionParser inputs through new pipeline |
| **Unit — Fast path** | Simple variable bypass, detection accuracy | PHPUnit: `FastPathTest.php` |
| **Integration — Composite engine** | Label with evaluator, function expressions in PDF | PHPUnit: `LabelExpressionTest.php` — Label test with real data |
| **Integration — InterpolatesVariables** | Evaluator delegation, resolve callback correctness | PHPUnit: extend existing `InterpolatesVariablesTest.php` |
| **Unit — TS Tokenizer** | Frontend tokenizer matches backend | Vitest: `expressionParser.test.ts` |
| **Unit — TS Parser** | Frontend parser produces equivalent AST | Vitest: `expressionParser.test.ts` |
| **Unit — TS Round-trip** | parse → build → parse produces equivalent AST | Vitest: `expressionParser.test.ts` |
| **Vue — FunctionsModal** | Render, search, insert | Vitest: `FunctionsModal.test.ts` |
| **Vue — ExpressionBuilder** | Visual/raw modes, apply | Vitest: `ExpressionBuilder.test.ts` |

### Test Count Estimate

- Tokenizer tests: ~15 scenarios
- Parser tests: ~12 scenarios
- Evaluator tests: ~10 scenarios
- Math function tests: ~14 scenarios (one per function + edge cases)
- Text function tests: ~9 scenarios
- Date function tests: ~3 scenarios
- Backward compat tests: ~8 scenarios (matching existing ExpressionParser inputs)
- Fast path tests: ~5 scenarios
- Integration tests: ~6 scenarios
- Frontend tests: ~10 scenarios
- **Total: ~92 new test scenarios**

## 12. File Changes

| File | Action | Description |
|------|--------|-------------|
| `src/Expression/ExpressionEvaluator.php` | **Create** | Core pipeline: tokenize → parse → evaluate. Holds Tokenizer + Parser + Evaluator. Public API: `evaluateExpression(string, callable): string` |
| `src/Expression/Tokenizer.php` | **Create** | State machine tokenizer. Public API: `tokenize(string): Token[]` |
| `src/Expression/Token.php` | **Create** | Readonly Token value object (type, value, position) |
| `src/Expression/TokenType.php` | **Create** | PHP 8.3 enum for token types |
| `src/Expression/Parser.php` | **Create** | Recursive descent parser. Public API: `parse(Token[]): ExpressionNode` |
| `src/Expression/Ast/ExpressionNode.php` | **Create** | Abstract base class for all AST nodes |
| `src/Expression/Ast/FunctionCallNode.php` | **Create** | name: string, args: ExpressionNode[] |
| `src/Expression/Ast/VariableNode.php` | **Create** | path: string |
| `src/Expression/Ast/NumberLiteralNode.php` | **Create** | value: float |
| `src/Expression/Ast/StringLiteralNode.php` | **Create** | value: string |
| `src/Expression/Ast/BinaryOpNode.php` | **Create** | left, operator, right |
| `src/Expression/Ast/FilterChainNode.php` | **Create** | base: ExpressionNode, filters: FilterApplication[] |
| `src/Expression/Ast/FilterApplication.php` | **Create** | name: string, params: ExpressionNode[] |
| `src/Expression/FunctionInterface.php` | **Create** | Contract: name(), apply(value, params, resolve) |
| `src/Expression/FunctionRegistry.php` | **Create** | Register, get, has, names, defaults() factory |
| `src/Expression/LegacyFilterAdapter.php` | **Create** | Wraps FilterInterface as FunctionInterface |
| `src/Expression/Function/Math/SumFunction.php` | **Create** | SUM — sum array or multiple args |
| `src/Expression/Function/Math/MultiplyFunction.php` | **Create** | MULTIPLY |
| `src/Expression/Function/Math/DivideFunction.php` | **Create** | DIVIDE — zero → empty string |
| `src/Expression/Function/Math/RoundFunction.php` | **Create** | ROUND |
| `src/Expression/Function/Math/AbsFunction.php` | **Create** | ABS |
| `src/Expression/Function/Math/MinFunction.php` | **Create** | MIN |
| `src/Expression/Function/Math/MaxFunction.php` | **Create** | MAX |
| `src/Expression/Function/Math/AvgFunction.php` | **Create** | AVG |
| `src/Expression/Function/Math/PowFunction.php` | **Create** | POW |
| `src/Expression/Function/Math/SqrtFunction.php` | **Create** | SQRT — negative → empty string |
| `src/Expression/Function/Math/CeilFunction.php` | **Create** | CEIL |
| `src/Expression/Function/Math/FloorFunction.php` | **Create** | FLOOR |
| `src/Expression/Function/Math/ClampFunction.php` | **Create** | CLAMP |
| `src/Expression/Function/Math/ModFunction.php` | **Create** | MOD — zero divisor → empty string |
| `src/Expression/Function/Text/ConcatFunction.php` | **Create** | CONCAT |
| `src/Expression/Function/Text/LeftFunction.php` | **Create** | LEFT |
| `src/Expression/Function/Text/RightFunction.php` | **Create** | RIGHT |
| `src/Expression/Function/Text/MidFunction.php` | **Create** | MID |
| `src/Expression/Function/Text/LenFunction.php` | **Create** | LEN |
| `src/Expression/Function/Date/DateAddFunction.php` | **Create** | DATE_ADD |
| `src/Expression/Function/Date/DateDiffFunction.php` | **Create** | DATE_DIFF |
| `src/Expression/Function/Formatting/FormatCurrencyFunction.php` | **Create** | FORMAT_CURRENCY |
| `src/Expression/Function/Formatting/FormatNumberFunction.php` | **Create** | FORMAT_NUMBER |
| `src/Modules/PdfEngine/Primitives/Label.php` | **Modify** | Add `setExpressionEvaluator()`, update `interpolate()` to delegate |
| `src/Modules/PdfEngine/Engine/ReportCompiler.php` | **Modify** | Create evaluator in `compile()`, inject into Labels |
| `src/Layout/InterpolatesVariables.php` | **Modify** | Add `setExpressionEvaluator()`, delegate `interpolate()` |
| `src/Expression/ExpressionParser.php` | **Deprecate** | Mark `@deprecated`, kept for reference until next minor |
| `src/Expression/FilterRegistry.php` | **Deprecate** | Mark `@deprecated`, FunctionRegistry supersedes |
| `designer/src/utils/expressionParser.ts` | **Modify** | Rewrite to tokenize + parse new grammar |
| `designer/src/utils/functionDefinitions.ts` | **Create** | UI metadata for all 40 functions |
| `designer/src/components/modals/FunctionsModal.vue` | **Create** | Function reference modal |
| `designer/src/components/modals/ExpressionBuilder.vue` | **Modify** | Rebuild for function call UI |
| `designer/src/components/layout/properties/LabelProperty.vue` | **Modify** | Add expression builder button |
| `tests/Unit/Expression/TokenizerTest.php` | **Create** | Tokenizer unit tests |
| `tests/Unit/Expression/ParserTest.php` | **Create** | Parser unit tests |
| `tests/Unit/Expression/EvaluatorTest.php` | **Create** | Evaluator unit tests |
| `tests/Unit/Expression/MathFunctionTest.php` | **Create** | Math function tests |
| `tests/Unit/Expression/TextFunctionTest.php` | **Create** | Text function tests |
| `tests/Unit/Expression/BackwardCompatTest.php` | **Create** | Pipe syntax through new pipeline |
| `tests/Unit/Expression/FastPathTest.php` | **Create** | Fast path tests |
| `tests/Unit/Expression/IntegrationTest.php` | **Create** | Label + InterpolatesVariables with evaluator |
| `designer/src/__tests__/expressionParser.test.ts` | **Create** | Frontend parser tests |
| `designer/src/__tests__/FunctionsModal.test.ts` | **Create** | FunctionsModal Vue tests |

**Summary**: 42 new files, 5 modified files, 2 deprecated files, 0 deleted files.

## 13. Implementation Order

### Phase 1: Core Engine (Backend, no integration)
1. `TokenType.php` — enum
2. `Token.php` — readonly class
3. `Tokenizer.php` — state machine
4. `TokenizerTest.php` — validate tokenizer
5. `Ast/ExpressionNode.php` — abstract base
6. `Ast/FunctionCallNode.php`, `VariableNode.php`, `NumberLiteralNode.php`, `StringLiteralNode.php`, `BinaryOpNode.php`, `FilterChainNode.php`, `FilterApplication.php`
7. `Parser.php` — recursive descent
8. `ParserTest.php` — validate parser
9. `FunctionInterface.php` — contract
10. `FunctionRegistry.php` — registry
11. `LegacyFilterAdapter.php` — bridge
12. `Evaluator.php` — visitor pattern (inside ExpressionEvaluator.php)
13. `ExpressionEvaluator.php` — public facade
14. `EvaluatorTest.php` — validate evaluator
15. `FastPathTest.php` — validate fast path

### Phase 2: Functions (Backend)
1. All 14 Math functions + `MathFunctionTest.php`
2. All 5 new Text functions + `TextFunctionTest.php`
3. All 2 new Date functions + `DateFunctionTest.php`
4. `BackwardCompatTest.php` — existing pipe syntax through new pipeline

### Phase 3: Integration (Backend)
1. `Label.php` — add `setExpressionEvaluator()`, update `interpolate()`
2. `ReportCompiler.php` — create + inject evaluator
3. `InterpolatesVariables.php` — add `setExpressionEvaluator()`, delegate
4. `IntegrationTest.php` — Label + InterpolatesVariables with evaluator
5. Run all 44 existing `InterpolatesVariablesTest` — must pass unchanged
6. Run all 20 existing `LabelTest` — must pass unchanged

### Phase 4: Frontend
1. `expressionParser.ts` — rewrite with new grammar
2. `expressionParser.test.ts` — frontend parser tests
3. `functionDefinitions.ts` — function metadata
4. `FunctionsModal.vue` — new component
5. `FunctionsModal.test.ts` — Vue tests
6. `ExpressionBuilder.vue` — rebuild for function syntax
7. `LabelProperty.vue` — add expression builder button

### Phase 5: Polish & Verification
1. Full test suite pass (PHPUnit + Vitest)
2. Performance benchmark: fast path vs full pipeline for 1000 simple variables
3. Round-trip test: PHP parse → string → TS parse → compare AST
4. Deprecation annotations on ExpressionParser and FilterRegistry

## 14. Migration / Rollout

No data migration required — expressions are runtime-evaluated, not persisted. The rollout is code-only:

1. **Phase 1-2**: New files only, zero impact on existing code
2. **Phase 3**: Label and InterpolatesVariables get additive `setExpressionEvaluator()` calls. Existing behavior preserved when evaluator is null.
3. **Phase 4**: Frontend parser and components updated. Old pipe syntax still supported through backward compat layer.
4. **Deprecation**: ExpressionParser and FilterRegistry marked `@deprecated` but not removed until next minor version.

## 15. Open Questions

- [ ] **SUM with nested array field**: `SUM(items[].total)` — the evaluator's resolve callback returns an array for `items[].total`. Should SUM accept the array directly, or should the evaluator flatten it first? Recommendation: SUM accepts array as $value (first arg), flattens internally. This matches Excel behavior.
- [ ] **CONCAT vs + operator**: The specs define CONCAT as a function. The `+` operator also concatenates. Should `+` be removed in favor of CONCAT only? Recommendation: keep both — `+` is inline concatenation (strings), CONCAT is explicit function (works with variables too).
- [ ] **DEFAULT function vs DEFAULT filter**: The specs list DEFAULT as both a logic function and it exists as a legacy filter. Should it be wrapped or re-implemented? Recommendation: wrap as LegacyFilterAdapter (same behavior).
- [ ] **Frontend syntax highlighting**: The ExpressionEditor spec requires syntax highlighting. Should we use CodeMirror/Monaco or a lightweight textarea with overlay? Recommendation: lightweight textarea with CSS overlay for v1 — no new dependencies.
