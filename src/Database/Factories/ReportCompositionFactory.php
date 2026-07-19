<?php

declare(strict_types=1);

namespace Toolreport\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Toolreport\Core\Models\ReportComposition;

class ReportCompositionFactory extends Factory
{
    protected $model = ReportComposition::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->paragraph(),
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'paper_size' => 'a4',
                'margin' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'is_active' => true,
        ];
    }
}
