# Proposal: Page Context Variables (PAGE_NUM, PAGE_COUNT)

## Summary

Inject `PAGE_NUM` and `PAGE_COUNT` as resolvable variables into every Label's global data during TCPDF composite engine compilation.

## Motivation

Users need to display page numbers in footers, summaries, and any other band. Currently, `page_index` and `total_pages` exist as local variables in `ReportCompiler::renderPage()` but are not exposed to the expression system.

## Approach

Add a `$pageContext` property to `ReportCompiler`, set it before each `renderPage()` call, and merge it into Label's global data via `array_merge($this->data, $this->pageContext)`.

## Scope

- **Backend**: `ReportCompiler.php` (3 lines of change)
- **Tests**: `ReportCompilerTest.php` (2 new integration tests)
- **Specs**: Updated `composite-integration/spec.md` and `functions/spec.md`

## Risk

Minimal — purely additive, no existing behavior changed.
