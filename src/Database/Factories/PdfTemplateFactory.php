<?php

declare(strict_types=1);

namespace Toolreport\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Toolreport\Core\Models\PdfTemplate;

class PdfTemplateFactory extends Factory
{
    protected $model = PdfTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->paragraph(),
            'engine' => 'dompdf',
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'bands' => [
                    ['id' => 'title', 'type' => 'title', 'anchor' => 'top', 'label' => 'Title', 'height' => 20, 'enabled' => true, 'children' => []],
                    ['id' => 'detail', 'type' => 'detail', 'anchor' => 'fill', 'label' => 'Detail', 'height' => 120, 'enabled' => true, 'children' => []],
                ],
            ],
            'config' => [],
            'is_active' => true,
        ];
    }

    public function withElements(array $elements): static
    {
        return $this->state(function (array $attributes) use ($elements) {
            $page = $attributes['page'] ?? [];
            $bands = $page['bands'] ?? [];

            // Put elements in the detail band
            foreach ($bands as &$band) {
                if ($band['id'] === 'detail') {
                    $band['children'] = $elements;
                }
            }

            $page['bands'] = $bands;
            return ['page' => $page];
        });
    }
}