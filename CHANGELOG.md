# Changelog

All notable changes to `toolreport/core` will be documented in this file.

## v0.1.3 — 2026-07-19

### Fixed
- Font path resolution — `__DIR__.'/../fonts/core'` contained `..` which `tc-lib-file` rejects as a security measure. Changed to `dirname(__DIR__, 2).'/fonts/core'`.

### Added
- `pdf-designer:generate-fonts` artisan command — downloads Core14 AFM files from Adobe and converts them to JSON

## v0.1.2 — 2026-07-19

### Added
- Bundled Core14 fonts in `fonts/core/` (14 JSON files, 156KB)
- Fonts: courier, courierb, courierbi, courieri, helvetica, helveticab, helveticabi, helveticai, symbol, times, timesb, timesbi, timesi, zapfdingbats

### Changed
- Font loading now checks bundled fonts first, then vendor, then parent target
- README updated with Tailwind v4 `@source` directive for designer files

### Fixed
- TCPDF composite engine font resolution — `tc-lib-pdf-font` doesn't ship pre-built JSON fonts, now bundled

## v0.1.1 — 2026-07-19

### Added
- Vue 3 designer frontend (full visual PDF designer)
- Publishable Blade view (`resources/views/pdf-designer.blade.php`)
- Web route `GET /pdf-designer/{templateId?}`
- `pdf-designer-views` vendor:publish tag
- `--with-assets` flag now publishes views alongside assets

### Changed
- Install command publishes designer views when using `--with-assets`
- README updated with PDF Designer section

## v0.1.0 — 2026-07-19

Initial release.

### Added
- PDF template management (CRUD API)
- Layout engine with band-based rendering (header, detail, footer)
- Dual PDF rendering: DomPDF + TCPDF composite engine
- Report compositions (multi-page PDFs)
- Template variables (public/private, required)
- Datasource execution with variable interpolation
- Expression engine with filters (currency, date, number, uppercase, etc.)
- `PdfDesigner` facade for programmatic PDF generation
- `pdf-designer:install` artisan command
- 8 database migrations, 5 models, 5 factories
- 6 API controllers, 9 form requests, 5 API resources
- Unit and feature test suite
