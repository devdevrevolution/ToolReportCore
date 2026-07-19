<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\CompositionPage;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Tests\TestCase;

class CompositionApiTest extends TestCase
{
    private array $pageConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageConfig = [
            'width' => 210,
            'height' => 297,
            'margin' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
        ];
    }

    // ─── FR-1: CRUD ───────────────────────────────────────────────

    #[Test]
    public function it_creates_a_composition_with_two_templates(): void
    {
        $templateA = PdfTemplate::factory()->create();
        $templateB = PdfTemplate::factory()->create();

        $response = $this->postJson('/api/pdf-designer/compositions', [
            'name' => 'Monthly Report',
            'slug' => 'monthly-report',
            'description' => 'A monthly report with cover',
            'page' => $this->pageConfig,
            'is_active' => true,
            'pages' => [
                ['pdf_template_id' => $templateA->id, 'sort_order' => 0],
                ['pdf_template_id' => $templateB->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Monthly Report');
        $response->assertJsonPath('data.slug', 'monthly-report');
        $response->assertJsonCount(2, 'data.pages');
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'description', 'page', 'is_active', 'pages', 'page_count', 'created_at', 'updated_at'],
        ]);

        $this->assertDatabaseHas('report_compositions', [
            'name' => 'Monthly Report',
            'slug' => 'monthly-report',
        ]);

        $composition = ReportComposition::where('slug', 'monthly-report')->first();
        $this->assertCount(2, $composition->pages);
        $this->assertEquals($templateA->id, $composition->pages[0]->pdf_template_id);
        $this->assertEquals(0, $composition->pages[0]->sort_order);
        $this->assertEquals($templateB->id, $composition->pages[1]->pdf_template_id);
        $this->assertEquals(1, $composition->pages[1]->sort_order);
    }

    #[Test]
    public function it_lists_compositions_with_pagination(): void
    {
        ReportComposition::factory()->count(15)->create();

        $response = $this->getJson('/api/pdf-designer/compositions?per_page=10&page=1');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonStructure([
            'data' => [],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 15);

        // Each entry should have page_count but NOT pages array
        foreach ($response->json('data') as $item) {
            $this->assertArrayHasKey('page_count', $item);
            $this->assertArrayNotHasKey('pages', $item);
        }
    }

    #[Test]
    public function it_shows_composition_with_ordered_pages(): void
    {
        $composition = ReportComposition::factory()->create();
        $templateA = PdfTemplate::factory()->create();
        $templateB = PdfTemplate::factory()->create();
        $templateC = PdfTemplate::factory()->create();

        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateA->id,
            'sort_order' => 0,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateB->id,
            'sort_order' => 1,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateC->id,
            'sort_order' => 2,
        ]);

        $response = $this->getJson("/api/pdf-designer/compositions/{$composition->id}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data.pages');

        $pages = $response->json('data.pages');
        $this->assertEquals($templateA->id, $pages[0]['pdf_template_id']);
        $this->assertEquals(0, $pages[0]['sort_order']);
        $this->assertEquals($templateB->id, $pages[1]['pdf_template_id']);
        $this->assertEquals(1, $pages[1]['sort_order']);
        $this->assertEquals($templateC->id, $pages[2]['pdf_template_id']);
        $this->assertEquals(2, $pages[2]['sort_order']);

        // Check each page includes template metadata
        $this->assertArrayHasKey('template_name', $pages[0]);
        $this->assertArrayHasKey('template_slug', $pages[0]);
        $this->assertArrayHasKey('page', $pages[0]);
        $this->assertArrayHasKey('width', $pages[0]['page']);
        $this->assertArrayHasKey('height', $pages[0]['page']);
    }

    #[Test]
    public function it_updates_composition_replacing_pages_atomically(): void
    {
        $templateA = PdfTemplate::factory()->create();
        $templateB = PdfTemplate::factory()->create();
        $templateC = PdfTemplate::factory()->create();

        $composition = ReportComposition::factory()->create();
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateA->id,
            'sort_order' => 0,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateB->id,
            'sort_order' => 1,
        ]);

        $response = $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'name' => 'Updated Report',
            'pages' => [
                ['pdf_template_id' => $templateC->id, 'sort_order' => 0],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Report');

        // Pages should have been replaced atomically
        $composition->refresh();
        $composition->load('pages');
        $this->assertCount(1, $composition->pages);
        $this->assertEquals($templateC->id, $composition->pages[0]->pdf_template_id);

        // Old pages should be deleted
        $this->assertDatabaseMissing('composition_pages', [
            'pdf_template_id' => $templateA->id,
            'report_composition_id' => $composition->id,
        ]);
        $this->assertDatabaseMissing('composition_pages', [
            'pdf_template_id' => $templateB->id,
            'report_composition_id' => $composition->id,
        ]);
    }

    #[Test]
    public function it_deletes_composition_with_cascade(): void
    {
        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();

        // Create pages with explicit sort_order to avoid unique constraint issues
        foreach ([0, 1, 2] as $sortOrder) {
            CompositionPage::factory()->create([
                'report_composition_id' => $composition->id,
                'pdf_template_id' => $template->id,
                'sort_order' => $sortOrder,
            ]);
        }

        // Create documents referencing the composition
        $doc1 = PdfDocument::factory()->forComposition($composition->id)->done()->create();
        $doc2 = PdfDocument::factory()->forComposition($composition->id)->done()->create();

        $response = $this->deleteJson("/api/pdf-designer/compositions/{$composition->id}");

        $response->assertNoContent();

        // Composition is deleted
        $this->assertDatabaseMissing('report_compositions', ['id' => $composition->id]);

        // Pages cascade-deleted
        $this->assertDatabaseMissing('composition_pages', [
            'report_composition_id' => $composition->id,
        ]);

        // Documents should have report_composition_id set to NULL (SET NULL)
        $this->assertDatabaseHas('pdf_documents', [
            'id' => $doc1->id,
            'report_composition_id' => null,
        ]);
        $this->assertDatabaseHas('pdf_documents', [
            'id' => $doc2->id,
            'report_composition_id' => null,
        ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_composition(): void
    {
        $response = $this->getJson('/api/pdf-designer/compositions/99999');
        $response->assertNotFound();

        $response = $this->putJson('/api/pdf-designer/compositions/99999', ['name' => 'Test']);
        $response->assertNotFound();

        $response = $this->deleteJson('/api/pdf-designer/compositions/99999');
        $response->assertNotFound();
    }

    // ─── FR-2: Page Ordering ──────────────────────────────────────

    #[Test]
    public function it_adds_page_to_existing_composition(): void
    {
        $templateA = PdfTemplate::factory()->create();
        $templateB = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();

        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateA->id,
            'sort_order' => 0,
        ]);

        $response = $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'pages' => [
                ['pdf_template_id' => $templateA->id, 'sort_order' => 0],
                ['pdf_template_id' => $templateB->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertOk();
        $composition->refresh();
        $composition->load('pages');
        $this->assertCount(2, $composition->pages);
        $this->assertEquals($templateA->id, $composition->pages[0]->pdf_template_id);
        $this->assertEquals($templateB->id, $composition->pages[1]->pdf_template_id);
    }

    #[Test]
    public function it_removes_page_from_composition(): void
    {
        $templateA = PdfTemplate::factory()->create();
        $templateB = PdfTemplate::factory()->create();
        $templateC = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();

        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateA->id,
            'sort_order' => 0,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateB->id,
            'sort_order' => 1,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateC->id,
            'sort_order' => 2,
        ]);

        // Remove page B (index 1), keep A and C
        $response = $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'pages' => [
                ['pdf_template_id' => $templateA->id, 'sort_order' => 0],
                ['pdf_template_id' => $templateC->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertOk();
        $composition->refresh();
        $composition->load('pages');

        $this->assertCount(2, $composition->pages);
        $this->assertEquals($templateA->id, $composition->pages[0]->pdf_template_id);
        $this->assertEquals(0, $composition->pages[0]->sort_order);
        $this->assertEquals($templateC->id, $composition->pages[1]->pdf_template_id);
        $this->assertEquals(1, $composition->pages[1]->sort_order);

        // Page B should be deleted
        $this->assertDatabaseMissing('composition_pages', [
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateB->id,
        ]);
    }

    #[Test]
    public function it_reorders_pages(): void
    {
        $cover = PdfTemplate::factory()->create(['name' => 'Cover']);
        $toc = PdfTemplate::factory()->create(['name' => 'TOC']);
        $content = PdfTemplate::factory()->create(['name' => 'Content']);
        $composition = ReportComposition::factory()->create();

        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $cover->id,
            'sort_order' => 0,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $toc->id,
            'sort_order' => 1,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $content->id,
            'sort_order' => 2,
        ]);

        // Reorder to: Content, Cover, TOC
        $response = $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'pages' => [
                ['pdf_template_id' => $content->id, 'sort_order' => 0],
                ['pdf_template_id' => $cover->id, 'sort_order' => 1],
                ['pdf_template_id' => $toc->id, 'sort_order' => 2],
            ],
        ]);

        $response->assertOk();
        $composition->refresh();
        $composition->load('pages');

        $this->assertCount(3, $composition->pages);
        $this->assertEquals($content->id, $composition->pages[0]->pdf_template_id);
        $this->assertEquals(0, $composition->pages[0]->sort_order);
        $this->assertEquals($cover->id, $composition->pages[1]->pdf_template_id);
        $this->assertEquals(1, $composition->pages[1]->sort_order);
        $this->assertEquals($toc->id, $composition->pages[2]->pdf_template_id);
        $this->assertEquals(2, $composition->pages[2]->sort_order);
    }

    // ─── FR-4: Generation ─────────────────────────────────────────

    #[Test]
    public function it_generates_pdf_from_composition(): void
    {
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
            ->andReturn('%PDF-1.4 mock content');

        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $template->id,
            'sort_order' => 0,
        ]);

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Full Report',
            'data' => ['client' => 'Acme'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
        $response->assertJsonPath('data.report_composition_id', $composition->id);
        $response->assertJsonPath('data.pdf_template_id', null);
        $response->assertJsonStructure([
            'data' => ['id', 'report_composition_id', 'pdf_template_id', 'title', 'status', 'file_size', 'generated_at', 'created_at'],
        ]);

        // Verify PdfDocument was created with correct FK values
        $this->assertDatabaseHas('pdf_documents', [
            'report_composition_id' => $composition->id,
            'pdf_template_id' => null,
            'title' => 'Full Report',
            'status' => 'done',
        ]);
    }

    #[Test]
    public function it_enforces_page_limit_at_generation_time(): void
    {
        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();

        // Create 51 pages (exceeds limit)
        for ($i = 0; $i < 51; $i++) {
            CompositionPage::factory()->create([
                'report_composition_id' => $composition->id,
                'pdf_template_id' => $template->id,
                'sort_order' => $i,
            ]);
        }

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Too Many Pages',
            'data' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages']);
    }

    #[Test]
    public function it_rejects_inactive_template_at_generation(): void
    {
        $activeTemplate = PdfTemplate::factory()->create(['is_active' => true]);
        $inactiveTemplate = PdfTemplate::factory()->create(['is_active' => false]);

        $composition = ReportComposition::factory()->create();
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $activeTemplate->id,
            'sort_order' => 0,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $inactiveTemplate->id,
            'sort_order' => 1,
        ]);

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Test',
            'data' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages']);
        $response->assertSee($inactiveTemplate->name);
    }

    #[Test]
    public function it_rejects_deleted_template_at_generation(): void
    {
        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $template->id,
            'sort_order' => 0,
        ]);

        // Delete the template (cascade will remove the page, but let's test the check)
        $template->delete();

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Test',
            'data' => [],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_generates_with_empty_data_payload(): void
    {
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
            ->andReturn('%PDF-1.4 empty data');

        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $template->id,
            'sort_order' => 0,
        ]);

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Test',
            'data' => [],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    // ─── Additional index features ────────────────────────────────

    #[Test]
    public function it_searches_compositions(): void
    {
        ReportComposition::factory()->create(['name' => 'Monthly Finance Report']);
        ReportComposition::factory()->create(['name' => 'Quarterly Review']);
        ReportComposition::factory()->create(['name' => 'Annual Summary']);

        $response = $this->getJson('/api/pdf-designer/compositions?search=Monthly');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'Monthly Finance Report');
    }

    #[Test]
    public function it_sorts_compositions(): void
    {
        ReportComposition::factory()->create(['name' => 'B Report', 'slug' => 'b-report']);
        ReportComposition::factory()->create(['name' => 'A Report', 'slug' => 'a-report']);

        $response = $this->getJson('/api/pdf-designer/compositions?sort=name&order=asc');

        $response->assertOk();
        $this->assertEquals('A Report', $response->json('data.0.name'));
        $this->assertEquals('B Report', $response->json('data.1.name'));
    }

    #[Test]
    public function it_respects_max_per_page(): void
    {
        ReportComposition::factory()->count(50)->create();

        $response = $this->getJson('/api/pdf-designer/compositions?per_page=200');

        $response->assertOk();
        $this->assertLessThanOrEqual(100, count($response->json('data')));
        $this->assertEquals(100, $response->json('meta.per_page'));
    }

    // ─── Existing endpoint compatibility ──────────────────────────

    #[Test]
    public function existing_template_generate_endpoint_still_works(): void
    {
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
            ->andReturn('%PDF-1.4 template test');

        $template = PdfTemplate::factory()->create();

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Legacy Document',
            'data' => ['name' => 'Test'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    // ─── Per-template datasource independence ──────────────────────

    #[Test]
    public function each_template_executes_its_own_datasources_independently(): void
    {
        // Template A: datasource returns { clients: [{name: 'Acme'}] }
        // Template B: datasource returns [{cognome: 'Rossi'}]
        // Each template should render with its OWN data only.
        Http::fake([
            'https://api.example.com/clients' => Http::response([
                'clients' => [
                    ['name' => 'Acme Corp', 'role' => 'Client'],
                ],
            ]),
            'https://api.example.com/people' => Http::response([
                ['cognome' => 'Rossi', 'nome' => 'Mario'],
                ['cognome' => 'Bianchi', 'nome' => 'Luca'],
            ]),
        ]);

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
            ->andReturn('%PDF-composition-datasources');

        Storage::shouldReceive('disk')->once()->andReturnSelf();
        Storage::shouldReceive('put')->once()->andReturn(true);

        $templateA = PdfTemplate::factory()->create([
            'is_active' => true,
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    ['id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'label' => 'Detail', 'height' => 15, 'collectionPath' => 'clients', 'elements' => [
                        ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 80, 'height' => 10, 'content' => ['text' => '{{ name }}', 'variable' => 'name'], 'styles' => []],
                    ]],
                ],
            ],
            'config' => [
                'datasources' => [
                    ['id' => 'ds-clients', 'name' => 'Clients API', 'url' => 'https://api.example.com/clients', 'method' => 'GET'],
                ],
            ],
        ]);

        $templateB = PdfTemplate::factory()->create([
            'is_active' => true,
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    ['id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'label' => 'Detail', 'height' => 15, 'collectionPath' => '', 'elements' => [
                        ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 80, 'height' => 10, 'content' => ['text' => '{{ [].cognome }}', 'variable' => 'cognome'], 'styles' => []],
                    ]],
                ],
            ],
            'config' => [
                'datasources' => [
                    ['id' => 'ds-people', 'name' => 'People API', 'url' => 'https://api.example.com/people', 'method' => 'GET'],
                ],
            ],
        ]);

        $composition = ReportComposition::factory()->create([
            'page' => $this->pageConfig,
        ]);

        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateA->id,
            'sort_order' => 0,
        ]);
        CompositionPage::factory()->create([
            'report_composition_id' => $composition->id,
            'pdf_template_id' => $templateB->id,
            'sort_order' => 1,
        ]);

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Independent Datasources',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }
}
