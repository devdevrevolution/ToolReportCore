<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Tests\TestCase;

class PdfTemplateEngineTest extends TestCase
{
    #[Test]
    public function default_engine_is_dompdf(): void
    {
        $template = PdfTemplate::factory()->create();

        $this->assertEquals('dompdf', $template->engine);
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

    #[Test]
    public function isPdfEngine_returns_false_for_dompdf(): void
    {
        $template = PdfTemplate::factory()->create(['engine' => 'dompdf']);

        $this->assertFalse($template->isPdfEngine());
    }

    #[Test]
    public function isDomPdf_returns_true_for_dompdf(): void
    {
        $template = PdfTemplate::factory()->create(['engine' => 'dompdf']);

        $this->assertTrue($template->isDomPdf());
    }

    #[Test]
    public function isDomPdf_returns_false_for_pdf_engine(): void
    {
        $template = PdfTemplate::factory()->create(['engine' => 'pdf-engine']);

        $this->assertFalse($template->isDomPdf());
    }
}
