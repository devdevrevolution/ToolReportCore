# Expression Functions

ToolReport includes **30+ built-in functions** for dynamic content in PDF templates. Use them inside labels with the `{{ }}` syntax.

**Basic syntax:**

```
{{ FUNCTION_NAME(argument1, argument2) }}
```

**With variables:**

```
{{ MULTIPLY(price, quantity) }}
{{ CONCAT("Total: ", FORMAT_CURRENCY(total)) }}
{{ IF(status, "active", "Active", "Inactive") }}
```

---

## Math Functions

| Function | Signature | Description |
|----------|-----------|-------------|
| `SUM` | `SUM(values[])` | Sum of all numeric values. Flattens arrays automatically. |
| `MULTIPLY` | `MULTIPLY(a, b)` | Product of two values. |
| `DIVIDE` | `DIVIDE(a, b)` | Quotient of two values. Returns empty string if divisor is zero. |
| `ADD` | `ADD(a, b)` | Sum of two values. |
| `SUBTRACT` | `SUBTRACT(a, b)` | Difference of two values. |
| `ROUND` | `ROUND(value, decimals)` | Round to N decimal places (default: 0). |
| `ABS` | `ABS(value)` | Absolute value (removes negative sign). |
| `MIN` | `MIN(a, b, ...)` | Smallest value from a list of numbers. |
| `MAX` | `MAX(a, b, ...)` | Largest value from a list of numbers. |
| `POW` | `POW(base, exponent)` | Base raised to exponent. |
| `SQRT` | `SQRT(value)` | Square root. Returns empty string if negative. |
| `CEIL` | `CEIL(value)` | Round up to nearest integer. |
| `FLOOR` | `FLOOR(value)` | Round down to nearest integer. |
| `CLAMP` | `CLAMP(value, min, max)` | Constrain value between min and max. |
| `MOD` | `MOD(a, b)` | Remainder of division. Returns empty string if divisor is zero. |

**Examples:**

```
{{ SUM(prices) }}                          → 60 (from [10, 20, 30])
{{ SUM(items[].total) }}                   → Sum a nested field across an array
{{ MULTIPLY(price, quantity) }}            → 300
{{ DIVIDE(total, quantity) }}              → 25.5
{{ ROUND(3.456, 2) }}                      → 3.46
{{ CLAMP(quantity, 1, 100) }}              → 100 (if quantity > 100)
{{ MOD(10, 3) }}                           → 1
```

---

## Text Functions

| Function | Signature | Description |
|----------|-----------|-------------|
| `UPPER` | `UPPER(text)` | Convert to uppercase. |
| `LOWER` | `LOWER(text)` | Convert to lowercase. |
| `TRIM` | `TRIM(text)` | Remove leading/trailing whitespace. |
| `CONCAT` | `CONCAT(a, b, ...)` | Concatenate 2+ values into a single string. |
| `LEFT` | `LEFT(text, count)` | Extract first N characters. |
| `RIGHT` | `RIGHT(text, count)` | Extract last N characters. |
| `MID` | `MID(text, start, length)` | Extract substring from position for N characters. |
| `SUBSTR` | `SUBSTR(text, start, length)` | Alias of MID. Extract a portion of text. |
| `LEN` | `LEN(text)` | Return character count. |
| `REPLACE` | `REPLACE(text, search, replace)` | Replace all occurrences of search with replace. |

**Examples:**

```
{{ UPPER(name) }}                          → JOHN DOE
{{ CONCAT(first, " ", last) }}            → John Doe
{{ LEFT(code, 3) }}                        → ABC
{{ RIGHT(code, 3) }}                       → CDE
{{ MID(name, 2, 5) }}                      → hnelo (from "hello world")
{{ LEN(text) }}                             → 5
{{ REPLACE(text, "_", " ") }}             → hello world
```

---

## Date Functions

| Function | Signature | Description |
|----------|-----------|-------------|
| `FORMAT_DATE` | `FORMAT_DATE(date, format)` | Format a date using PHP date format string. |
| `DATE_ADD` | `DATE_ADD(date, amount, unit)` | Add days, months, or years to a date. |
| `DATE_DIFF` | `DATE_DIFF(date1, date2)` | Difference in days between two dates. |

**Examples:**

```
{{ FORMAT_DATE(created_at, "d/m/Y") }}       → 15/01/2026
{{ FORMAT_DATE(created_at, "Y-m-d") }}       → 2026-01-15
{{ DATE_ADD(start, 30, "days") }}             → 2026-01-31
{{ DATE_DIFF(start, end) }}                   → 30
```

**Date format tokens:** Use standard [PHP date format](https://www.php.net/manual/en/datetime.format.php) strings: `d` (day), `m` (month), `Y` (4-digit year), `H:i` (time), etc.

---

## Logic Functions

| Function | Signature | Description |
|----------|-----------|-------------|
| `IF` | `IF(value, compare, trueResult, falseResult)` | Return `trueResult` if value matches compare, else `falseResult`. |
| `DEFAULT` | `DEFAULT(value, fallback)` | Return fallback when value is null or empty. |

**Examples:**

```
{{ IF(status, "active", "Active", "Inactive") }}   → Active
{{ IF(total, "0", "No charge", total) }}            → Total or "No charge"
{{ DEFAULT(phone, "N/A") }}                         → Phone number or "N/A"
```

---

## Formatting Functions

| Function | Signature | Description |
|----------|-----------|-------------|
| `FORMAT_NUMBER` | `FORMAT_NUMBER(value, decimals, decSep, thousandsSep)` | Format number with locale separators. |
| `FORMAT_CURRENCY` | `FORMAT_CURRENCY(value, symbol, decimals, decSep, thousandsSep)` | Format as currency with symbol. |

**Parameters:**

| Param | Default | Description |
|-------|---------|-------------|
| `decimals` | `2` | Number of decimal places |
| `decSep` | `.` | Decimal separator |
| `thousandsSep` | `,` | Thousands separator |
| `symbol` | `$` | Currency symbol (FORMAT_CURRENCY only) |

**Examples:**

```
{{ FORMAT_NUMBER(1234567.89, 2) }}                          → 1,234,567.89
{{ FORMAT_NUMBER(1234567.89, 2, ",", ".") }}                → 1.234.567,89
{{ FORMAT_CURRENCY(1234.56) }}                              → $1,234.56
{{ FORMAT_CURRENCY(1234.56, "EUR") }}                       → EUR 1,234.56
{{ FORMAT_CURRENCY(1234.56, "$", 0) }}                      → $1,235
```

---

## Page Variables

These are **not functions** — they are variables injected automatically by the PDF engine.

| Variable | Type | Description |
|----------|------|-------------|
| `PAGE_NUM` | `int` | Current page number (1-based). |
| `PAGE_COUNT` | `int` | Total number of pages in the report. |

**Examples:**

```
{{ PAGE_NUM }}                                           → 2
{{ PAGE_COUNT }}                                         → 5
{{ CONCAT("Page ", PAGE_NUM, " of ", PAGE_COUNT) }}     → Page 2 of 5
```

---

## Auto-Number Parsing

Math functions automatically parse formatted number strings (Latin American / Argentine format) without needing explicit conversion:

| Format | Parsed as |
|--------|-----------|
| `"45.000.000"` | `45000000` (dot = thousands separator) |
| `"530.000"` | `530000` (dot with 3 digits = thousands) |
| `"1.234,56"` | `1234.56` (comma = decimal separator) |
| `"100.5"` | `100.5` (dot with 1-2 digits = decimal) |
| `"999"` | `999` (plain number) |

This means you can write `{{ SUM(items[].ki) }}` directly on values like `"45.000.000"` and get the correct sum without manual parsing.

---

## Error Handling

Functions handle edge cases gracefully without breaking the PDF render:

- `DIVIDE` or `MOD` with zero divisor → returns empty string
- `SQRT` of negative number → returns empty string
- `FORMAT_DATE` with invalid date → returns empty string
- Functions receiving `null` → return empty string or sensible default
- Non-numeric strings in math functions → value is used as-is (no crash)
