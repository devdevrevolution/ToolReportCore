# ToolReport Core

PDF Designer for Laravel — template management, visual layout engine, and dual PDF rendering (DomPDF + TCPDF composite engine).

## Requirements

- PHP 8.3+
- Laravel 13+
- DomPDF (`barryvdh/laravel-dompdf`)
- TCPDF composite engine (`tecnickcom/tc-lib-pdf`)

## Installation

```bash
composer require toolreport/core
```

Publish configuration and run migrations:

```bash
php artisan pdf-designer:install
```

Or publish manually:

```bash
# Config
php artisan vendor:publish --tag=pdf-designer-config

# Migrations
php artisan vendor:publish --tag=pdf-designer-migrations
```

## Configuration

After publishing, edit `config/pdf-designer.php`:

```php
// API prefix (default: api/pdf-designer)
'api_prefix' => env('PDF_DESIGNER_API_PREFIX', 'api/pdf-designer'),

// Storage disk for generated PDFs
'storage' => [
    'disk' => env('PDF_DESIGNER_STORAGE_DISK', 'local'),
    'path' => env('PDF_DESIGNER_STORAGE_PATH', 'pdf-documents'),
],

// PDF engine selection per template
'pdf-engine' => [
    'enabled' => env('PDF_DESIGNER_PDF_ENGINE_ENABLED', true),
    'default_font' => env('PDF_DESIGNER_PDF_ENGINE_FONT', 'dejavusans'),
],
```

## Usage

### Programmatic (Facade)

```php
use Toolreport\Core\Facades\PdfDesigner;
use Toolreport\Core\Models\PdfTemplate;

$template = PdfTemplate::find(1);

// Render a single template
$document = PdfDesigner::renderTemplate($template, [
    'company' => 'Acme Corp',
    'total' => '$1,234.56',
], 'Invoice #1001');

// The returned PdfDocument has file_path and file_size
echo $document->file_path; // e.g. pdf-documents/acme-corp_1.pdf
```

### API Routes

Routes are auto-loaded by the service provider under the configured prefix (default: `api/pdf-designer`).

#### Templates

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/templates` | List all templates |
| `POST` | `/templates` | Create a template |
| `GET` | `/templates/{id}` | Show a template |
| `PUT` | `/templates/{id}` | Update a template |
| `DELETE` | `/templates/{id}` | Delete a template |
| `POST` | `/templates/{id}/duplicate` | Duplicate a template |
| `POST` | `/templates/{id}/generate` | Generate a PDF from template |

#### Documents

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/templates/{id}/documents` | List documents for a template |
| `GET` | `/documents/{id}` | Show a document |
| `GET` | `/documents/{id}/download` | Download a PDF document |

#### Compositions (Multi-page reports)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/compositions` | List all compositions |
| `POST` | `/compositions` | Create a composition |
| `GET` | `/compositions/{id}` | Show a composition |
| `PUT` | `/compositions/{id}` | Update a composition |
| `DELETE` | `/compositions/{id}` | Delete a composition |
| `POST` | `/compositions/{id}/generate` | Generate a combined PDF |

#### Template Variables

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/templates/{id}/template-vars` | List variables |
| `POST` | `/templates/{id}/template-vars` | Create a variable |
| `PUT` | `/templates/{id}/template-vars/{varId}` | Update a variable |
| `DELETE` | `/templates/{id}/template-vars/{varId}` | Delete a variable |

#### Datasource Testing

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/templates/{id}/datasources/test` | Test a datasource connection |

#### Health Check

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/health` | API health check |

## PDF Engines

ToolReport Core supports two rendering engines:

- **DomPDF** (default) — HTML-to-PDF rendering via `barryvdh/laravel-dompdf`. Good for simple layouts.
- **PDF Engine** (TCPDF composite) — Component-based rendering via `tecnickcom/tc-lib-pdf`. Better for complex layouts with precise positioning.

Set the engine per template via the `engine` field (`dompdf` or `pdf-engine`).

## Testing

```bash
composer test
```

## License

MIT
