# Frontend Specification

## Purpose

Defines the Vue 3 / TypeScript frontend components for the expression system: FunctionsModal, ExpressionEditor, TypeScript parser updates, and LabelProperty integration.

---

## Requirements

### Requirement: FunctionsModal Component

The system SHALL provide a `FunctionsModal.vue` modal component that displays all available expression functions organized by category (math, text, date, logic, formatting).

The modal MUST:
- Show function name, signature (parameter list), and one-line description
- Group functions by category
- Support a search/filter input to find functions by name
- Support drag-to-insert or click-to-insert into the expression input
- Be launched from the LabelProperty.vue expression builder button

#### Scenario: Modal shows all functions

- GIVEN the FunctionsModal is opened
- WHEN the modal renders
- THEN it displays all 25+ functions grouped by category

#### Scenario: Modal search filters functions

- GIVEN the FunctionsModal is open
- WHEN the user types `round` in the search field
- THEN only `ROUND` and `FORMAT_NUMBER` (or functions matching "round") are visible

#### Scenario: Modal inserts function into expression

- GIVEN the FunctionsModal is open
- AND an expression input field is focused
- WHEN the user clicks `MULTIPLY`
- THEN the text `MULTIPLY()` is inserted at the cursor position in the input field

---

### Requirement: ExpressionEditor Component

The system SHALL provide an `ExpressionEditor.vue` component for advanced expression editing with syntax highlighting and autocomplete.

The editor MUST:
- Display the expression text with syntax highlighting (function names, variables, string literals, numbers in distinct colors)
- Provide autocomplete suggestions for function names when the user types an uppercase letter or after `(`
- Provide autocomplete suggestions for variable paths from the data context
- Support inserting functions from the FunctionsModal
- Emit the expression string on change for the parent component to consume

The editor MUST NOT replace the standard text input — it is an enhancement available via a button in LabelProperty.vue.

#### Scenario: Editor highlights function names

- GIVEN an ExpressionEditor with expression `{{ ROUND(price, 2) }}`
- WHEN rendered
- THEN `ROUND` is highlighted in a distinct color from `price` and `2`

#### Scenario: Editor provides function autocomplete

- GIVEN an ExpressionEditor with cursor after `{`
- WHEN the user types `S`
- THEN autocomplete suggests `SUM`, `SQRT`, `SUBSTR`, `FORMAT_NUMBER`, etc.

#### Scenario: Editor provides variable autocomplete

- GIVEN an ExpressionEditor with data context `['price' => 10, 'quantity' => 3]`
- WHEN the user types `{` and pauses
- THEN autocomplete suggests `price`, `quantity` from the data context

#### Scenario: Editor emits expression on change

- GIVEN an ExpressionEditor
- WHEN the expression text changes
- THEN the component emits an `update:modelValue` event with the new expression string

---

### Requirement: TypeScript Expression Parser Update

The `expressionParser.ts` utility SHALL be updated to support the new Excel-style function syntax in addition to the existing pipe syntax.

The parser MUST:
- Tokenize function calls: `SUM(prices[].total)`
- Parse function calls into AST nodes: `FunctionCallNode` with name and arguments
- Parse string literals, number literals, and variable references
- Support pipe syntax as legacy path (same as current)
- Support concatenation with `+` (same as current)
- Provide a `buildExpression()` function that can reconstruct the expression string from AST (round-trip support)

The frontend parser MUST mirror the backend parser's grammar for round-trip compatibility.

#### Scenario: Frontend parses function call

- GIVEN the expression string `MULTIPLY(price, qty)`
- WHEN parsed by the frontend parser
- THEN the AST contains a `FunctionCallNode` with name `MULTIPLY` and two `VariableNode` arguments

#### Scenario: Frontend parses pipe syntax

- GIVEN the expression string `name | upper`
- WHEN parsed by the frontend parser
- THEN the AST contains a `FilterChainNode` with variable `name` and filter `upper`

#### Scenario: Frontend round-trips expression

- GIVEN the expression string `{{ ROUND(MULTIPLY(price, 0.21), 2) }}`
- WHEN parsed, built to string, and parsed again
- THEN the second parse produces an equivalent AST

---

### Requirement: Function Definitions Metadata

The system SHALL provide a `functionDefinitions.ts` module containing UI metadata for all built-in functions.

Each function definition MUST include:
- `name`: display name (uppercase)
- `category`: one of `math`, `text`, `date`, `logic`, `formatting`
- `signature`: parameter signature string (e.g., `(value, decimals)`)
- `description`: one-line description for the FunctionsModal
- `params`: array of parameter descriptors with name, type, required flag, and default value

#### Scenario: Function definitions include all built-in functions

- GIVEN `functionDefinitions.ts` is imported
- WHEN the definitions array is accessed
- THEN it contains entries for all 25+ built-in functions

#### Scenario: Function definition includes parameter defaults

- GIVEN the `ROUND` function definition
- WHEN its params are inspected
- THEN `decimals` has a default value of `0`

---

### Requirement: LabelProperty Integration

The `LabelProperty.vue` component SHALL include a button that opens the ExpressionEditor or FunctionsModal for editing the label's text field.

The button MUST:
- Appear adjacent to the label text input
- Open the FunctionsModal or ExpressionEditor overlay
- Pass the current expression text to the editor
- Update the label's text property when the editor emits a change

#### Scenario: LabelProperty shows expression button

- GIVEN a LabelProperty component with a text input
- WHEN rendered
- THEN an expression builder button is visible near the text input

#### Scenario: Button opens expression editor

- GIVEN the expression builder button
- WHEN clicked
- THEN the FunctionsModal or ExpressionEditor opens with the current label text

#### Scenario: Editor changes update label text

- GIVEN the expression editor is open with text `{{ name }}`
- WHEN the user edits to `{{ UPPER(name) }}` and confirms
- THEN the label's text property is updated to `{{ UPPER(name) }}`
