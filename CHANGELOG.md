# Changelog

All notable changes to `toolreport/core` will be documented in this file.

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
