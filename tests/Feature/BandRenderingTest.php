<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Tests\TestCase;

class BandRenderingTest extends TestCase
{
    #[Test]
    public function it_generates_pdf_with_band_template_and_detail_repetition()
    {
        Http::fake([
            'https://api.example.com/orders' => Http::response([
                'orders' => [
                    ['id' => 1, 'total' => 29.99],
                    ['id' => 2, 'total' => 49.99],
                    ['id' => 3, 'total' => 99.99],
                ],
                'client' => ['name' => 'Acme Corp'],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'engine' => 'pdf-engine',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'header',
                        'type' => 'header',
                        'anchor' => 'top',
                        'height' => 30,
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 110, 'y2' => 5],
                        ],
                    ],
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'height' => 15,
                        'collectionPath' => 'orders',
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 90, 'y2' => 5],
                        ],
                    ],
                    [
                        'id' => 'footer',
                        'type' => 'footer',
                        'anchor' => 'bottom',
                        'height' => 20,
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 110, 'y2' => 5],
                        ],
                    ],
                ],
            ],
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'Orders API',
                        'url' => 'https://api.example.com/orders',
                        'method' => 'GET',
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Band Test',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_generates_pdf_with_v1_template_without_bands()
    {
        $template = PdfTemplate::factory()->create([
            'config' => [
                'elements' => [
                    [
                        'type' => 'text',
                        'x' => 20,
                        'y' => 20,
                        'width' => 170,
                        'height' => 15,
                        'content' => ['text' => 'Hello {{ name }}'],
                        'styles' => ['fontSize' => 14],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'V1 Template',
            'data' => ['name' => 'World'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_generates_pdf_with_band_template_using_explicit_data()
    {
        $template = PdfTemplate::factory()->create([
            'engine' => 'pdf-engine',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'height' => 15,
                        'collectionPath' => 'items',
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 90, 'y2' => 5],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Explicit Data Band',
            'data' => [
                'items' => [
                    ['name' => 'Widget'],
                    ['name' => 'Gadget'],
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_generates_pdf_with_band_template_with_nested_collectionPath()
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
            'engine' => 'pdf-engine',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'height' => 15,
                        'collectionPath' => 'data.items',
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 90, 'y2' => 5],
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
            'title' => 'Nested CollectionPath',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_preserves_collection_arrays_for_band_rendering()
    {
        // Verify that executeForRendering() keeps collection arrays intact
        // by using a datasource that returns an array and checking PDF generation succeeds
        Http::fake([
            'https://api.example.com/orders' => Http::response([
                'orders' => [
                    ['id' => 1, 'total' => 29.99],
                    ['id' => 2, 'total' => 49.99],
                ],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'engine' => 'pdf-engine',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'height' => 15,
                        'collectionPath' => 'orders',
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 90, 'y2' => 5],
                        ],
                    ],
                ],
            ],
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'Orders API',
                        'url' => 'https://api.example.com/orders',
                        'method' => 'GET',
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Collection Preservation',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'done');
    }

    #[Test]
    public function it_renders_root_level_array_with_bracket_notation()
    {
        Http::fake([
            'https://api.example.com/people' => Http::response([
                ['cognome' => 'Rossi', 'nome' => 'Mario'],
                ['cognome' => 'Bianchi', 'nome' => 'Luca'],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'engine' => 'pdf-engine',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'label' => 'Detail',
                        'height' => 15,
                        'collectionPath' => '',
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 90, 'y2' => 5],
                        ],
                    ],
                ],
            ],
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'People API',
                        'url' => 'https://api.example.com/people',
                        'method' => 'GET',
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Root Array Test',
        ]);

        $response->assertCreated();
        $document = $response->json('data');
        $this->assertEquals('done', $document['status']);
    }

    #[Test]
    public function it_renders_root_level_array_with_null_collection_path_from_middleware()
    {
        // Simulates ConvertEmptyStringsToNull converting collectionPath: "" → null
        // The datasourceId on the band signals that this band is bound to a datasource,
        // so null collectionPath is treated as "" (root-level array iteration).
        Http::fake([
            'https://api.example.com/people' => Http::response([
                ['cognome' => 'Rossi', 'nome' => 'Mario'],
                ['cognome' => 'Bianchi', 'nome' => 'Luca'],
            ]),
        ]);

        $template = PdfTemplate::factory()->create([
            'engine' => 'pdf-engine',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    [
                        'id' => 'detail',
                        'type' => 'detail',
                        'anchor' => 'fill',
                        'label' => 'Detail',
                        'height' => 15,
                        // collectionPath is null — simulates what ConvertEmptyStringsToNull does
                        // to "": a root-level array datasource. datasourceId signals the binding.
                        'collectionPath' => null,
                        'datasourceId' => 'ds-1',
                        'children' => [
                            ['type' => 'Shape', 'shapeType' => 'line', 'x1' => 10, 'y1' => 5, 'x2' => 90, 'y2' => 5],
                        ],
                    ],
                ],
            ],
            'config' => [
                'datasources' => [
                    [
                        'id' => 'ds-1',
                        'name' => 'People API',
                        'url' => 'https://api.example.com/people',
                        'method' => 'GET',
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/generate", [
            'title' => 'Null CollectionPath Test',
        ]);

        $response->assertCreated();
        $document = $response->json('data');
        $this->assertEquals('done', $document['status']);
    }
}