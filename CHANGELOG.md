# Changelog

All notable changes to `toolreport/core` will be documented in this file.

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
