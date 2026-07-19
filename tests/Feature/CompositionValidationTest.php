<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Tests\TestCase;

class CompositionValidationTest extends TestCase
{
    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validPayload = [
            'name' => 'Test Composition',
            'slug' => 'test-composition',
            'description' => 'A test composition',
            'page' => [
                'width' => 210,
                'height' => 297,
                'margin' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'is_active' => true,
        ];
    }

    // ─── FR-3: Validation ─────────────────────────────────────────

    #[Test]
    public function it_rejects_different_paper_sizes(): void
    {
        $a4Template = PdfTemplate::factory()->create([
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [],
            ],
        ]);

        $letterTemplate = PdfTemplate::factory()->create([
            'page' => [
                'width' => 215.9,
                'height' => 279.4,
                'orientation' => 'portrait',
                'paper_size' => 'letter',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [],
            ],
        ]);

        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'pages' => [
                ['pdf_template_id' => $a4Template->id, 'sort_order' => 0],
                ['pdf_template_id' => $letterTemplate->id, 'sort_order' => 1],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages.1.pdf_template_id']);
        $body = $response->json();
        $this->assertStringContainsString('ancho', $body['errors']['pages.1.pdf_template_id'][0]);
        $this->assertStringContainsString($letterTemplate->name, $body['errors']['pages.1.pdf_template_id'][0]);
    }

    #[Test]
    public function it_rejects_empty_pages_array(): void
    {
        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'pages' => [],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages']);
    }

    #[Test]
    public function it_allows_duplicate_templates_at_different_positions(): void
    {
        $template = PdfTemplate::factory()->create();

        // Same template at two different sort positions
        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'pages' => [
                ['pdf_template_id' => $template->id, 'sort_order' => 0],
                ['pdf_template_id' => $template->id, 'sort_order' => 1],
            ],
        ]));

        $response->assertCreated();
        $response->assertJsonCount(2, 'data.pages');
    }

    #[Test]
    public function it_rejects_non_existent_template(): void
    {
        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'pages' => [
                ['pdf_template_id' => 99999, 'sort_order' => 0],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages.0.pdf_template_id']);
    }

    #[Test]
    public function it_rejects_invalid_slug_format(): void
    {
        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'slug' => 'Invalid Slug!',
            'pages' => [
                ['pdf_template_id' => PdfTemplate::factory()->create()->id, 'sort_order' => 0],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);

        // Also test uppercase
        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'slug' => 'UPPERCASE',
            'pages' => [
                ['pdf_template_id' => PdfTemplate::factory()->create()->id, 'sort_order' => 0],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_allows_partial_update(): void
    {
        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create();

        // Add a page to the composition
        $composition->pages()->create([
            'pdf_template_id' => $template->id,
            'sort_order' => 0,
        ]);

        // Send only a name update
        $response = $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');

        // Original slug should remain unchanged
        $originalSlug = $composition->slug;
        $composition->refresh();
        $this->assertEquals($originalSlug, $composition->slug);

        // Pages should remain unchanged
        $composition->load('pages');
        $this->assertCount(1, $composition->pages);
    }

    #[Test]
    public function it_rejects_too_many_pages(): void
    {
        $template = PdfTemplate::factory()->create();
        $pages = [];
        for ($i = 0; $i < 51; $i++) {
            $pages[] = ['pdf_template_id' => $template->id, 'sort_order' => $i];
        }

        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'pages' => $pages,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages']);
    }

    #[Test]
    public function it_requires_name_and_slug(): void
    {
        $response = $this->postJson('/api/pdf-designer/compositions', [
            'pages' => [
                ['pdf_template_id' => PdfTemplate::factory()->create()->id, 'sort_order' => 0],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'slug', 'page']);
    }

    #[Test]
    public function it_rejects_duplicate_slug(): void
    {
        $template = PdfTemplate::factory()->create();

        // Create first composition with explicit slug
        $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'slug' => 'my-unique-slug',
            'pages' => [['pdf_template_id' => $template->id, 'sort_order' => 0]],
        ]))->assertCreated();

        // Try to create second with same slug
        $response = $this->postJson('/api/pdf-designer/compositions', array_merge($this->validPayload, [
            'slug' => 'my-unique-slug',
            'pages' => [['pdf_template_id' => $template->id, 'sort_order' => 0]],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_allows_update_without_changing_slug(): void
    {
        $template = PdfTemplate::factory()->create();
        $composition = ReportComposition::factory()->create([
            'slug' => 'original-slug',
        ]);

        $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'name' => 'Updated',
            'pages' => [['pdf_template_id' => $template->id, 'sort_order' => 0]],
        ])->assertOk();
    }

    #[Test]
    public function it_validates_page_dimensions(): void
    {
        $response = $this->postJson('/api/pdf-designer/compositions', [
            'name' => 'Test',
            'slug' => 'test',
            'page' => ['width' => 5, 'height' => 5000, 'margin' => ['top' => -1, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            'pages' => [
                ['pdf_template_id' => PdfTemplate::factory()->create()->id, 'sort_order' => 0],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page.width', 'page.height', 'page.margin.top']);
    }

    #[Test]
    public function it_requires_title_for_generation(): void
    {
        $composition = ReportComposition::factory()->create();

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function it_handles_generation_with_composition_that_has_no_pages(): void
    {
        $composition = ReportComposition::factory()->create();

        $response = $this->postJson("/api/pdf-designer/compositions/{$composition->id}/generate", [
            'title' => 'Test',
            'data' => [],
        ]);

        // Should get a validation error since there are no pages
        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_update_with_different_paper_sizes(): void
    {
        $a4Template = PdfTemplate::factory()->create([
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [],
            ],
        ]);

        $letterTemplate = PdfTemplate::factory()->create([
            'page' => [
                'width' => 215.9,
                'height' => 279.4,
                'orientation' => 'portrait',
                'paper_size' => 'letter',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [],
            ],
        ]);

        $composition = ReportComposition::factory()->create();
        $composition->pages()->createMany([
            ['pdf_template_id' => $a4Template->id, 'sort_order' => 0],
        ]);

        $response = $this->putJson("/api/pdf-designer/compositions/{$composition->id}", [
            'page' => [
                'width' => 210,
                'height' => 297,
                'margin' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'pages' => [
                ['pdf_template_id' => $a4Template->id, 'sort_order' => 0],
                ['pdf_template_id' => $letterTemplate->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pages.1.pdf_template_id']);
    }
}
