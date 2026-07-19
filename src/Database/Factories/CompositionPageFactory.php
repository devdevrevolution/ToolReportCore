<?php

declare(strict_types=1);

namespace Toolreport\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Toolreport\Core\Models\CompositionPage;
use Toolreport\Core\Models\ReportComposition;

class CompositionPageFactory extends Factory
{
    protected $model = CompositionPage::class;

    public function definition(): array
    {
        return [
            'report_composition_id' => ReportCompositionFactory::new(),
            'pdf_template_id' => PdfTemplateFactory::new(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
