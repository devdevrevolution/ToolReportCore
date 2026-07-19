<?php

declare(strict_types=1);

namespace Toolreport\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Toolreport\Core\Models\PdfDocument;

class PdfDocumentFactory extends Factory
{
    protected $model = PdfDocument::class;

    public function definition(): array
    {
        return [
            'pdf_template_id' => PdfTemplateFactory::new(),
            'title' => fake()->sentence(3),
            'data' => [],
            'status' => 'pending',
        ];
    }

    public function done(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'done',
            'file_path' => 'pdf-documents/' . fake()->uuid() . '.pdf',
            'file_size' => fake()->numberBetween(1000, 50000),
            'generated_at' => fake()->dateTimeThisMonth(),
        ]);
    }

    public function forTemplate(int $templateId): static
    {
        return $this->state(fn(array $attributes) => [
            'pdf_template_id' => $templateId,
        ]);
    }

    public function forComposition(int $compositionId): static
    {
        return $this->state(fn(array $attributes) => [
            'report_composition_id' => $compositionId,
            'pdf_template_id' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}
