<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Tests\TestCase;

class PdfDocumentApiTest extends TestCase
{
    #[Test]
    public function it_generates_a_pdf()
    {
        $template = PdfTemplate::factory()->create([
            'config' => [
                'elements' => [
                    [
                        'type' => 'text',
                        'x' => 20, 'y' => 20, 'width' => 170, 'height' => 15,
                        'content' => ['text' => 'Hello {{ name }}'],
                        'styles' => ['fontSize' => 14],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Test Document',
            'data' => ['name' => 'John'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
        $response->assertJsonStructure([
            'data' => ['id', 'status', 'file_size', 'title'],
        ]);
    }

    #[Test]
    public function it_lists_documents_for_a_template()
    {
        $template = PdfTemplate::factory()->create();
        PdfDocument::factory()->count(2)->forTemplate($template->id)->create();
        PdfDocument::factory()->forTemplate($template->id)->done()->create();

        $response = $this->getJson("/api/pdf-designer/templates/{$template->id}/documents");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_shows_document_details()
    {
        $template = PdfTemplate::factory()->create();
        $document = PdfDocument::factory()->forTemplate($template->id)->done()->create();

        $response = $this->getJson("/api/pdf-designer/documents/{$document->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $document->id);
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_returns_404_for_non_existent_template()
    {
        $response = $this->postJson('/api/pdf-designer/templates/99999/generate');

        $response->assertNotFound();
    }

    #[Test]
    public function it_executes_datasources_when_no_explicit_data_provided()
    {
        Http::fake([
            'https://api.example.com/users' => Http::response([
                'users' => [
                    ['name' => 'Alice', 'email' => 'alice@test.com'],
                ],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    ['id' => 'title', 'type' => 'title', 'anchor' => 'top', 'label' => 'Title', 'height' => 20, 'datasourceId' => null, 'collectionPath' => null, 'elements' => []],
                    [
                        'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'label' => 'Detail', 'height' => 120,
                        'datasourceId' => 'ds-1',
                        'collectionPath' => 'users',
                        'elements' => [
                            ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 100, 'height' => 15, 'styles' => [], 'content' => ['text' => 'Name', 'variable' => 'name']],
                        ],
                    ],
                ],
            ],
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'Users API',
                        'url' => 'https://api.example.com/users',
                        'method' => 'GET',
                        'headers' => [],
                        'auth' => ['type' => 'none'],
                        'timeout' => 30,
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Test Document',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_uses_explicit_data_over_datasources()
    {
        Http::fake([
            'https://api.example.com/users' => Http::response([
                'data' => [['name' => 'Should Not Appear']],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'Users API',
                        'url' => 'https://api.example.com/users',
                        'method' => 'GET',
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Test Document',
            'data' => ['name' => 'Explicit'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_resolves_nested_collection_path_from_band()
    {
        Http::fake([
            'https://api.example.com/report' => Http::response([
                'data' => [
                    'items' => [
                        ['product' => 'Widget', 'price' => 9.99],
                        ['product' => 'Gadget', 'price' => 24.99],
                    ],
                ],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'page' => [
                'width' => 210, 'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    ['id' => 'title', 'type' => 'title', 'anchor' => 'top', 'label' => 'Title', 'height' => 20, 'datasourceId' => null, 'collectionPath' => null, 'elements' => []],
                    [
                        'id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'label' => 'Detail', 'height' => 120,
                        'datasourceId' => 'ds-1',
                        'collectionPath' => 'data.items',
                        'elements' => [
                            ['type' => 'text', 'x' => 10, 'y' => 5, 'width' => 100, 'height' => 15, 'styles' => [], 'content' => ['text' => 'Product', 'variable' => 'product']],
                        ],
                    ],
                ],
            ],
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'Report API',
                        'url' => 'https://api.example.com/report',
                        'method' => 'GET',
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Nested Test',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }
}
