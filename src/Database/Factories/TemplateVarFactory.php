<?php

declare(strict_types=1);

namespace Toolreport\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\TemplateVar;

class TemplateVarFactory extends Factory
{
    protected $model = TemplateVar::class;

    public function definition(): array
    {
        return [
            'pdf_template_id' => PdfTemplate::factory(),
            'name' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'visibility' => 'public',
            'is_required' => false,
            'description' => fake()->sentence(),
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => 'private']);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }
}
