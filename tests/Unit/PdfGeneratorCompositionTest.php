<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\DataTransferObjects\LayoutResult;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Pdf\PdfGenerator;
use Toolreport\Core\Tests\TestCase;

class PdfGeneratorCompositionTest extends TestCase
{
    private PdfGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('pdf-designer.storage.disk', 'local');
        config()->set('pdf-designer.storage.path', 'pdf-documents');
        config()->set('pdf-designer.dompdf.options', []);

        $this->generator = new PdfGenerator();
    }

    #[Test]
    public function it_combines_layouts_into_single_html_document(): void
    {
        $composition = ReportComposition::factory()->create([
            'slug' => 'monthly-report',
        ]);

        $layout1 = new LayoutResult(
            html: '<!DOCTYPE html><html><head><style>body { font-size: 10pt; }</style></head><body><div>Page 1 content</div></body></html>',
            title: 'Cover',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $layout2 = new LayoutResult(
            html: '<!DOCTYPE html><html><head><style>.band { color: #000; }</style></head><body><div>Page 2 content</div></body></html>',
            title: 'Details',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $layout3 = new LayoutResult(
            html: '<!DOCTYPE html><html><head><style>.band { color: #000; }</style></head><body><div>Page 3 content</div></body></html>',
            title: 'Appendix',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $capturedHtml = null;

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->withArgs(function ($html) use (&$capturedHtml) {
                $capturedHtml = $html;
                return true;
            })
            ->andReturnSelf();

        DomPdf::shouldReceive('setPaper')
            ->once()
            ->with('a4', 'portrait')
            ->andReturnSelf();

        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();

        DomPdf::shouldReceive('output')
            ->once()
            ->andReturn('%PDF-1.4 combined');

        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return str_starts_with($path, 'pdf-documents/monthly-report_');
            })
            ->andReturn(true);

        $document = $this->generator->generateFromComposition($composition, [$layout1, $layout2, $layout3], []);

        // Verify HTML is a single document (no nested <html> tags)
        $this->assertNotNull($capturedHtml);
        $this->assertStringContainsString('<!DOCTYPE html>', $capturedHtml);
        $this->assertStringContainsString('Page 1 content', $capturedHtml);
        $this->assertStringContainsString('Page 2 content', $capturedHtml);
        $this->assertStringContainsString('Page 3 content', $capturedHtml);

        // Body content extracted, not nested HTML documents
        $this->assertStringContainsString('pdf-composition-page', $capturedHtml);

        // Page breaks between pages but NOT after the last
        $this->assertEquals(2, substr_count($capturedHtml, 'page-break-after: always;'));

        // Styles are merged (only unique)
        $this->assertStringContainsString('font-size: 10pt', $capturedHtml);

        // Single <html> document
        $this->assertEquals(1, substr_count($capturedHtml, '<html>'));
        $this->assertEquals(1, substr_count($capturedHtml, '</html>'));

        // Verify document properties
        $this->assertInstanceOf(PdfDocument::class, $document);
        $this->assertNull($document->pdf_template_id);
        $this->assertEquals($composition->id, $document->report_composition_id);
        $this->assertEquals('Cover', $document->title);
        $this->assertEquals('done', $document->status);
    }

    #[Test]
    public function it_uses_first_layout_paper_size(): void
    {
        $composition = ReportComposition::factory()->create();

        $layout1 = new LayoutResult(
            html: '<div>Page 1</div>',
            title: 'First',
            paperSize: 'a4',
            orientation: 'landscape',
            page: ['width' => 297, 'height' => 210, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $layout2 = new LayoutResult(
            html: '<div>Page 2</div>',
            title: 'Second',
            paperSize: 'letter',
            orientation: 'portrait',
            page: ['width' => 215.9, 'height' => 279.4, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->andReturnSelf();

        DomPdf::shouldReceive('setPaper')
            ->once()
            ->with('a4', 'landscape')  // Should use first layout's paper size
            ->andReturnSelf();

        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();

        DomPdf::shouldReceive('output')
            ->once()
            ->andReturn('%PDF-1.4');

        Storage::shouldReceive('disk')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        $document = $this->generator->generateFromComposition($composition, [$layout1, $layout2], []);
        $this->assertNotNull($document);
    }

    #[Test]
    public function it_generates_document_with_null_pdf_template_id(): void
    {
        $composition = ReportComposition::factory()->create();

        $layout = new LayoutResult(
            html: '<div>Single page</div>',
            title: 'Single',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setPaper')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('output')
            ->once()
            ->andReturn('%PDF-1.4');

        Storage::shouldReceive('disk')
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        $document = $this->generator->generateFromComposition($composition, [$layout], []);

        $this->assertNull($document->pdf_template_id);
        $this->assertEquals($composition->id, $document->report_composition_id);
    }

    #[Test]
    public function it_sets_status_to_failed_on_dompdf_exception(): void
    {
        $composition = ReportComposition::factory()->create();

        $layout = new LayoutResult(
            html: '<div>Bad content</div>',
            title: 'Fail',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setPaper')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('output')
            ->once()
            ->andThrow(new \Exception('DomPDF memory exhausted'));

        $this->expectException(PdfGenerationException::class);

        try {
            $this->generator->generateFromComposition($composition, [$layout], []);
        } catch (PdfGenerationException $e) {
            // Verify document status was set to failed
            $this->assertDatabaseHas('pdf_documents', [
                'report_composition_id' => $composition->id,
                'pdf_template_id' => null,
                'status' => 'failed',
            ]);
            throw $e;
        }
    }

    #[Test]
    public function it_names_file_after_composition_slug(): void
    {
        $composition = ReportComposition::factory()->create([
            'slug' => 'my-custom-report',
        ]);

        $layout = new LayoutResult(
            html: '<div>Content</div>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $capturedPath = null;

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setPaper')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('output')
            ->once()
            ->andReturn('%PDF-1.4');

        Storage::shouldReceive('disk')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) use (&$capturedPath) {
                $capturedPath = $path;
                return true;
            })
            ->andReturn(true);

        $document = $this->generator->generateFromComposition($composition, [$layout], []);

        $this->assertStringContainsString('my-custom-report', $capturedPath);
        $this->assertStringContainsString('my-custom-report', $document->file_path);
    }

    #[Test]
    public function it_handles_single_page_composition(): void
    {
        $composition = ReportComposition::factory()->create();

        $layout = new LayoutResult(
            html: '<!DOCTYPE html><html><head><style>body { margin: 0; }</style></head><body><div>Single page</div></body></html>',
            title: 'Solo',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $capturedHtml = null;

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->withArgs(function ($html) use (&$capturedHtml) {
                $capturedHtml = $html;
                return true;
            })
            ->andReturnSelf();
        DomPdf::shouldReceive('setPaper')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('output')
            ->once()
            ->andReturn('%PDF-1.4');

        Storage::shouldReceive('disk')
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        $this->generator->generateFromComposition($composition, [$layout], []);

        // Single page should have NO page-break
        $this->assertNotNull($capturedHtml);
        $this->assertEquals(0, substr_count($capturedHtml, 'page-break-after: always;'));
        // Single document produced
        $this->assertStringContainsString('Single page', $capturedHtml);
    }

    #[Test]
    public function it_stores_the_document_in_database(): void
    {
        $composition = ReportComposition::factory()->create([
            'slug' => 'stored-test',
        ]);

        $layout = new LayoutResult(
            html: '<div>Content</div>',
            title: 'Database Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        DomPdf::shouldReceive('loadHtml')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setPaper')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('setOptions')
            ->once()
            ->andReturnSelf();
        DomPdf::shouldReceive('output')
            ->once()
            ->andReturn('%PDF-1.4 stored');

        Storage::shouldReceive('disk')
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        $document = $this->generator->generateFromComposition($composition, [$layout], ['key' => 'value']);

        $this->assertDatabaseHas('pdf_documents', [
            'id' => $document->id,
            'report_composition_id' => $composition->id,
            'pdf_template_id' => null,
            'title' => 'Database Test',
            'status' => 'done',
        ]);

        // Data should be stored as JSON
        $this->assertEquals(['key' => 'value'], $document->data);
    }
}
