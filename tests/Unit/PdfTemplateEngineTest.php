<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Tests\TestCase;

class PdfTemplateEngineTest extends TestCase
{
    #[Test]
    public function default_engine_is_pdf_engine(): void
    {
        $template = PdfTemplate::factory()->create();

        $this->assertEquals('pdf-engine', $template->engine);
    }

    #[Test]
    public function can_set_engine_to_pdf_engine(): void
    {
        $template = PdfTemplate::factory()->create(['engine' => 'pdf-engine']);

        $this->assertEquals('pdf-engine', $template->engine);
    }

    #[Test]
    public function isPdfEngine_returns_true_for_pdf_engine(): void
    {
        $template = PdfTemplate::factory()->create(['engine' => 'pdf-engine']);

        $this->assertTrue($template->isPdfEngine());
    }
}
