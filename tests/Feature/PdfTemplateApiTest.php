<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Tests\TestCase;

class PdfTemplateApiTest extends TestCase
{
    private array $validTemplateData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validTemplateData = [
            'name' => 'Invoice Template',
            'slug' => 'invoice-template',
            'description' => 'A test template',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'title', 'type' => 'title', 'anchor' => 'top', 'label' => 'Title', 'height' => 20,
                        'children' => [],
                    ],
                    [
                        'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'label' => 'Detail', 'height' => 120,
                        'children' => [
                            [
                                'type' => 'text',
                                'x' => 20, 'y' => 20, 'width' => 170, 'height' => 15,
                                'content' => ['text' => 'Hello'],
                                'styles' => ['fontSize' => 12],
                                'locked' => false,
                                'visible' => true,
                                'rotation' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            'config' => [],
        ];
    }

    #[Test]
    public function it_lists_templates()
    {
        PdfTemplate::factory()->count(3)->create();

        $response = $this->getJson('/api/pdf-designer/templates');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_creates_a_template()
    {
        $response = $this->postJson('/api/pdf-designer/templates', $this->validTemplateData);

        $response->assertCreated();
        $response->assertJson([
            'message' => 'Template created successfully.',
        ]);
        $response->assertJsonPath('data.name', 'Invoice Template');

        $this->assertDatabaseHas('pdf_templates', [
            'slug' => 'invoice-template',
        ]);
    }

    #[Test]
    public function it_requires_name_and_slug()
    {
        $response = $this->postJson('/api/pdf-designer/templates', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'slug', 'page']);
    }

    #[Test]
    public function it_shows_a_template()
    {
        $template = PdfTemplate::factory()->create();

        $response = $this->getJson("/api/pdf-designer/templates/{$template->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $template->id);
        $response->assertJsonPath('data.name', $template->name);
    }

    #[Test]
    public function it_updates_a_template()
    {
        $template = PdfTemplate::factory()->create();

        $response = $this->putJson("/api/pdf-designer/templates/{$template->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pdf_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function it_deletes_a_template()
    {
        $template = PdfTemplate::factory()->create();

        $response = $this->deleteJson("/api/pdf-designer/templates/{$template->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('pdf_templates', ['id' => $template->id]);
    }

    #[Test]
    public function it_duplicates_a_template()
    {
        $template = PdfTemplate::factory()->create([
            'name' => 'Original',
            'slug' => 'original',
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/duplicate");

        $response->assertCreated();
        $this->assertDatabaseHas('pdf_templates', [
            'name' => 'Original (Copy)',
        ]);
    }

    #[Test]
    public function it_requires_valid_element_type()
    {
        $data = $this->validTemplateData;
        $data['page']['bands'][1]['children'][0]['type'] = 'invalid_type';

        $response = $this->postJson('/api/pdf-designer/templates', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page.bands.1.children.0.type']);
    }

    #[Test]
    public function it_requires_valid_band_type()
    {
        $data = $this->validTemplateData;
        $data['page']['bands'][0]['type'] = 'invalid_band';

        $response = $this->postJson('/api/pdf-designer/templates', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page.bands.0.type']);
    }

    #[Test]
    public function it_requires_valid_band_anchor()
    {
        $data = $this->validTemplateData;
        $data['page']['bands'][0]['anchor'] = 'invalid_anchor';

        $response = $this->postJson('/api/pdf-designer/templates', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page.bands.0.anchor']);
    }

    #[Test]
    public function it_requires_valid_element_fields_in_band()
    {
        $data = $this->validTemplateData;
        unset($data['page']['bands'][1]['children'][0]['x']);

        $response = $this->postJson('/api/pdf-designer/templates', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['page.bands.1.children.0.x']);
    }

    #[Test]
    public function it_accepts_template_without_bands_legacy()
    {
        $data = $this->validTemplateData;
        unset($data['page']['bands']);

        $response = $this->postJson('/api/pdf-designer/templates', $data);

        $response->assertCreated();
    }

    #[Test]
    public function it_accepts_legacy_flat_elements()
    {
        $data = $this->validTemplateData;
        // Move children out of bands into flat page.elements (v2 format)
        $elements = $data['page']['bands'][1]['children'];
        unset($data['page']['bands'][1]['children']);
        $data['page']['elements'] = $elements;

        $response = $this->postJson('/api/pdf-designer/templates', $data);

        $response->assertCreated();
    }
}
