# Functions Specification

## Purpose

Defines the 25+ built-in expression functions organized by category: math, text, date, logic, and formatting. Each function MUST implement `FunctionInterface`.

---

## Requirements

### Requirement: Math Functions

The system SHALL provide the following math functions, each implementing `FunctionInterface`:

| Function | Signature | Description |
|----------|-----------|-------------|
| `SUM` | `SUM(values[])` | Sum of all numeric values in an array or multiple arguments |
| `MULTIPLY` | `MULTIPLY(a, b)` | Product of two values |
| `DIVIDE` | `DIVIDE(a, b)` | Quotient of two values; returns empty string if divisor is zero |
| `ROUND` | `ROUND(value, decimals)` | Round to N decimal places (default: 0) |
| `ABS` | `ABS(value)` | Absolute value |
| `MIN` | `MIN(values[])` | Minimum of all values |
| `MAX` | `MAX(values[])` | Maximum of all values |
| `AVG` | `AVG(values[])` | Average of all numeric values |
| `POW` | `POW(base, exponent)` | Base raised to exponent |
| `SQRT` | `SQRT(value)` | Square root; returns empty string if negative |
| `CEIL` | `CEIL(value)` | Round up to nearest integer |
| `FLOOR` | `FLOOR(value)` | Round down to nearest integer |
| `CLAMP` | `CLAMP(value, min, max)` | Clamp value between min and max |
| `MOD` | `MOD(a, b)` | Modulus (remainder); returns empty string if divisor is zero |

#### Scenario: SUM with array variable

- GIVEN data `['prices' => [10, 20, 30]]`
- WHEN `{{ SUM(prices) }}` is evaluated
- THEN the result is `60`

#### Scenario: MULTIPLY with two variables

- GIVEN data `['price' => 100, 'quantity' => 3]`
- WHEN `{{ MULTIPLY(price, quantity) }}` is evaluated
- THEN the result is `300`

#### Scenario: ROUND with decimal places

- GIVEN data `['value' => 3.456]`
- WHEN `{{ ROUND(value, 2) }}` is evaluated
- THEN the result is `3.46`

#### Scenario: CLAMP bounds value

- GIVEN data `['value' => 150]`
- WHEN `{{ CLAMP(value, 0, 100) }}` is evaluated
- THEN the result is `100`

#### Scenario: DIVIDE by zero returns empty string

- GIVEN data `['a' => 10, 'b' => 0]`
- WHEN `{{ DIVIDE(a, b) }}` is evaluated
- THEN the result is an empty string (not an error)

#### Scenario: SQRT of negative returns empty string

- GIVEN data `['value' => -4]`
- WHEN `{{ SQRT(value) }}` is evaluated
- THEN the result is an empty string (not an error)

#### Scenario: MOD returns remainder

- GIVEN data `['a' => 10, 'b' => 3]`
- WHEN `{{ MOD(a, b) }}` is evaluated
- THEN the result is `1`

#### Scenario: SUM with nested array field

- GIVEN data `['items' => [['total' => 10], ['total' => 20], ['total' => 30]]]`
- WHEN `{{ SUM(items[].total) }}` is evaluated
- THEN the result is `60`

#### Scenario: SUM with empty array

- GIVEN data `['prices' => []]`
- WHEN `{{ SUM(prices) }}` is evaluated
- THEN the result is `0`

#### Scenario: ABS of negative

- GIVEN data `['value' => -42]`
- WHEN `{{ ABS(value) }}` is evaluated
- THEN the result is `42`

---

### Requirement: Text Functions

The system SHALL provide the following text functions:

| Function | Signature | Description |
|----------|-----------|-------------|
| `UPPER` | `UPPER(text)` | Convert to uppercase |
| `LOWER` | `LOWER(text)` | Convert to lowercase |
| `TRIM` | `TRIM(text)` | Remove leading/trailing whitespace |
| `CONCAT` | `CONCAT(a, b, ...)` | Concatenate 2+ values into a single string |
| `LEFT` | `LEFT(text, count)` | Extract first N characters |
| `RIGHT` | `RIGHT(text, count)` | Extract last N characters |
| `MID` | `MID(text, start, length)` | Extract substring starting at position for N characters |
| `LEN` | `LEN(text)` | Return character count |
| `REPLACE` | `REPLACE(text, search, replace)` | Replace all occurrences of search with replace |

#### Scenario: UPPER converts to uppercase

- GIVEN data `['name' => 'john doe']`
- WHEN `{{ UPPER(name) }}` is evaluated
- THEN the result is `JOHN DOE`

#### Scenario: TRIM removes whitespace

- GIVEN data `['name' => '  hello  ']`
- WHEN `{{ TRIM(name) }}` is evaluated
- THEN the result is `hello`

#### Scenario: CONCAT joins multiple values

- GIVEN data `['first' => 'John', 'last' => 'Doe']`
- WHEN `{{ CONCAT(first, " ", last) }}` is evaluated
- THEN the result is `John Doe`

#### Scenario: LEFT extracts characters

- GIVEN data `['code' => 'ABCDE']`
- WHEN `{{ LEFT(code, 3) }}` is evaluated
- THEN the result is `ABC`

#### Scenario: LEN returns count

- GIVEN data `['text' => 'hello']`
- WHEN `{{ LEN(text) }}` is evaluated
- THEN the result is `5`

#### Scenario: REPLACE substitutes text

- GIVEN data `['text' => 'hello world']`
- WHEN `{{ REPLACE(text, "world", "PHP") }}` is evaluated
- THEN the result is `hello PHP`

---

### Requirement: Date Functions

The system SHALL provide the following date functions:

| Function | Signature | Description |
|----------|-----------|-------------|
| `DATE_FORMAT` | `DATE_FORMAT(date, format)` | Format a date using PHP date format string |
| `DATE_ADD` | `DATE_ADD(date, days)` | Add N days to a date |
| `DATE_DIFF` | `DATE_DIFF(date1, date2)` | Difference in days between two dates |

#### Scenario: DATE_FORMAT formats date

- GIVEN data `['created' => '2026-01-15']`
- WHEN `{{ DATE_FORMAT(created, "d/m/Y") }}` is evaluated
- THEN the result is `15/01/2026`

#### Scenario: DATE_ADD adds days

- GIVEN data `['start' => '2026-01-01']`
- WHEN `{{ DATE_ADD(start, 30) }}` is evaluated
- THEN the result is `2026-01-31`

#### Scenario: DATE_DIFF returns day count

- GIVEN data `['start' => '2026-01-01', 'end' => '2026-01-31']`
- WHEN `{{ DATE_DIFF(start, end) }}` is evaluated
- THEN the result is `30`

---

### Requirement: Logic Functions

The system SHALL provide the following logic functions:

| Function | Signature | Description |
|----------|-----------|-------------|
| `IF` | `IF(condition, then, else)` | Return `then` if condition is truthy, else `else` |

#### Scenario: IF returns true branch

- GIVEN data `['status' => 'active']`
- WHEN `{{ IF(status, "Active", "Inactive") }}` is evaluated
- THEN the result is `Active`

#### Scenario: IF returns false branch for falsy values

- GIVEN data `['status' => '']`
- WHEN `{{ IF(status, "Active", "Inactive") }}` is evaluated
- THEN the result is `Inactive`

#### Scenario: IF with null value

- GIVEN data `['status' => null]`
- WHEN `{{ IF(status, "Active", "Inactive") }}` is evaluated
- THEN the result is `Inactive`

---

### Requirement: Formatting Functions

The system SHALL provide the following formatting functions:

| Function | Signature | Description |
|----------|-----------|-------------|
| `FORMAT_CURRENCY` | `FORMAT_CURRENCY(value, symbol)` | Format number as currency with symbol (default `$`) |
| `FORMAT_NUMBER` | `FORMAT_NUMBER(value, decimals, decimalSep, thousandsSep)` | Format number with locale separators |

#### Scenario: FORMAT_CURRENCY with default symbol

- GIVEN data `['total' => 1234.56]`
- WHEN `{{ FORMAT_CURRENCY(total) }}` is evaluated
- THEN the result is `$1,234.56`

#### Scenario: FORMAT_CURRENCY with custom symbol

- GIVEN data `['total' => 1234.56]`
- WHEN `{{ FORMAT_CURRENCY(total, "EUR") }}` is evaluated
- THEN the result is `EUR 1,234.56`

#### Scenario: FORMAT_NUMBER with custom separators

- GIVEN data `['value' => 1234567.89]`
- WHEN `{{ FORMAT_NUMBER(value, 2, ",", ".") }}` is evaluated
- THEN the result is `1.234.567,89`

---

### Requirement: Function Error Handling

Functions MUST handle edge cases gracefully without throwing exceptions that break rendering:

- Functions receiving `null` as the primary value SHOULD return an empty string or a sensible default
- `DIVIDE` and `MOD` with zero divisor SHOULD return an empty string
- `SQRT` with negative input SHOULD return an empty string
- `DATE_FORMAT` with invalid date SHOULD return an empty string
- `DATE_ADD` and `DATE_DIFF` with invalid dates SHOULD return an empty string
- Functions receiving non-numeric values where numbers are expected SHOULD attempt string-to-number coercion, or return the value as-is

#### Scenario: Function receives null as primary value

- GIVEN data `['value' => null]`
- WHEN `{{ ROUND(value, 2) }}` is evaluated
- THEN the result is an empty string

#### Scenario: Function receives non-numeric string

- GIVEN data `['value' => 'abc']`
- WHEN `{{ ROUND(value, 2) }}` is evaluated
- THEN the result is the original string value (coercion failure is non-fatal)
